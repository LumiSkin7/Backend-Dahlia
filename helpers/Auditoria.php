<?php
// helpers/Auditoria.php
// No es un modelo de negocio (no representa una entidad como Usuario o Reserva),
// por eso vive en helpers/ en vez de models/ — es una utilidad transversal.

function registrarAuditoria(PDO $pdo, ?int $idUsuario, string $accion, string $tabla, ?int $idRegistro): void {
    $stmt = $pdo->prepare(
        "INSERT INTO Logs_Auditoria (id_usuario, accion, tabla_afectada, id_registro_afectado, ejecutado_en)
         VALUES (:usuario, :accion, :tabla, :registro, NOW())"
    );
    $stmt->execute([
        'usuario' => $idUsuario,
        'accion' => $accion,
        'tabla' => $tabla,
        'registro' => $idRegistro,
    ]);
}