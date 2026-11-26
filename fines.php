<?php
require_once __DIR__ . '/layout.php';
$me = require_role('librarian');

/* ---------- Actions: remind / mark returned / settle ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash_set('error', 'Your session expired. Please try again.');
    } elseif ($action === 'remind') {
        $w = $pdo->prepare('SELECT u.full_name FROM borrow_records r JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.returned_at IS NULL');
        $w->execute([$id]);
        $who = $w->fetchColumn();
        if ($who === false) {
            flash_set('error', 'That book is not currently borrowed.');
        } else {
            $pdo->prepare('UPDATE borrow_records SET reminded_at = NOW() WHERE id = ?')->execute([$id]);
            flash_set('success', "Reminder logged for $who. (Email sending is not set up yet.)");
        }
    } elseif ($action === 'return') {
        [$ok, $msg] = return_loan_by_id($pdo, $id, 'Intact');
        flash_set($ok ? 'success' : 'error', $msg);
    } elseif ($action === 'settle') {
        $u = $pdo->prepare('UPDATE borrow_records SET fine_paid_at = CURDATE()
                            WHERE id = ? AND returned_at IS NOT NULL AND fine_amount > 0 AND fine_paid_at IS NULL');
        $u->execute([$id]);
        flash_set($u->rowCount() ? 'success' : 'error', $u->rowCount() ? 'Fine settled.' : 'Nothing to settle for that record.');
    }
    redirect('fines.php');
}

/* ---------- Summary cards ---------- */
$overdueCount = (int) $pdo->query('SELECT COUNT(*) FROM borrow_records WHERE returned_at IS NULL AND due_date < CURDATE()')->fetchColumn();
$totalFines   = (float) $pdo->query('SELECT COALESCE(SUM(' . sql_fine_expr('r') . '), 0) FROM borrow_records r WHERE ' . sql_unpaid_cond('r'))->fetchColumn();
$collected    = (float) $pdo->query('SELECT COALESCE(SUM(fine_amount), 0) FROM borrow_records WHERE fine_paid_at = CURDATE()')->fetchColumn();

/* ---------- List ---------- */
$q      = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$base   = '((r.returned_at IS NULL AND r.due_date <= CURDATE())
            OR (r.returned_at IS NOT NULL AND r.fine_amount > 0 AND r.fine_paid_at IS NULL))';
$where  = $base;
if ($filter === 'overdue')  { $where .= ' AND r.returned_at IS NULL AND r.due_date < CURDATE()'; }
if ($filter === 'today')    { $where .= ' AND r.returned_at IS NULL AND r.due_date = CURDATE()'; }
if ($filter === 'unpaid')   { $where .= ' AND r.returned_at IS NOT NULL'; }
$params = [];
if ($q !== '') {
    $where .= ' AND (u.id_no LIKE ? OR u.full_name LIKE ? OR b.title LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$cnt = $pdo->prepare('SELECT COUNT(*) ' . loan_from() . " WHERE $where");
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$page  = min(page_number(), max(1, (int) ceil($total / PER_PAGE)));
$st = $pdo->prepare('SELECT ' . loan_cols() . ' ' . loan_from() . " WHERE $where
                     ORDER BY r.returned_at IS NOT NULL, r.due_date ASC, r.id ASC
                     LIMIT " . (int) PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));
$st->execute($params);
$rows = $st->fetchAll();

lib_header('Book Fines', 'fines', true);
?>
<div class="page-title"><h1>Book Fines</h1></div>

<section class="stats three">
  <div class="stat row-stat"><span>Overdue Books</span><span class="num red"><?= $overdueCount ?></span></div>
  <div class="stat row-stat"><span>Total Fines</span><span class="num"><?= h(peso($totalFines)) ?></span></div>
  <div class="stat row-stat"><span>Collected Today</span><span class="num blue"><?= h(peso($collected)) ?></span></div>
</section>

<section class="panel">
  <form class="filters" method="get">
    <input class="in grow" name="q" value="<?= h($q) ?>" placeholder="Search Student ID, Name, Book Title...">
    <select class="in" name="filter" onchange="this.form.submit()">
      <option value="all"<?= $filter === 'all' ? ' selected' : '' ?>>All Overdue Records</option>
      <option value="overdue"<?= $filter === 'overdue' ? ' selected' : '' ?>>Overdue (not returned)</option>
      <option value="today"<?= $filter === 'today' ? ' selected' : '' ?>>Due Today</option>
      <option value="unpaid"<?= $filter === 'unpaid' ? ' selected' : '' ?>>Returned, fine unpaid</option>
    </select>
    <button class="btn-p" type="submit">Search</button>
    <a class="btn-s" href="fines.php" title="Refresh">Refresh</a>
  </form>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Student</th><th>Book Title</th><th>Days Borrowed / Overdue</th><th>Fine</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="5" class="empty">No overdue records.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r):
          $returned = $r['returned_at'] !== null;
          $d = (int) $r['days_diff'];
          $fine = (float) $r['fine_now']; ?>
        <tr>
          <td><strong><?= h($r['full_name']) ?></strong><span class="sub"><?= h($r['id_no']) ?></span></td>
          <td><?= h($r['title']) ?></td>
          <td>
            <?php if ($returned): ?>
              <span class="txt-red">Returned <?= (int) $r['late_days'] ?> day<?= (int) $r['late_days'] === 1 ? '' : 's' ?> late</span>
            <?php elseif ($d > 0): ?>
              <span class="txt-red"><?= $d ?> Day<?= $d === 1 ? '' : 's' ?> Overdue</span>
            <?php else: ?>
              <span class="txt-amber">Due Today</span>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= h(peso($fine)) ?></strong>
            <span class="sub"><span class="pill <?= $fine > 0 ? 'red' : 'amber' ?>"><?= $fine > 0 ? 'Unpaid' : 'Due Today' ?></span></span>
          </td>
          <td class="acts">
            <form class="inline" method="post">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <?php if ($returned): ?>
                <button class="btn-d btn-xs" name="action" value="settle" type="submit">Settle</button>
              <?php else: ?>
                <?php if ($d > 0): ?><button class="btn-p btn-xs" name="action" value="remind" type="submit">Send Reminder</button><?php endif; ?>
                <button class="btn-s btn-xs" name="action" value="return" type="submit" onclick="return confirm('Mark this book as returned?');">Mark Returned</button>
              <?php endif; ?>
            </form>
            <?php if (!$returned && $r['reminded_at']): ?><span class="sub">Reminded <?= h(fmt_date($r['reminded_at'])) ?></span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager($total, $page) ?>
</section>
<?php lib_footer();
