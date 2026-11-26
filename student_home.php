<?php
require_once __DIR__ . '/auth.php';
$user = require_role('student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Homepage | UC Library</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="nav">
    <span class="brand"><span class="logo-mark sm">UC</span> UC Library</span>
    <nav>
      <a class="active" href="student_home.php">Home</a>
      <span class="soon" title="Coming soon">Catalog</span>
      <span class="soon" title="Coming soon">MyBooks</span>
      <span class="soon" title="Coming soon">Overdue</span>
    </nav>
    <a class="logout" href="logout.php">Logout</a>
  </header>

  <main class="page">
    <section class="banner">
      <h1>Welcome, <?= h($user['name']) ?></h1>
      <p>Search for books, borrow, and return them here.</p>
      <p class="small">Student ID: <?= h($user['id_no']) ?></p>
    </section>
    <p class="muted">Student Homepage: Catalog, MyBooks, and Overdue pages will be added next.</p>
  </main>
</body>
</html>
