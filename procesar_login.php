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

/**
 * Verifica la contraseña del usuario contemplando hash modernos y heredados.
 */
$passwordValido = false;

if ($usuario && !empty($usuario['password'])) {
    $hashGuardado = trim((string) $usuario['password']);

    $passwordValido = password_verify($password, $hashGuardado)
        || hash_equals($hashGuardado, $password)
        || hash_equals($hashGuardado, md5($password))
        || hash_equals($hashGuardado, sha1($password))
        || hash_equals($hashGuardado, hash('sha256', $password))
        || hash_equals($hashGuardado, hash('sha512', $password));
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
