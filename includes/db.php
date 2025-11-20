<?php
$host = 'localhost';
$db = 'nextboost_agencia_db';
$user = 'nextboost_admincrm';
$pass = 's^}-=3OOah5k';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>