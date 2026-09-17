<?php
// middleware/AuthMiddleware.php
// Se incluye AL PRINCIPIO de cualquier endpoint que necesite proteger.
// Si falla, corta la ejecución con exit — el resto del archivo nunca se llega a correr.

function requerirSesion(): int {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['id_usuario'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
    return $_SESSION['id_usuario'];
}

function requerirRol(string $rolEsperado, PDO $pdo): void {
    requerirSesion();

    $stmt = $pdo->prepare("SELECT nombre_rol FROM Roles WHERE id_rol = :id");
    $stmt->execute(['id' => $_SESSION['id_rol']]);
    $rol = $stmt->fetchColumn();

    if ($rol !== $rolEsperado) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No tienes permiso para esta acción']);
        exit;
    }
}