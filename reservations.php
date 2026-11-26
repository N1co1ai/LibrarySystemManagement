<?php
require_once __DIR__ . '/layout.php';
$me = require_role('librarian');

// Old reservations expire automatically
$pdo->exec("UPDATE reservations SET status = 'expired'
            WHERE status IN ('pending', 'ready') AND reserved_at < DATE_SUB(CURDATE(), INTERVAL " . (int) RESERVATION_DAYS . " DAY)");

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash_set('error', 'Your session expired. Please try again.');
    } elseif ($action === 'new') {
        $sid  = trim($_POST['student_id'] ?? '');
        $call = trim($_POST['book_id'] ?? '');
        $s = $pdo->prepare("SELECT id FROM users WHERE id_no = ? AND role = 'student' LIMIT 1");
        $s->execute([$sid]);
        $studentId = $s->fetchColumn();
        $b = $pdo->prepare('SELECT id FROM books WHERE call_no = ? LIMIT 1');
        $b->execute([$call]);
        $bookId = $b->fetchColumn();

        if ($sid === '' || $call === '') {
            flash_set('error', 'Please enter the Student ID and the Book ID.');
        } elseif (!$studentId) {
            flash_set('error', "No student found with ID $sid.");
        } elseif (!$bookId) {
            flash_set('error', "No book found with ID $call.");
        } else {
            $d = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE user_id = ? AND book_id = ? AND status IN ('pending', 'ready')");
            $d->execute([$studentId, $bookId]);
            if ((int) $d->fetchColumn() > 0) {
                flash_set('error', 'This student already has an open reservation for that book.');
            } else {
                $pdo->prepare('INSERT INTO reservations (user_id, book_id, reserved_at) VALUES (?, ?, CURDATE())')->execute([$studentId, $bookId]);
                flash_set('success', 'Reservation added.');
            }
        }
    } elseif ($action === 'approve') {
        $r = $pdo->prepare("SELECT b.copies_available FROM reservations rv JOIN books b ON b.id = rv.book_id WHERE rv.id = ? AND rv.status = 'pending'");
        $r->execute([$id]);
        $avail = $r->fetchColumn();
        if ($avail === false) {
            flash_set('error', 'That reservation is not pending.');
        } elseif ((int) $avail < 1) {
            flash_set('error', 'No copy is available yet, so it cannot be approved.');
        } else {
            $pdo->prepare("UPDATE reservations SET status = 'ready' WHERE id = ?")->execute([$id]);
            flash_set('success', 'Reservation approved. It is now Ready for Pickup.');
        }
    } elseif ($action === 'cancel') {
        $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'ready', 'expired')")->execute([$id]);
        flash_set('success', 'Reservation cancelled.');
    } elseif ($action === 'issue') {
        $r = $pdo->prepare("SELECT u.id_no, b.call_no FROM reservations rv
                            JOIN users u ON u.id = rv.user_id JOIN books b ON b.id = rv.book_id
                            WHERE rv.id = ? AND rv.status = 'ready'");
        $r->execute([$id]);
        $row = $r->fetch();
        if (!$row) {
            flash_set('error', 'That reservation is not ready for pickup.');
        } else {
            [$ok, $msg] = issue_loan($pdo, $row['id_no'], $row['call_no'], (int) $me['id']);
            if ($ok) { $pdo->prepare("UPDATE reservations SET status = 'completed' WHERE id = ?")->execute([$id]); }
            flash_set($ok ? 'success' : 'error', $msg);
        }
    }
    redirect('reservations.php' . (isset($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : ''));
}

/* ---------- Counts + list ---------- */
$counts = ['pending' => 0, 'ready' => 0, 'completed' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM reservations GROUP BY status")->fetchAll() as $c) {
    if (isset($counts[$c['status']])) { $counts[$c['status']] = (int) $c['n']; }
}

$tab    = $_GET['tab'] ?? 'all';
$q      = trim($_GET['q'] ?? '');
$where  = "rv.status <> 'cancelled'";
$params = [];
if ($tab === 'pending') { $where .= " AND rv.status = 'pending'"; }
if ($tab === 'ready')   { $where .= " AND rv.status = 'ready'"; }
if ($q !== '') {
    $where .= ' AND (u.full_name LIKE ? OR b.title LIKE ? OR u.id_no LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$from = 'FROM reservations rv JOIN users u ON u.id = rv.user_id JOIN books b ON b.id = rv.book_id';

$cnt = $pdo->prepare("SELECT COUNT(*) $from WHERE $where");
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$page  = min(page_number(), max(1, (int) ceil($total / PER_PAGE)));
$st = $pdo->prepare("SELECT rv.id, rv.reserved_at, rv.status, u.full_name, u.id_no, b.title, b.call_no
                     $from WHERE $where ORDER BY rv.reserved_at DESC, rv.id DESC
                     LIMIT " . (int) PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));
$st->execute($params);
$rows = $st->fetchAll();

$labels = ['pending' => ['Pending', 'amber'], 'ready' => ['Ready for Pickup', 'green'],
           'completed' => ['Completed', 'blue'], 'expired' => ['Expired', 'red']];

lib_header('Book Reservations', 'reservations', true);
?>
<div class="page-title no-print">
  <h1>Book Reservations</h1>
  <div>
    <button class="btn-s" type="button" onclick="window.print()">Print Pull List</button>
  </div>
</div>

<details class="newres no-print">
  <summary class="btn-p">+ New Reservation</summary>
  <section class="panel mt">
    <form method="post" autocomplete="off" class="grid2">
      <?= csrf_field() ?><input type="hidden" name="action" value="new">
      <div><label class="f" for="n_student">Student ID</label><input class="in" id="n_student" name="student_id" placeholder="e.g. 2024-0012" required></div>
      <div><label class="f" for="n_book">Book ID / Call No.</label><input class="in" id="n_book" name="book_id" placeholder="e.g. FIL-899.21" required></div>
      <div><button class="btn-p" type="submit">Add Reservation</button></div>
    </form>
  </section>
</details>

<section class="stats three">
  <div class="stat row-stat"><span>Pending</span><span class="num txt-amber"><?= $counts['pending'] ?></span></div>
  <div class="stat row-stat"><span>Ready for Pickup</span><span class="num green"><?= $counts['ready'] ?></span></div>
  <div class="stat row-stat"><span>Completed</span><span class="num blue"><?= $counts['completed'] ?></span></div>
</section>

<section class="panel">
  <form class="filters" method="get">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <input class="in grow" name="q" value="<?= h($q) ?>" placeholder="Search by student or book title...">
    <button class="btn-p" type="submit">Search</button>
    <span class="tabs">
      <a class="tab<?= $tab === 'all' ? ' on' : '' ?>" href="?tab=all">All</a>
      <a class="tab<?= $tab === 'pending' ? ' on' : '' ?>" href="?tab=pending">Pending (<?= $counts['pending'] ?>)</a>
      <a class="tab<?= $tab === 'ready' ? ' on' : '' ?>" href="?tab=ready">Ready for Pickup (<?= $counts['ready'] ?>)</a>
    </span>
  </form>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Student</th><th>Book</th><th>Date Reserved</th><th>Status</th><th class="no-print">Action</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="5" class="empty">No reservations found.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): [$lab, $col] = $labels[$r['status']] ?? [$r['status'], 'gray']; ?>
        <tr>
          <td><strong><?= h($r['full_name']) ?></strong><span class="sub"><?= h($r['id_no']) ?></span></td>
          <td><strong><?= h($r['title']) ?></strong><span class="sub"><?= h($r['call_no']) ?></span></td>
          <td><?= h(fmt_date($r['reserved_at'])) ?></td>
          <td><span class="pill <?= $col ?>"><?= h($lab) ?></span></td>
          <td class="acts no-print">
            <form class="inline" method="post">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <?php if ($r['status'] === 'pending'): ?>
                <button class="btn-p btn-xs" name="action" value="approve" type="submit">Approve</button>
                <button class="btn-s btn-xs" name="action" value="cancel" type="submit">Cancel</button>
              <?php elseif ($r['status'] === 'ready'): ?>
                <button class="btn-p btn-xs" name="action" value="issue" type="submit">Issue</button>
              <?php elseif ($r['status'] === 'expired'): ?>
                <button class="btn-s btn-xs" name="action" value="cancel" type="submit">Cancel</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager($total, $page) ?>
</section>
<?php lib_footer();
