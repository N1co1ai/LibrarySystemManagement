<?php
require_once __DIR__ . '/auth.php';

// Librarian sign up needs this code (the library gives it to its staff).
// CHANGE IT, so not just anyone can create a librarian account.
const STAFF_ACCESS_CODE = '141';

// Already logged in? Go to the homepage.
if (!empty($_SESSION['user'])) {
    header('Location: ' . home_for($_SESSION['user']['role']));
    exit;
}

// Which tab is selected: from the form, or from ?role=student|librarian
$role        = (($_POST['role'] ?? $_GET['role'] ?? 'student') === 'librarian') ? 'librarian' : 'student';
$isLibrarian = ($role === 'librarian');
$idLabel     = $isLibrarian ? 'Staff ID' : 'Student ID';
$loginPage   = login_for($role);

$error = '';
$old   = ['full_name' => '', 'id_no' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['id_no']     = trim($_POST['id_no'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm          = $_POST['confirm'] ?? '';
    $code             = trim($_POST['staff_code'] ?? '');

    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($old['full_name'] === '' || $old['id_no'] === '' || $old['email'] === ''
              || $password === '' || $confirm === '' || ($isLibrarian && $code === '')) {
        $error = 'Please fill in all fields.';                                   // empty fields
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';                          // invalid email
    } elseif (strlen($old['full_name']) > 100 || strlen($old['email']) > 120) {
        $error = 'Your name or email is too long.';
    } elseif (!preg_match('/^[A-Za-z0-9-]{4,30}$/', $old['id_no'])) {
        $error = "Your $idLabel can only use letters, numbers, and dashes (4 to 30 characters).";
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';                                      // password mismatch
    } elseif ($isLibrarian && !hash_equals(STAFF_ACCESS_CODE, $code)) {
        $error = 'Invalid staff access code.';                                   // blocks fake librarians
    } else {
        $check = $pdo->prepare('SELECT 1 FROM users WHERE id_no = ? OR email = ? LIMIT 1');
        $check->execute([$old['id_no'], $old['email']]);

        if ($check->fetchColumn()) {
            $error = 'An account with this ID or email already exists.';         // existing account
        } else {
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO users (id_no, full_name, email, password_hash, role)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $old['id_no'], $old['full_name'], $old['email'],
                    password_hash($password, PASSWORD_DEFAULT), $role,
                ]);
                header('Location: ' . $loginPage . '?registered=1');
                exit;
            } catch (PDOException $e) {
                $error = ($e->getCode() === '23000')
                    ? 'An account with this ID or email already exists.'
                    : 'Could not create the account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign up | UC South Library</title>
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <div class="split">
    <aside class="brand">
      <?php include __DIR__ . '/logo.php'; ?>
      <div class="join">
        <h2>Join the South Campus Academic Community</h2>
        <ul>
          <li>Browse and search the book catalog</li>
          <li>Borrow, return, and track due dates</li>
          <li>Librarians manage books, fines, and reports</li>
        </ul>
      </div>
    </aside>
  
    <main class="side">
      <div class="card wide">
        <a class="back" href="index.php" aria-label="Back to role selection">&larr;</a>

        <div class="head">
          <span class="badge">UC</span>
          <h1>Create an Account</h1>
          <p class="role" id="role-sub"><?= h($isLibrarian ? 'Librarian' : 'Student') ?> Sign up</p>
        </div>

        <?php if ($error !== ''): ?>
          <div class="error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off" novalidate>
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

          <div class="roles" role="radiogroup" aria-label="Account type">
            <label class="role-opt">
              <input type="radio" name="role" value="student" <?= $isLibrarian ? '' : 'checked' ?>>
              <span>Student</span>
            </label>
            <label class="role-opt">
              <input type="radio" name="role" value="librarian" <?= $isLibrarian ? 'checked' : '' ?>>
              <span>Librarian</span>
            </label>
          </div>

          <label for="full_name">Full name</label>
          <input type="text" id="full_name" name="full_name" maxlength="100"
                 value="<?= h($old['full_name']) ?>" placeholder="e.g. Juan Dela Cruz" required autofocus>

          <label for="id_no" id="id-label"><?= h($idLabel) ?></label>
          <input type="text" id="id_no" name="id_no" maxlength="30"
                 value="<?= h($old['id_no']) ?>"
                 placeholder="<?= h($isLibrarian ? 'e.g. LIB-002' : 'e.g. 2024-0012') ?>" required>

          <label for="email">Email</label>
          <input type="text" id="email" name="email" maxlength="120"
                 value="<?= h($old['email']) ?>" placeholder="name@example.com" required>

          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="At least 8 characters" required>

          <label for="confirm">Confirm password</label>
          <input type="password" id="confirm" name="confirm" placeholder="Re-enter your password" required>

          <div id="staff-field" <?= $isLibrarian ? '' : 'hidden' ?>>
            <label for="staff_code">Staff access code</label>
            <input type="password" id="staff_code" name="staff_code" placeholder="Given by the library">
            <p class="hint">Only library staff can create a librarian account.</p>
          </div>

          <button type="submit" class="btn" style="margin-top:20px">Create Account</button>
        </form>

        <div class="row">
          <span>Already have an account?</span>
          <a href="<?= h($loginPage) ?>" id="login-link">Log in</a>
        </div>
      </div>
    </main>
  </div>

  <script>
    // Switch the form when the Student / Librarian tab changes
    (function () {
      var radios = document.querySelectorAll('input[name="role"]');
      var staff  = document.getElementById('staff-field');
      function apply(role) {
        var lib = (role === 'librarian');
        staff.hidden = !lib;
        document.getElementById('id-label').textContent = lib ? 'Staff ID' : 'Student ID';
        document.getElementById('id_no').placeholder = lib ? 'e.g. LIB-002' : 'e.g. 2024-0012';
        document.getElementById('role-sub').textContent = lib ? 'Librarian Sign up' : 'Student Sign up';
        document.getElementById('login-link').href = lib ? 'librarian_login.php' : 'student_login.php';
      }
      radios.forEach(function (r) {
        r.addEventListener('change', function () { apply(r.value); });
      });
    })();
  </script>
</body>
</html>