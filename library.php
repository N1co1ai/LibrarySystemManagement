<?php
require_once __DIR__ . '/auth.php';

/* ===== Library rules: change them here ===== */
const LOAN_DAYS        = 7;    // default loan period at the desk (days)
const FINE_PER_DAY     = 5;    // pesos per day overdue
const MAX_BOOKS        = 3;    // books one student may hold at the same time
const RESERVATION_DAYS = 7;    // a reservation expires after this many days
const PER_PAGE         = 10;   // rows per page in the tables
const BOOK_CATEGORIES  = ['Filipiniana', 'Mathematics', 'Science', 'Engineering', 'Business',
                          'Computer Science', 'Philippine Literature', 'Reference', 'General'];

date_default_timezone_set('Asia/Manila');
$pdo->exec("SET time_zone = '+08:00'");     // keep MySQL dates the same as PHP dates

/* ===== Small helpers ===== */
function peso($amount): string { return '₱' . number_format((float) $amount, 2); }
function fmt_date($d): string  { return $d ? date('M j, Y', strtotime($d)) : '-'; }
function fmt_time($d): string  { return $d ? date('g:i A', strtotime($d)) : '-'; }
function redirect(string $url) { header('Location: ' . $url); exit; }

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function flash_set(string $type, string $msg, string $slot = 'main'): void {
    $_SESSION['flash'][$slot] = ['type' => $type, 'msg' => $msg];
}

function flash_show(string $slot = 'main'): void {
    if (empty($_SESSION['flash'][$slot])) { return; }
    $f = $_SESSION['flash'][$slot];
    unset($_SESSION['flash'][$slot]);
    echo '<div class="flash flash-' . h($f['type']) . '" role="status">' . h($f['msg']) . '</div>';
}

function page_number(): int { return max(1, (int) ($_GET['page'] ?? 1)); }

function pager(int $total, int $page, int $perPage = PER_PAGE): string {
    $pages = max(1, (int) ceil($total / $perPage));
    $from  = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
    $to    = min($total, $page * $perPage);
    $q     = $_GET;
    $link  = function (int $p, string $label, bool $on = false) use ($q) {
        $q['page'] = $p;
        return '<a class="pg' . ($on ? ' on' : '') . '" href="?' . h(http_build_query($q)) . '">' . h($label) . '</a>';
    };
    $out = '<div class="pager"><span>Showing ' . $from . ' to ' . $to . ' of ' . $total . '</span><span class="pgs">';
    $out .= $page > 1 ? $link($page - 1, 'Previous') : '<span class="pg off">Previous</span>';
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) { $out .= $link($p, (string) $p, $p === $page); }
    $out .= $page < $pages ? $link($page + 1, 'Next') : '<span class="pg off">Next</span>';
    return $out . '</span></div>';
}

/* ===== SQL pieces shared by the pages ===== */
// Fine right now: still-borrowed books accrue per day, returned books keep their fixed fine.
function sql_fine_expr(string $a = 'r'): string {
    return "CASE WHEN $a.returned_at IS NULL THEN GREATEST(DATEDIFF(CURDATE(), $a.due_date), 0) * " . FINE_PER_DAY
         . " ELSE $a.fine_amount END";
}
// A record that has an unpaid fine right now.
function sql_unpaid_cond(string $a = 'r'): string {
    return "(($a.returned_at IS NULL AND $a.due_date < CURDATE())"
         . " OR ($a.returned_at IS NOT NULL AND $a.fine_amount > 0 AND $a.fine_paid_at IS NULL))";
}
function loan_cols(): string {
    return 'r.id, r.user_id, r.borrowed_at, r.due_date, r.returned_at, r.fine_amount, r.fine_paid_at, r.reminded_at,
            DATEDIFF(CURDATE(), r.due_date) AS days_diff,
            CASE WHEN r.returned_at IS NULL THEN 0 ELSE GREATEST(DATEDIFF(DATE(r.returned_at), r.due_date), 0) END AS late_days,
            ' . sql_fine_expr('r') . ' AS fine_now,
            u.full_name, u.id_no, b.title, b.call_no, b.category, hd.full_name AS handler';
}
function loan_from(): string {
    return 'FROM borrow_records r
            JOIN users u ON u.id = r.user_id
            JOIN books b ON b.id = r.book_id
            LEFT JOIN users hd ON hd.id = r.handled_by';
}

// [action label, action colour, status label, status colour] for one loan row
function loan_status(array $r): array {
    $fine = (float) $r['fine_now'];
    if ($r['returned_at'] === null) {
        $d = (int) $r['days_diff'];
        if ($d > 0)   { return ['Overdue',  'red',   'Overdue (' . peso($fine) . ')', 'red']; }
        if ($d === 0) { return ['Borrowed', 'blue',  'Due Today',                    'amber']; }
        return ['Borrowed', 'blue', 'Normal', 'blue'];
    }
    if ($fine > 0 && $r['fine_paid_at'] === null) {
        return ['Returned', 'green', 'Fine ' . peso($fine) . ' unpaid', 'red'];
    }
    return ['Returned', 'green', 'Cleared', 'green'];
}

/* ===== Circulation rules ===== */

