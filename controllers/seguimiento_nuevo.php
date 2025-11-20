<?php
require_once '../includes/db.php';
session_start();

$cliente_id = $_POST['cliente_id'];
$fecha = $_POST['fecha'];
$nota = $_POST['nota'];
$usuario_id = $_SESSION['usuario_id'];

$stmt = $pdo->prepare("INSERT INTO seguimientos (cliente_id, fecha, nota, usuario_id)
                       VALUES (?, ?, ?, ?)");
$ok = $stmt->execute([$cliente_id, $fecha, $nota, $usuario_id]);

echo json_encode(['success' => $ok]);