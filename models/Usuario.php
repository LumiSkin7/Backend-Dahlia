<?php
// models/Usuario.php
// Regla de esta capa: solo SQL y PHP puro aquí. Nada de $_POST, $_SESSION, ni json_encode.
// Si algún día cambian de MySQL a otro motor, este es el ÚNICO archivo que debería cambiar.

class Usuario {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function buscarPorCorreo(string $correo): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM Usuarios WHERE correo = :correo");
        $stmt->execute(['correo' => $correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        return $usuario ?: null;
    }

    public function crear(string $nombre, string $correo, string $passwordHash, int $idRol): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO Usuarios (nombre, correo, password_hash, id_rol, creado_en)
             VALUES (:nombre, :correo, :hash, :rol, NOW())"
        );
        $stmt->execute([
            'nombre' => $nombre,
            'correo' => $correo,
            'hash' => $passwordHash,
            'rol' => $idRol,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}