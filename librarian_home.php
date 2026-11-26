<?php
require_once __DIR__ . '/layout.php';
$me = require_role('librarian');

// Dashboard numbers (all counted from the borrow records)
$dueToday   = (int) $pdo->query('SELECT COUNT(*) FROM borrow_records WHERE returned_at IS NULL AND due_date = CURDATE()')->fetchColumn();
$overdueQ   = (int) $pdo->query('SELECT COUNT(*) FROM borrow_records WHERE returned_at IS NULL AND due_date < CURDATE()')->fetchColumn();
$loansToday = (int) $pdo->query('SELECT COUNT(*) FROM borrow_records WHERE DATE(borrowed_at) = CURDATE()')->fetchColumn();
$inCirc     = (int) $pdo->query('SELECT COUNT(*) FROM borrow_records WHERE returned_at IS NULL')->fetchColumn();
$unpaid     = (float) $pdo->query('SELECT COALESCE(SUM(' . sql_fine_expr('r') . '), 0) FROM borrow_records r WHERE ' . sql_unpaid_cond('r'))->fetchColumn();

// Latest 5 loans (newest borrow or return first)
$activity = $pdo->query(
    'SELECT ' . loan_cols() . ', GREATEST(r.borrowed_at, COALESCE(r.returned_at, r.borrowed_at)) AS last_ts '
    . loan_from() . ' ORDER BY last_ts DESC, r.id DESC LIMIT 5'
)->fetchAll();

$notices = [
    ['End of Quarter Notice', 'Priority', 'Return all borrowed books before Friday 3:00 PM for clearance verification.'],
    ['Cashier Fine Cut-off',  'Daily',    'Daily cash collections and settlement remittal cutoff strictly at 3:30 PM.'],
    ['Friday Shelf Inventory', 'Weekly',  'Shelf inventory audit starts after library hours.'],
];

lib_header('Librarian Home', 'home', true);
?>
<section class="banner">
  <h1>Welcome, <?= h($me['name']) ?></h1>
  <div class="chips">
    <span class="chip"><?= h(date('l, M j, Y')) ?></span>
    <span class="chip">Open (7:30 AM - 4:30 PM)</span>
    <span class="chip">Active Duty: <?= h($me['name']) ?></span>
  </div>
</section>

<section class="stats">
  <div class="stat"><div class="lbl">Books due today</div><div class="num"><?= $dueToday ?></div><div class="sub">Expected by 4:00 PM</div></div>
  <div class="stat"><div class="lbl">Overdue notice queue</div><div class="num red"><?= $overdueQ ?></div><div class="sub red"><?= h(peso($unpaid)) ?> unpaid fines pending</div></div>
  <div class="stat"><div class="lbl">Today's loans issued</div><div class="num"><?= $loansToday ?></div><div class="sub">Logged today</div></div>
  <div class="stat"><div class="lbl">Total books in circulation</div><div class="num"><?= $inCirc ?></div><div class="sub">Active patron loans</div></div>
</section>

<section class="panel">
  <div class="panel-head"><h2>Circulation Desk Operations</h2></div>
  <div class="ops">
    <a class="op primary" href="books.php"><strong>Manual Book Entry</strong><span>Call Number Search</span></a>
    <a class="op" href="circulation.php"><strong>Issue / Return Book</strong><span>Access Desk Ledger</span></a>
    <a class="op" href="fines.php"><strong>Due Dates &amp; Slips</strong><span>Print Overdue Notices</span></a>
    <a class="op" href="books.php"><strong>Add and Delete Books</strong><span>Update books</span></a>
  </div>
</section>

<div class="two-col">
  <section class="panel">
    <div class="panel-head"><h2>Today's Circulation Activity</h2><span class="pill blue">All Records (Latest 5)</span></div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Time</th><th>Student ID &amp; Name</th><th>Book Title &amp; Call No.</th><th>Action</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$activity): ?>
          <tr><td colspan="6" class="empty">No circulation activity yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($activity as $r): [$act, $actC, $stat, $statC] = loan_status($r); ?>
          <tr>
            <td><?= h(fmt_time($r['last_ts'])) ?></td>
            <td><strong><?= h($r['id_no']) ?></strong><span class="sub"><?= h($r['full_name']) ?></span></td>
            <td><strong><?= h($r['title']) ?></strong><span class="sub"><?= h($r['call_no']) ?></span></td>
            <td class="txt-<?= h($actC) ?>"><?= h($act) ?></td>
            <td><span class="pill <?= h($statC) ?>"><?= h($stat) ?></span></td>
            <td class="acts">
              <?php if ($statC === 'red'): ?>
                <a class="btn-d btn-xs" href="fines.php">Settle</a>
              <?php elseif ($r['returned_at'] !== null): ?>
                <a class="btn-s btn-xs" href="slip.php?id=<?= (int) $r['id'] ?>">Receipt</a>
              <?php else: ?>
                <a class="btn-s btn-xs" href="slip.php?id=<?= (int) $r['id'] ?>">Slip</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <aside>
    <section class="panel">
      <div class="panel-head"><h2>Staff Noticeboard</h2></div>
      <?php foreach ($notices as [$t, $tag, $body]): ?>
        <div class="notice"><b><?= h($t) ?> <i><?= h($tag) ?></i></b><?= h($body) ?></div>
      <?php endforeach; ?>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Desk Quick Reference</h2></div>
      <div class="ref"><span>Loan period:</span><strong><?= (int) LOAN_DAYS ?> days</strong></div>
      <div class="ref"><span>Overdue fine rate:</span><strong class="red"><?= h(peso(FINE_PER_DAY)) ?> / day</strong></div>
      <div class="ref"><span>Max books per student:</span><strong><?= (int) MAX_BOOKS ?> books</strong></div>
      <div class="ref"><span>Reservation holds for:</span><strong><?= (int) RESERVATION_DAYS ?> days</strong></div>
    </section>
  </aside>
</div>
<?php lib_footer();