<?php
session_start();
require_once 'includes/db.php';

$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$emailInput = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$passwordInput = filter_input(INPUT_POST, 'password');

$email = strtolower(trim($emailInput ?? ''));
$password = trim($passwordInput ?? '');

// Construir base path para redirecciones seguras en subcarpetas
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$basePath = ($basePath === '.' || $basePath === '/') ? '' : $basePath;

$redirect = function (string $path, array $params = []) use ($basePath) {
    $query = $params ? '?' . http_build_query($params) : '';
    header('Location: ' . $basePath . '/' . ltrim($path, '/') . $query, true, 303);
    exit;
};

if (!$isPost) {
    $redirect('index.php', ['error' => 'credenciales']);
}

if ($email === '' || $password === '') {
    $redirect('index.php', ['error' => 'incompleto']);
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE LOWER(email) COLLATE utf8mb4_unicode_ci = ? LIMIT 1");
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
        $redirect('admin/dashboard.php', ['bienvenida' => 1]);
    }

    $redirect('colaborador/dashboard.php', ['bienvenida' => 1]);
}

$redirect('index.php', ['error' => 'credenciales']);
