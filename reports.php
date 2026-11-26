<?php
require_once __DIR__ . '/layout.php';
$me = require_role('librarian');

/* ---------- Inputs ---------- */
function valid_date(string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!valid_date($from)) { $from = date('Y-m-01'); }
if (!valid_date($to))   { $to   = date('Y-m-d'); }
if ($from > $to) { [$from, $to] = [$to, $from]; }
$type = $_GET['type'] ?? 'all';

$where  = 'DATE(r.borrowed_at) BETWEEN ? AND ?';
$params = [$from, $to];
if ($type === 'borrowed') { $where .= ' AND r.returned_at IS NULL'; }
if ($type === 'returned') { $where .= ' AND r.returned_at IS NOT NULL'; }
if ($type === 'overdue')  { $where .= ' AND r.returned_at IS NULL AND r.due_date < CURDATE()'; }

/* ---------- Report status label ---------- */
function report_status(array $r): array {
    if ($r['returned_at'] !== null) { return ['Returned', 'green']; }
    if ((int) $r['days_diff'] > 0)  { return ['Overdue', 'red']; }
    return ['Borrowed', 'blue'];
}

/* ---------- CSV export ---------- */
if (isset($_GET['export'])) {
    $st = $pdo->prepare('SELECT ' . loan_cols() . ' ' . loan_from() . " WHERE $where ORDER BY r.borrowed_at DESC, r.id DESC");
    $st->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="borrowing-report-' . $from . '-to-' . $to . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date borrowed', 'Student ID', 'Student', 'Book', 'Call No.', 'Category', 'Status', 'Due date', 'Returned', 'Fine (PHP)', 'Handled by']);
    foreach ($st->fetchAll() as $r) {
        fputcsv($out, [fmt_date($r['borrowed_at']), $r['id_no'], $r['full_name'], $r['title'], $r['call_no'], $r['category'],
                       report_status($r)[0], fmt_date($r['due_date']), $r['returned_at'] ? fmt_date($r['returned_at']) : '',
                       number_format((float) $r['fine_now'], 2, '.', ''), $r['handler'] ?? '']);
    }
    fclose($out);
    exit;
}

/* ---------- Summary for the whole period (not affected by the type filter) ---------- */
$sum = $pdo->prepare(
    'SELECT COUNT(*) AS borrowed,
            COALESCE(SUM(r.returned_at IS NOT NULL), 0) AS returned,
            COALESCE(SUM(r.returned_at IS NULL AND r.due_date < CURDATE()), 0) AS overdue,
            COALESCE(SUM(CASE WHEN ' . sql_unpaid_cond('r') . ' THEN ' . sql_fine_expr('r') . ' ELSE 0 END), 0) AS unpaid
       FROM borrow_records r WHERE DATE(r.borrowed_at) BETWEEN ? AND ?'
);
$sum->execute([$from, $to]);
$s = $sum->fetch();
$borrowed = (int) $s['borrowed'];
$rate = $borrowed > 0 ? round(((int) $s['returned'] / $borrowed) * 100, 1) : 0;

/* ---------- Activity log ---------- */
$cnt = $pdo->prepare('SELECT COUNT(*) FROM borrow_records r WHERE ' . $where);
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$page  = min(page_number(), max(1, (int) ceil($total / PER_PAGE)));
$st = $pdo->prepare('SELECT ' . loan_cols() . ' ' . loan_from() . " WHERE $where ORDER BY r.borrowed_at DESC, r.id DESC
                     LIMIT " . (int) PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));
$st->execute($params);
$rows = $st->fetchAll();

lib_header('Borrowing Reports', 'reports', true);
?>
<div class="page-title"><div><h1>Borrowing Reports &amp; Analytics</h1><p>Report period: <?= h(fmt_date($from)) ?> to <?= h(fmt_date($to)) ?></p></div></div>

<section class="panel no-print">
  <form class="filters" method="get" style="margin:0">
    <div><label class="f" style="margin-top:0" for="from">From</label><input class="in" type="date" id="from" name="from" value="<?= h($from) ?>"></div>
    <div><label class="f" style="margin-top:0" for="to">To</label><input class="in" type="date" id="to" name="to" value="<?= h($to) ?>"></div>
    <div><label class="f" style="margin-top:0" for="type">Report Type</label>
      <select class="in" id="type" name="type">
        <option value="all"<?= $type === 'all' ? ' selected' : '' ?>>All Transactions</option>
        <option value="borrowed"<?= $type === 'borrowed' ? ' selected' : '' ?>>Still Borrowed</option>
        <option value="returned"<?= $type === 'returned' ? ' selected' : '' ?>>Returned</option>
        <option value="overdue"<?= $type === 'overdue' ? ' selected' : '' ?>>Overdue</option>
      </select></div>
    <div style="align-self:flex-end"><button class="btn-p" type="submit">Generate Report</button></div>
    <div style="margin-left:auto; align-self:flex-end">
      <button class="btn-s" type="button" onclick="window.print()">Print</button>
      <a class="btn-s" href="?<?= h(http_build_query(array_merge($_GET, ['from' => $from, 'to' => $to, 'type' => $type, 'export' => 1]))) ?>">Export CSV</a>
    </div>
  </form>
</section>

<section class="stats three">
  <div class="stat"><div class="lbl">Total books borrowed</div><div class="num"><?= $borrowed ?></div><div class="sub">in this period</div></div>
  <div class="stat"><div class="lbl">Total returned</div><div class="num"><?= (int) $s['returned'] ?></div><div class="sub green"><?= h((string) $rate) ?>% return rate</div></div>
  <div class="stat"><div class="lbl">Total overdue</div><div class="num red"><?= (int) $s['overdue'] ?></div><div class="sub red"><?= h(peso($s['unpaid'])) ?> outstanding fines</div></div>
</section>

<section class="panel">
  <div class="panel-head"><div><h2>Circulation Activity Log</h2><span class="small muted">Displaying <?= count($rows) ?> of <?= $total ?> records for the period</span></div><span class="pill gray">Live Ledger</span></div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Date</th><th>Student</th><th>Book</th><th>Category</th><th>Status</th><th>Handled By</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty">No records in this period.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): [$lab, $col] = report_status($r); ?>
        <tr>
          <td><?= h(fmt_date($r['borrowed_at'])) ?></td>
          <td><strong><?= h($r['full_name']) ?></strong><span class="sub"><?= h($r['id_no']) ?></span></td>
          <td><em><?= h($r['title']) ?></em></td>
          <td><?= h($r['category']) ?></td>
          <td><span class="pill <?= $col ?>"><?= h($lab) ?></span></td>
          <td><?= h($r['handler'] ?? '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= pager($total, $page) ?>
</section>
<?php lib_footer();
