<?php
require_once __DIR__ . '/layout.php';
require_role('librarian');

$st = $pdo->prepare('SELECT ' . loan_cols() . ' ' . loan_from() . ' WHERE r.id = ?');
$st->execute([(int) ($_GET['id'] ?? 0)]);
$r = $st->fetch();
if (!$r) { http_response_code(404); exit('Record not found.'); }

$isReceipt = ($r['returned_at'] !== null);
lib_header($isReceipt ? 'Return Receipt' : 'Borrowing Slip', 'circulation', false);
?>
<div class="slip">
  <h1>UNIVERSITY OF CEBU SOUTH LIBRARY</h1>
  <div class="sub"><?= $isReceipt ? 'RETURN RECEIPT' : 'OFFICIAL BORROWING SLIP' ?> &middot; Record #<?= (int) $r['id'] ?></div>
  <div class="line"><span>Student</span><strong><?= h($r['full_name']) ?> (<?= h($r['id_no']) ?>)</strong></div>
  <div class="line"><span>Book</span><strong><?= h($r['title']) ?></strong></div>
  <div class="line"><span>Call No.</span><strong><?= h($r['call_no']) ?></strong></div>
  <div class="line"><span>Date issued</span><strong><?= h(fmt_date($r['borrowed_at'])) ?></strong></div>
  <div class="line"><span>Handled by</span><strong><?= h($r['handler'] ?? '-') ?></strong></div>
  <?php if ($isReceipt): ?>
    <div class="line"><span>Returned</span><strong><?= h(fmt_date($r['returned_at'])) ?></strong></div>
    <div class="line"><span>Fine</span><strong><?= h(peso($r['fine_amount'])) ?><?= ((float) $r['fine_amount'] > 0) ? ($r['fine_paid_at'] ? ' (paid ' . h(fmt_date($r['fine_paid_at'])) . ')' : ' (unpaid)') : '' ?></strong></div>
  <?php else: ?>
    <div class="big">Return by <?= h(fmt_date($r['due_date'])) ?></div>
    <div class="sub">Strictly before 4:00 PM (library hours)</div>
  <?php endif; ?>
  <p class="no-print mt"><button class="btn-p" onclick="window.print()">Print</button> <a class="btn-s" href="librarian_home.php">Back</a></p>
</div>
<?php lib_footer();
