<?php
require_once __DIR__ . '/auth.php';

/**
 * Shared login screen + handler for both roles.
 * student_login.php and librarian_login.php just call this.
 */
function render_login(PDO $pdo, string $role): void {
    $isLibrarian = ($role === 'librarian');
    $roleLabel   = $isLibrarian ? 'Librarian' : 'Student';
    $otherLabel  = $isLibrarian ? 'student' : 'librarian';
    $otherPage   = $isLibrarian ? 'student_login.php' : 'librarian_login.php';
    $idLabel     = $isLibrarian ? 'Staff ID' : 'Student ID';
    $idExample   = $isLibrarian ? 'e.g. LIB-001' : 'e.g. 2024-0012';
    $signupPage  = 'signup.php?role=' . $role;

    // Demo box under the form. Set to false before real use.
    $showDemo = true;
    $demoId   = $isLibrarian ? 'LIB-001' : '2024-0012';
    $demoPass = $isLibrarian ? 'librarian123' : 'student123';

    $error      = '';
    $idValue    = '';
    $registered = ($_SERVER['REQUEST_METHOD'] !== 'POST') && isset($_GET['registered']);

    // Already logged in with this role? Go straight to the homepage.
    if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === $role) {
        header('Location: ' . home_for($role));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $idValue  = trim($_POST['id_no'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!csrf_valid($_POST['csrf'] ?? null)) {
            $error = 'Your session expired. Please try again.';
        } elseif ($idValue === '' || $password === '') {
            $error = "Please enter your $idLabel and password.";
        } elseif (attempt_login($pdo, $idValue, $password, $role)) {
            header('Location: ' . home_for($role));
            exit;
        } else {
            // Same message for wrong ID, wrong password, or wrong role
            $error = "Invalid $idLabel or password.";
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($roleLabel) ?> Log in | UC South Library</title>
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <div class="split">
    <aside class="brand">
      <?php include __DIR__ . '/logo.php'; ?>
    </aside>

    <main class="side">
      <div class="card">
        <a class="back" href="index.php" aria-label="Back to role selection">&larr;</a>

        <div class="head">
          <span class="badge">UC</span>
          <h1>University of Cebu South Library</h1>
          <p class="role"><?= h($roleLabel) ?> Log in</p>
        </div>

        <?php if ($registered): ?>
          <div class="success" role="status">Account created. You can log in now.</div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
          <div class="error" role="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off" novalidate>
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

          <label for="id_no"><?= h($idLabel) ?></label>
          <input type="text" id="id_no" name="id_no"
                 value="<?= h($idValue) ?>" placeholder="<?= h($idExample) ?>" required autofocus>

          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="Enter your password" required>

          <div class="row">
            <a href="<?= h($signupPage) ?>">Sign up</a>
            <a href="<?= h($otherPage) ?>">I am a <?= h($otherLabel) ?></a>
          </div>

          <button type="submit" class="btn">Log in</button>
        </form>

        <?php if ($showDemo): ?>
          <div class="demo">
            <strong>Demo account</strong>
            <p><?= h($idLabel) ?>: <b><?= h($demoId) ?></b><br>Password: <b><?= h($demoPass) ?></b></p>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</body>
</html>
    <?php
}