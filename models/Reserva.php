<?php
// models/Reserva.php
// Regla de esta capa: solo SQL y PHP puro aquí. Nada de $_POST, $_SESSION, ni json_encode.
// Si algún día cambian de MySQL a otro motor, este es el ÚNICO archivo que debería cambiar.


class Reserva {
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    public function obtenerOcupadosPorServicio(int $idServicio, string $inicioRango, string $finRango) : array {
        /* Este metodo no calcula horarios libres (eso lo hace el controlador). Solo 
        trae de la base de datos las reservas que ya existen para un servicio,
        dentro de una rango de fechas, para que el controlador sepa qué horas evitar. */

        $stmt = $this->pdo->prepare(
            "SELECT fecha_hora_inicio, fecha_hora_fin
            FROM Reservas
            WHERE id_servicio = :id_servicio
            AND estado_actual <> 'cancelado'
            AND fecha_hora_inicio < :fin_rango
            AND fecha_hora_fin > :inicio_rango
            ORDER BY fecha_hora_inicio ASC"
        );

        $stmt->execute([
            'id_servicio' => $idServicio,
            'inicio_rango' => $inicioRango,
            'fin_rango' => $finRango,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(int $idUsuario, int $idServicio, string $fechaHoraInicio, string $fechaHoraFin): int {
    
    $stmt = $this->pdo->prepare(
        "INSERT INTO Reservas (id_usuario, id_servicio, fecha_hora_inicio, fecha_hora_fin, estado_actual, creado_en)
        VALUES (:id_usuario, :id_servicio, :inicio, :fin, 'pendiente', NOW())"
    );

    $stmt->execute([
        'id_usuario' => $idUsuario,
        'id_servicio' => $idServicio,
        'inicio' => $fechaHoraInicio,
        'fin' => $fechaHoraFin,
    ]);

    return (int) $this->pdo->lastInsertId();
    
    }

    public function listarPorUsuario(int $idUsuario): array {


        $stmt = $this->pdo->prepare(
            "SELECT r.id_reserva, r.id_servicio, s.nombre AS nombre_servicio,
                    r.fecha_hora_inicio, r.fecha_hora_fin, r.estado_actual
            FROM Reservas r
            INNER JOIN Servicios s ON s.id_servicio = r.id_servicio
            WHERE r.id_usuario = :id_usuario
            ORDER BY r.fecha_hora_inicio ASC"
        );

        $stmt->execute(['id_usuario' => $idUsuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

}