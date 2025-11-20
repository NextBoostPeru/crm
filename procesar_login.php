<?php
session_start();
require_once 'includes/db.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario && password_verify($password, $usuario['password'])) {
    $_SESSION['usuario'] = $usuario['nombre'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['usuario_id'] = $usuario['id'];

    if ($usuario['rol'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: colaborador/dashboard.php");
    }
    exit;
} else {
    header("Location: login.php?error=1");
}