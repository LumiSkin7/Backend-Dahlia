<?php
// controllers/AuthController.php
// Regla de esta capa: recibe la petición HTTP, valida lo básico, le pide al Modelo
// que haga el trabajo con la BD, y responde en JSON. No escribe SQL directamente.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/Auditoria.php';

header('Content-Type: application/json');

$usuarioModel = new Usuario($pdo);
$accion = $_GET['accion'] ?? '';

if ($accion === 'registrar') {
    registrar($usuarioModel, $pdo);
} elseif ($accion === 'login') {
    login($usuarioModel, $pdo);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Acción no válida']);
}
function registrar(Usuario $usuarioModel, PDO $pdo): void {
    $datos = json_decode(file_get_contents('php://input'), true);

    $nombre = trim($datos['nombre'] ?? '');
    $correo = trim($datos['correo'] ?? '');
    $password = $datos['password'] ?? '';
    $idRol = $datos['id_rol'] ?? 1; // 1 = cliente por defecto

    if (!$nombre || !$correo || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios']);
        return;
    }

    if ($usuarioModel->buscarPorCorreo($correo)) {
        http_response_code(409);
        echo json_encode(['error' => 'Ese correo ya está registrado']);
        return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $idUsuario = $usuarioModel->crear($nombre, $correo, $hash, (int) $idRol);

    registrarAuditoria($pdo, $idUsuario, 'registro', 'Usuarios', $idUsuario);

    echo json_encode(['mensaje' => 'Usuario registrado correctamente', 'id_usuario' => $idUsuario]);
}

function login(Usuario $usuarioModel, PDO $pdo): void {
    $datos = json_decode(file_get_contents('php://input'), true);

    $correo = trim($datos['correo'] ?? '');
    $password = $datos['password'] ?? '';

    if (!$correo || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Correo y contraseña son obligatorios']);
        return;
    }

    $usuario = $usuarioModel->buscarPorCorreo($correo);

    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales inválidas']);
        return;
    }

    session_start();
    $_SESSION['id_usuario'] = $usuario['id_usuario'];
    $_SESSION['id_rol'] = $usuario['id_rol'];

    registrarAuditoria($pdo, $usuario['id_usuario'], 'login', 'Usuarios', $usuario['id_usuario']);

    echo json_encode([
        'mensaje' => 'Sesión iniciada',
        'nombre' => $usuario['nombre'],
        'id_rol' => $usuario['id_rol'],
    ]);
}