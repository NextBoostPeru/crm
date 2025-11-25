<?php
session_start();
require_once 'includes/db.php';

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header("Location: login.php?error=1");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

$passwordValido = false;

if ($usuario) {
    // Soporta contraseñas en texto plano y con password_hash para compatibilidad
    if (!empty($usuario['password'])) {
        if (password_verify($password, $usuario['password'])) {
            $passwordValido = true;
        } elseif (hash_equals((string) $usuario['password'], $password)) {
            $passwordValido = true;
        }
    }
}

if ($usuario && $passwordValido) {
    session_regenerate_id(true);
    $_SESSION['usuario'] = $usuario['nombre'];
    $_SESSION['rol'] = $usuario['rol'];
    $_SESSION['usuario_id'] = $usuario['id'];

    if ($usuario['rol'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: colaborador/dashboard.php");
    }
    exit;
}

header("Location: login.php?error=1");
exit;