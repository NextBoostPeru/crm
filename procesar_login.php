<?php
session_start();
require_once 'includes/db.php';

$email = strtolower(trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    header("Location: /index.php?error=1", true, 303);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

$passwordValido = false;

if ($usuario) {
    // Soporta contraseñas en texto plano, password_hash y legacy md5/sha1
    if (!empty($usuario['password'])) {
        $hashGuardado = (string) $usuario['password'];

        if (password_verify($password, $hashGuardado)) {
            $passwordValido = true;
        } elseif (hash_equals($hashGuardado, $password)) {
            $passwordValido = true;
        } elseif (hash_equals($hashGuardado, md5($password))) {
            $passwordValido = true;
        } elseif (hash_equals($hashGuardado, sha1($password))) {
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
        header("Location: /admin/dashboard.php", true, 303);
    } else {
        header("Location: /colaborador/dashboard.php", true, 303);
    }
    exit;
}

header("Location: /index.php?error=1", true, 303);
exit;
