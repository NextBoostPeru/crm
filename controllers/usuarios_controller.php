<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../includes/db.php';
session_start();

if (($_SESSION['rol'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// GET: listado
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $usuarios = $pdo->query("SELECT id, nombre, email, rol, creado_en FROM usuarios ORDER BY id DESC")
                    ->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $usuarios]);
    exit;
}

// POST: crear / actualizar / eliminar
$action = $_POST['action'] ?? '';

if ($action === 'eliminar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
    $ok = $stmt->execute([$id]);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Usuario eliminado' : 'No se pudo eliminar']);
    exit;
}

$id       = (int)($_POST['id'] ?? 0);
$nombre   = trim($_POST['nombre'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$rol      = trim($_POST['rol'] ?? '');

if ($nombre === '' || $email === '' || $rol === '') {
    echo json_encode(['success' => false, 'message' => 'Completa todos los campos obligatorios']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Correo no válido']);
    exit;
}

// Validar duplicado de correo
$sqlDup = 'SELECT id FROM usuarios WHERE email = ?' . ($action === 'actualizar' ? ' AND id <> ?' : '');
$stmtDup = $pdo->prepare($sqlDup);
$paramsDup = [$email];
if ($action === 'actualizar') {
    $paramsDup[] = $id;
}
$stmtDup->execute($paramsDup);
if ($stmtDup->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ya existe un usuario con ese correo']);
    exit;
}

if ($action === 'crear') {
    if ($password === '') {
        echo json_encode(['success' => false, 'message' => 'Define una contraseña']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)');
    $ok = $stmt->execute([$nombre, $email, $hash, $rol]);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Usuario creado correctamente' : 'No se pudo crear']);
    exit;
}

if ($action === 'actualizar') {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE usuarios SET nombre = ?, email = ?, password = ?, rol = ? WHERE id = ?');
        $ok = $stmt->execute([$nombre, $email, $hash, $rol, $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?');
        $ok = $stmt->execute([$nombre, $email, $rol, $id]);
    }

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Usuario actualizado' : 'No se pudo actualizar']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
