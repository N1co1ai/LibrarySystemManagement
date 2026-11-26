<?php
require_once __DIR__ . '/layout.php';
$me = require_role('librarian');

/* ---------- Save an edit / delete a student ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);
    $back   = 'students.php';

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash_set('error', 'Your session expired. Please try again.');
    } elseif ($action === 'delete') {
        $n = $pdo->prepare("SELECT (SELECT COUNT(*) FROM borrow_records WHERE user_id = ?) + (SELECT COUNT(*) FROM reservations WHERE user_id = ?)");
        $n->execute([$id, $id]);
        if ((int) $n->fetchColumn() > 0) {
            flash_set('error', 'This student has borrowing or reservation records, so the account cannot be deleted.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$id]);
            flash_set('success', 'Student account deleted.');
        }
    } elseif ($action === 'save') {
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $grade = trim($_POST['grade_section'] ?? '');
        $newPw = $_POST['new_password'] ?? '';
        $back  = 'students.php?edit=' . $id;

        if ($name === '' || strlen($name) > 100) {
            flash_set('error', 'Please enter the student\'s full name (up to 100 characters).');
        } elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120)) {
            flash_set('error', 'Please enter a valid email address.');
        } elseif (strlen($grade) > 40) {
            flash_set('error', 'Grade / section is too long.');
        } elseif ($newPw !== '' && strlen($newPw) < 8) {
            flash_set('error', 'A new password must be at least 8 characters.');
        } else {
            try {
                $sql    = "UPDATE users SET full_name = ?, email = ?, grade_section = ?";
                $params = [$name, $email === '' ? null : $email, $grade === '' ? null : $grade];
                if ($newPw !== '') { $sql .= ', password_hash = ?'; $params[] = password_hash($newPw, PASSWORD_DEFAULT); }
                $sql .= " WHERE id = ? AND role = 'student'";
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
                flash_set('success', 'Student account updated.');
                $back = 'students.php';
            } catch (PDOException $e) {
                flash_set('error', ($e->getCode() === '23000') ? 'That email is already used by another account.' : 'Could not save the changes.');
            }
        }
    }
    redirect($back);
}

/* ---------- Filters ---------- */
$q     = trim($_GET['q'] ?? '');
$grade = trim($_GET['grade'] ?? '');
$where = "u.role = 'student'";
$params = [];
if ($q !== '') {
    $where .= ' AND (u.full_name LIKE ? OR u.id_no LIKE ? OR u.grade_section LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($grade !== '') { $where .= ' AND u.grade_section = ?'; $params[] = $grade; }

$select = "SELECT u.id, u.id_no, u.full_name, u.email, u.grade_section,
             (SELECT COUNT(*) FROM borrow_records r WHERE r.user_id = u.id AND r.returned_at IS NULL) AS out_count,
             (SELECT COUNT(*) FROM borrow_records r WHERE r.user_id = u.id AND r.returned_at IS NULL AND r.due_date < CURDATE()) AS overdue_count
           FROM users u WHERE $where ORDER BY u.full_name";

/* ---------- Export the list as CSV ---------- */
if (isset($_GET['export'])) {
    $st = $pdo->prepare($select);
    $st->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student ID', 'Name', 'Email', 'Grade/Section', 'Books borrowed', 'Overdue']);
    foreach ($st->fetchAll() as $s) {
        fputcsv($out, [$s['id_no'], $s['full_name'], $s['email'], $s['grade_section'], $s['out_count'], $s['overdue_count']]);
    }
    fclose($out);
    exit;
}

$cnt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$cnt->execute($params);
$total = (int) $cnt->fetchColumn();
$page  = min(page_number(), max(1, (int) ceil($total / PER_PAGE)));
$st = $pdo->prepare($select . ' LIMIT ' . (int) PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));
$st->execute($params);
$students = $st->fetchAll();

$grades = $pdo->query("SELECT DISTINCT grade_section FROM users WHERE role = 'student' AND grade_section IS NOT NULL AND grade_section <> '' ORDER BY grade_section")->fetchAll(PDO::FETCH_COLUMN);

$edit = null;
if (isset($_GET['edit'])) {
    $e = $pdo->prepare("SELECT id, id_no, full_name, email, grade_section FROM users WHERE id = ? AND role = 'student'");
    $e->execute([(int) $_GET['edit']]);
    $edit = $e->fetch() ?: null;
}

lib_header('Student Accounts', 'students', true);
?>
<div class="page-title">
  <div><h1>Student Accounts &amp; Membership</h1><p>Manage patron profiles and check borrower quotas.</p></div>
  <a class="btn-s" href="?<?= h(http_build_query(array_merge($_GET, ['export' => 1]))) ?>">Export List</a>
</div>

<?php if ($edit): ?>
<section class="panel mt">
  <div class="panel-head"><h2>Edit student: <?= h($edit['id_no']) ?></h2></div>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
    <div class="grid2">
      <div><label class="f" for="e_name">Full name</label><input class="in" id="e_name" name="full_name" maxlength="100" value="<?= h($edit['full_name']) ?>" required></div>
      <div><label class="f" for="e_grade">Grade / Section</label><input class="in" id="e_grade" name="grade_section" maxlength="40" value="<?= h($edit['grade_section']) ?>" placeholder="e.g. Grade 11 - STEM A"></div>
      <div><label class="f" for="e_email">Email</label><input class="in" id="e_email" name="email" maxlength="120" value="<?= h($edit['email']) ?>"></div>
      <div><label class="f" for="e_pw">New password <span class="muted">(leave empty to keep)</span></label><input class="in" id="e_pw" name="new_password" type="password" placeholder="At least 8 characters"></div>
    </div>
    <p class="mt"><button class="btn-p" type="submit">Save changes</button> <a class="btn-s" href="students.php">Cancel</a></p>
  </form>
</section>
<?php endif; ?>

<section class="panel">
  <form class="filters" method="get">
    <input class="in grow" name="q" value="<?= h($q) ?>" placeholder="Search by name, ID or section...">
    <select class="in" name="grade" onchange="this.form.submit()">
      <option value="">All Grades</option>
      <?php foreach ($grades as $g): ?><option<?= $g === $grade ? ' selected' : '' ?>><?= h($g) ?></option><?php endforeach; ?>
    </select>
    <button class="btn-p" type="submit">Search</button>
  </form>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Photo</th><th>Student ID</th><th>Name</th><th>Grade/Section</th><th>Books Borrowed</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$students): ?><tr><td colspan="6" class="empty">No students found.</td></tr><?php endif; ?>
      <?php foreach ($students as $s):
          $n = (int) $s['out_count']; $o = (int) $s['overdue_count'];
          $label = $n . ' Book' . ($n === 1 ? '' : 's') . ($o > 0 ? " ($o Overdue)" : '');
          $cls = $o > 0 ? 'red' : ($n > 0 ? 'blue' : 'gray'); ?>
        <tr>
          <td><span class="avatar"><?= h(strtoupper(substr($s['full_name'], 0, 1))) ?></span></td>
          <td><?= h($s['id_no']) ?></td>
          <td><strong><?= h($s['full_name']) ?></strong></td>
          <td><?= h($s['grade_section'] ?: '-') ?></td>
          <td><span class="pill <?= $cls ?>"><?= h($label) ?></span></td>
          <td class="acts">
            <a class="btn-l" href="students.php?edit=<?= (int) $s['id'] ?>">Edit</a>
            <form class="inline" method="post" onsubmit="return confirm('Delete this student account?');">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
              <button class="btn-l del" type="submit">Delete</button>
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
