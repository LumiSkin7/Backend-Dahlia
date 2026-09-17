<?php

// Config/db.php
// Todos los controladores incluyen este archivo para obtener $pdo.

$host = '127.0.0.1';
$port = '3306';
$dbname = 'citas_estetica';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO:: ERRMODE_EXCEPTION]
    );

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'No se pudo conectar a la base de datos']));
}