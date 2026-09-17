<?php

class Servicio {
    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function listarActivos(): array {

        $stmt = $this->pdo->prepare(
            "SELECT id_servicio, nombre, duracion_min, precio, activo
            FROM Servicios
            WHERE activo = 1
            ORDER BY nombre ASC"
        );


        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    public function listarTodos(): array {

        $stmt = $this->pdo->prepare(
            "SELECT id_servicio, nombre, duracion_min, precio, activo
            FROM Servicios
            ORDER BY nombre ASC"
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    public function buscarPorId(int $idServicio): ?array{

        $stmt = $this->pdo->prepare(
            "SELECT id_servicio, nombre duracion_min, precio, activo
            FROM Servicios
            WHERE id_servicio= :id"
        );

        $stmt->execute(['id' => $idServicio]);

        $servicio = $stmt->fetch(PDO::FETCH_ASSOC);

        return $servicio ?: null;

    }
    
}