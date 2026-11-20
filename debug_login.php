<?php
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

$rows = $pdo->query('SELECT id, id_no, role, password_hash FROM users')->fetchAll();
echo "Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo "Users in table: " . count($rows) . "\n\n";

foreach ($rows as $r) {
    $try = ($r['role'] === 'librarian') ? 'librarian123' : 'student123';
    echo "ID: [" . $r['id_no'] . "]  length=" . strlen($r['id_no']) . "\n";
    echo "Role: [" . $r['role'] . "]\n";
    echo "Hash length: " . strlen($r['password_hash']) . " (should be 60)\n";
    echo "Password '$try' matches: " . (password_verify($try, $r['password_hash']) ? 'YES' : 'NO') . "\n\n";
}