<?php
require __DIR__ . '/src/php/config/db.php';
$pdo = getDB();

echo "=== students TABLE ===\n";
$stmt = $pdo->query("DESCRIBE students");
foreach ($stmt as $r) {
    echo str_pad($r['Field'], 22) . $r['Type'] . "  " . $r['Null'] . "  Default=" . ($r['Default'] ?? 'NULL') . "\n";
}

echo "\n=== Current signup flow in auth.php ===\n";
$content = file_get_contents(__DIR__ . '/src/php/includes/auth.php');
// Extract studentRegister function
preg_match('/function studentRegister.*?(?=function|\Z)/s', $content, $m);
if (!empty($m[0])) {
    echo $m[0] . "\n";
}

echo "\n=== Current login flow in auth.php ===\n";
preg_match('/function studentLogin.*?(?=function|\Z)/s', $content, $m);
if (!empty($m[0])) {
    echo $m[0] . "\n";
}
