<?php
require_once __DIR__ . '/config.php';

$demo = [
    ['LIB-001',   'Fritzie Mae Son', 'librarian', 'librarian123'],
    ['2024-0012', 'TJ Flores',       'student',   'student123'],
];

$upsert = $pdo->prepare(
    'INSERT INTO users (id_no, full_name, role, password_hash)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       full_name = VALUES(full_name),
       role = VALUES(role),
       password_hash = VALUES(password_hash)'
);

header('Content-Type: text/plain; charset=utf-8');
foreach ($demo as [$idNo, $name, $role, $plain]) {
    $upsert->execute([$idNo, $name, $role, password_hash($plain, PASSWORD_DEFAULT)]);
    echo "Saved $role: $idNo\n";
}
echo "\nDone. Now delete setup.php.\n";