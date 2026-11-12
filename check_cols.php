<?php
require_once 'c:/xampp/htdocs/Bestway_update/config/database.php';
$stmt = $pdo->query("SELECT * FROM opening_stock_logs WHERE product_id = 160");
echo "opening_stock_logs for 160:\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query("SELECT * FROM product_batches WHERE product_id = 160");
echo "product_batches for 160:\n";
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
