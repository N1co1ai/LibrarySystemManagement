<?php
require_once __DIR__ . '/library.php';

// Top of every librarian page. $active = home | students | circulation | reservations | fines | reports
function lib_header(string $title, string $active, bool $wide = true): void {
    $nav = [
        'home'         => ['librarian_home.php', 'Home'],
        'students'     => ['students.php',       'Student Account'],
        'circulation'  => ['circulation.php',    'Borrow/Return'],
        'reservations' => ['reservations.php',   'Reservation'],
        'fines'        => ['fines.php',          'Fines'],
        'reports'      => ['reports.php',        'Reports'],
    ];
    $name = $_SESSION['user']['name'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> | UC Library</title>
  <link rel="stylesheet" href="app.css">
</head>
<body>
  <header class="nav">
    <span class="brand"><span class="logo-sm">UC</span> UC Library</span>
    <nav>
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= h($href) ?>"<?= $key === $active ? ' class="active"' : '' ?>><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <span class="who"><?= h($name) ?></span>
    <a class="logout" href="logout.php">Logout</a>
  </header>
  <main class="page<?= $wide ? ' wide' : '' ?>">
    <?php flash_show('main'); ?>
<?php
}

function lib_footer(): void {
    ?>
  </main>
</body>
</html>
<?php
}
