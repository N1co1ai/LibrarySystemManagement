<?php
require_once __DIR__ . '/auth.php';
$user = require_role('librarian');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Librarian Homepage | UC Library</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="nav">
    <span class="brand"><span class="logo-mark sm">UC</span> UC Library</span>
    <nav>
      <a class="active" href="librarian_home.php">Home</a>
      <span class="soon" title="Coming soon">Student Account</span>
      <span class="soon" title="Coming soon">Borrow/Return</span>
      <span class="soon" title="Coming soon">Reservation</span>
      <span class="soon" title="Coming soon">Fines</span>
      <span class="soon" title="Coming soon">Reports</span>
    </nav>
    <a class="logout" href="logout.php">Logout</a>
  </header>

  <main class="page">
    <section class="banner">
      <h1>Welcome, <?= h($user['name']) ?></h1>
      <p>Librarian dashboard</p>
      <p class="small">Staff ID: <?= h($user['id_no']) ?></p>
    </section>
    <p class="muted">Librarian Homepage: dashboard statistics and desk operations will be added next.</p>
  </main>
</body>
</html>
