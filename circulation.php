<?php
require_once __DIR__ . '/layout.php';
$me = require_role('librarian');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash_set('error', 'Your session expired. Please try again.');
    } elseif ($action === 'borrow') {
        [$ok, $msg] = issue_loan($pdo, $_POST['student_id'] ?? '', $_POST['book_id'] ?? '', (int) $me['id']);
        flash_set($ok ? 'success' : 'error', $msg, 'borrow');
    } elseif ($action === 'return') {
        [$ok, $msg] = return_loan($pdo, $_POST['book_id'] ?? '', $_POST['student_id'] ?? '', $_POST['condition'] ?? 'Intact');
        flash_set($ok ? 'success' : 'error', $msg, 'return');
    }
    redirect('circulation.php');
}

// Latest 10 checkouts and returns
$recent = $pdo->query(
    "SELECT * FROM (
        SELECT r.borrowed_at AS ts, 'Checkout' AS type, u.full_name, u.id_no, b.title, b.call_no
          FROM borrow_records r JOIN users u ON u.id = r.user_id JOIN books b ON b.id = r.book_id
        UNION ALL
        SELECT r.returned_at AS ts, 'Return' AS type, u.full_name, u.id_no, b.title, b.call_no
          FROM borrow_records r JOIN users u ON u.id = r.user_id JOIN books b ON b.id = r.book_id
         WHERE r.returned_at IS NOT NULL
     ) t ORDER BY ts DESC LIMIT 10"
)->fetchAll();

lib_header('Borrow & Return', 'circulation', true);
?>
<div class="page-title"><div><h1>Circulation Desk: Borrow &amp; Return Books</h1></div></div>

<div class="two-col even">
  <section class="panel">
    <div class="panel-head"><h2>Borrow Book</h2></div>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="borrow">
      <label class="f" for="b_student">Student ID</label>
      <input class="in" id="b_student" name="student_id" placeholder="e.g. 2024-0012" required>
      <label class="f" for="b_book">Book ID / Call No.</label>
      <input class="in" id="b_book" name="book_id" placeholder="e.g. FIL-899.22" required>
      <div class="grid2">
        <div><label class="f">Borrow Date</label><input class="in" value="<?= h(date('F j, Y')) ?>" readonly></div>
        <div><label class="f">Due Date</label><input class="in" value="<?= h(date('F j, Y', strtotime('+' . LOAN_DAYS . ' days'))) ?>" readonly></div>
      </div>
      <button class="btn-p btn-block mt" type="submit">Borrow Book</button>
    </form>
    <div class="mt"><?php flash_show('borrow'); ?></div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Return Book</h2></div>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="return">
      <label class="f" for="r_book">Book ID / Call No.</label>
      <input class="in" id="r_book" name="book_id" placeholder="e.g. FIL-899.22" required>
      <label class="f" for="r_student">Student ID <span class="muted">(only if several students have this book)</span></label>
      <input class="in" id="r_student" name="student_id" placeholder="e.g. 2024-0012">
      <div class="grid2">
        <div><label class="f">Return Date</label><input class="in" value="<?= h(date('F j, Y')) ?>" readonly></div>
        <div><label class="f" for="r_cond">Condition Status</label>
          <select class="in" id="r_cond" name="condition"><option>Intact</option><option>Damaged</option></select></div>
      </div>
      <button class="btn-p btn-block mt" type="submit">Return Book</button>
    </form>
    <div class="mt"><?php flash_show('return'); ?></div>
  </section>
</div>

<section class="panel mt">
  <div class="panel-head"><h2>Today's Recent Transactions</h2></div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Timestamp</th><th>Student Name</th><th>Student ID</th><th>Book Title</th><th>Accession / Call No.</th><th>Type</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$recent): ?><tr><td colspan="7" class="empty">No transactions yet.</td></tr><?php endif; ?>
      <?php foreach ($recent as $t): $out = ($t['type'] === 'Checkout'); ?>
        <tr>
          <td><?= h(fmt_date($t['ts'])) ?> <span class="sub"><?= h(fmt_time($t['ts'])) ?></span></td>
          <td><?= h($t['full_name']) ?></td>
          <td><?= h($t['id_no']) ?></td>
          <td><?= h($t['title']) ?></td>
          <td><?= h($t['call_no']) ?></td>
          <td class="<?= $out ? 'txt-blue' : 'txt-green' ?>"><?= $out ? 'Checkout' : 'Return' ?></td>
          <td><span class="pill <?= $out ? 'blue' : 'green' ?>"><?= $out ? 'Borrowed' : 'Available' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php lib_footer();
