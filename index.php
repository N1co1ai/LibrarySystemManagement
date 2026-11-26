<?php
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['user'])) {
    header('Location: ' . home_for($_SESSION['user']['role']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>UC South Library</title>
  <link rel="stylesheet" href="select.css">
</head>
<body>
  <div class="frame">
    <main class="panel">
      <?php include __DIR__ . '/logo.php'; ?>

      <div class="actions">
        <a href="student_login.php">STUDENT</a>
        <a href="librarian_login.php">LIBRARIAN</a>
      </div>
    </main>
  </div>
</body>
</html>