// Lend a book to a student. Returns [true|false, message].
function issue_loan(PDO $pdo, string $studentIdNo, string $callNo, int $librarianId): array {
    $studentIdNo = trim($studentIdNo);
    $callNo      = trim($callNo);
    if ($studentIdNo === '' || $callNo === '') {
        return [false, 'Please enter the Student ID and the Book ID.'];
    }
    try {
        $pdo->beginTransaction();

        $s = $pdo->prepare("SELECT id, full_name FROM users WHERE id_no = ? AND role = 'student' LIMIT 1");
        $s->execute([$studentIdNo]);
        $student = $s->fetch();
        if (!$student) { $pdo->rollBack(); return [false, "No student found with ID $studentIdNo."]; }

        $b = $pdo->prepare('SELECT id, title, copies_available FROM books WHERE call_no = ? LIMIT 1 FOR UPDATE');
        $b->execute([$callNo]);
        $book = $b->fetch();
        if (!$book) { $pdo->rollBack(); return [false, "No book found with ID $callNo."]; }
        if ((int) $book['copies_available'] < 1) {
            $pdo->rollBack();
            return [false, '"' . $book['title'] . '" has no available copies. You can reserve it instead.'];
        }

        $c = $pdo->prepare('SELECT COUNT(*) FROM borrow_records WHERE user_id = ? AND returned_at IS NULL');
        $c->execute([$student['id']]);
        if ((int) $c->fetchColumn() >= MAX_BOOKS) {
            $pdo->rollBack();
            return [false, $student['full_name'] . ' already has ' . MAX_BOOKS . ' books (the limit).'];
        }

        $d = $pdo->prepare('SELECT COUNT(*) FROM borrow_records WHERE user_id = ? AND book_id = ? AND returned_at IS NULL');
        $d->execute([$student['id'], $book['id']]);
        if ((int) $d->fetchColumn() > 0) {
            $pdo->rollBack();
            return [false, $student['full_name'] . ' already has this book.'];
        }

        $ins = $pdo->prepare('INSERT INTO borrow_records (user_id, book_id, borrowed_at, due_date, handled_by)
                              VALUES (?, ?, NOW(), DATE_ADD(CURDATE(), INTERVAL ' . (int) LOAN_DAYS . ' DAY), ?)');
        $ins->execute([$student['id'], $book['id'], $librarianId]);
        $recordId = (int) $pdo->lastInsertId();

        $pdo->prepare('UPDATE books SET copies_available = copies_available - 1 WHERE id = ?')->execute([$book['id']]);

        $due = $pdo->prepare('SELECT due_date FROM borrow_records WHERE id = ?');
        $due->execute([$recordId]);
        $pdo->commit();

        return [true, 'Success: "' . $book['title'] . '" borrowed by ' . $student['full_name']
                      . ' (Due: ' . fmt_date($due->fetchColumn()) . ')'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, 'Something went wrong. Please try again.'];
    }
}

// Close one borrow record (the book came back). Returns [true|false, message].
function return_loan_by_id(PDO $pdo, int $recordId, string $condition = 'Intact'): array {
    try {
        $pdo->beginTransaction();

        $q = $pdo->prepare('SELECT r.id, r.book_id, u.full_name, b.title
                            FROM borrow_records r
                            JOIN users u ON u.id = r.user_id
                            JOIN books b ON b.id = r.book_id
                            WHERE r.id = ? AND r.returned_at IS NULL FOR UPDATE');
        $q->execute([$recordId]);
        $loan = $q->fetch();
        if (!$loan) { $pdo->rollBack(); return [false, 'That book is not currently borrowed.']; }

        $cond = in_array($condition, ['Intact', 'Damaged'], true) ? $condition : 'Intact';
        $pdo->prepare('UPDATE borrow_records
                       SET returned_at = NOW(), return_condition = ?,
                           fine_amount = GREATEST(DATEDIFF(CURDATE(), due_date), 0) * ' . (int) FINE_PER_DAY . '
                       WHERE id = ?')->execute([$cond, $recordId]);
        $pdo->prepare('UPDATE books SET copies_available = LEAST(copies_available + 1, copies_total) WHERE id = ?')
            ->execute([$loan['book_id']]);

        $chk = $pdo->prepare('SELECT fine_amount, GREATEST(DATEDIFF(CURDATE(), due_date), 0) AS late FROM borrow_records WHERE id = ?');
        $chk->execute([$recordId]);
        $row = $chk->fetch();
        $pdo->commit();

        $msg = 'Success: "' . $loan['title'] . '" returned by ' . $loan['full_name'];
        $msg .= ((int) $row['late'] > 0)
              ? ' (Late: ' . $row['late'] . ' day(s), fine ' . peso($row['fine_amount']) . ')'
              : ' (On time). Book status updated to Available.';
        return [true, $msg];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        return [false, 'Something went wrong. Please try again.'];
    }
}

// Find the active loan by Book ID (+ optional Student ID) and close it.
function return_loan(PDO $pdo, string $callNo, string $studentIdNo, string $condition = 'Intact'): array {
    $callNo      = trim($callNo);
    $studentIdNo = trim($studentIdNo);
    if ($callNo === '') { return [false, 'Please enter the Book ID.']; }

    $sql    = 'SELECT r.id FROM borrow_records r
               JOIN books b ON b.id = r.book_id
               JOIN users u ON u.id = r.user_id
               WHERE b.call_no = ? AND r.returned_at IS NULL';
    $params = [$callNo];
    if ($studentIdNo !== '') { $sql .= ' AND u.id_no = ?'; $params[] = $studentIdNo; }

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);

    if (count($ids) === 0) { return [false, 'No active loan found for that book' . ($studentIdNo !== '' ? ' and student.' : '.')]; }
    if (count($ids) > 1)   { return [false, 'Several students have this book. Please also enter the Student ID.']; }
    return return_loan_by_id($pdo, (int) $ids[0], $condition);
}
