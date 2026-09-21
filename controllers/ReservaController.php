<?php

/**
 * Control de Horarios y Zona Horaria
 * Se establece la zona horaria por defecto para asegurar la precisión de los cálculos de fechas.
 */
date_default_timezone_set('America/Bogota');

// Carga de dependencias necesarias (Base de datos y Modelos)
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Reserva.php';
require_once __DIR__ . '/../models/Servicio.php';

// Definición del formato de respuesta de la API
header('Content-Type: application/json');

/**
 * Constantes de Configuración del Negocio
 */
const HORA_APERTURA = '09:00:00'; // Hora de inicio de atención
const HORA_CIERRE = '19:00:00';   // Hora de fin de atención
const RANGO_MAXIMO_DIAS = 31;     // Límite de días permitidos para consultas de disponibilidad

/**
 * Asegura que exista una sesión activa sin provocar advertencias de PHP (PHP Notice)
 */
function asegurarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Instanciación de los modelos con la conexión PDO previa ($pdo provisto por db.php)
$reservaModel = new Reserva($pdo);
$servicioModel = new Servicio($pdo);

// Enrutador sencillo: Captura la acción enviada mediante parámetro GET
$accion = $_GET['accion'] ?? '';

/* ==========================================================================
   ENRUTADOR DE PETICIONES (Router Switch/If)
   ========================================================================== */
if ($accion === 'consultarDisponibilidad') {
    consultarDisponibilidad($reservaModel, $servicioModel);
} elseif ($accion === 'crearReserva') {
    crearReserva($reservaModel, $servicioModel);
} elseif ($accion === 'listarPorUsuario') {
    listarPorUsuario($reservaModel);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Acción no válida']);
}

/* ==========================================================================
   ENDPOINT 1: Consultar Disponibilidad de un Servicio
   Método: GET 
   Ejemplo: ReservaController.php?accion=consultarDisponibilidad&id_servicio=1&fecha_inicio=2026-09-18&fecha_fin=2026-09-20
   ========================================================================== */

/**
 * Consulta la disponibilidad de horarios de un servicio dentro de un rango de fechas.
 *
 * @param Reserva  $reservaModel  Instancia del modelo de Reservas.
 * @param Servicio $servicioModel Instancia del modelo de Servicios.
 */
function consultarDisponibilidad(Reserva $reservaModel, Servicio $servicioModel): void {

    // 1. Obtención y validación primaria de los parámetros de entrada
    $idServicio = $_GET['id_servicio'] ?? null;
    $fechaInicio = $_GET['fecha_inicio'] ?? null;
    $fechaFin = $_GET['fecha_fin'] ?? null;

    if (!$idServicio || !is_numeric($idServicio) || !$fechaInicio || !$fechaFin) {
        http_response_code(400);
        echo json_encode(['error' => 'Debe indicar id_servicio, fecha_inicio y fecha_fin']);
        return;
    }

    // 2. Conversión a objetos DateTime para operar fechas de forma segura
    $inicio = DateTime::createFromFormat('Y-m-d', $fechaInicio);
    $fin = DateTime::createFromFormat('Y-m-d', $fechaFin);

    // Validación de formato de fechas y coherencia lógica (inicio <= fin)
    if (!$inicio || !$fin || $inicio > $fin) {
        http_response_code(400);
        echo json_encode(['error' => 'Rango de fechas inválido (use el formato AAAA-MM-DD, con fecha_inicio menor o igual a fecha_fin)']);
        return;
    }

    // 3. Cálculo de la cantidad total de días solicitados
    $diasSolicitados = (int) $inicio->diff($fin)->days + 1;

    /* diff() calcula la diferencia entre dos fechas; -> days da esa diferencia en dias completos. 
    Se suma 1 porque pedir del dia 18 al dia 18 es "1 dia", no "0" */

    // Control de límites para prevenir consumo excesivo de memoria/servidor
    if ($diasSolicitados > RANGO_MAXIMO_DIAS) {
        http_response_code(400);
        echo json_encode(['error' => 'El rango no puede superar ' . RANGO_MAXIMO_DIAS . ' días']);
        return;
    }

    // 4. Verificación de existencia y estado del servicio
    $servicio = $servicioModel->buscarPorId((int) $idServicio);
    
    if (!$servicio || !$servicio['activo']) {
        http_response_code(404);
        echo json_encode(['error' => 'Servicio no encontrado o inactivo']);
        return;
    }

    $duracionMin = (int) $servicio['duracion_min'];

    // 5. Consulta de las reservas existentes dentro del rango para cruzar ocupación
    $ocupados = $reservaModel->obtenerOcupadosPorServicio(
        (int) $idServicio,
        $fechaInicio . ' 00:00:00',
        $fechaFin . ' 23:59:59'
    );

    // 6. Recorrido día a día para calcular los bloques disponibles
    $disponibilidad = [];
    $fechaActual = clone $inicio;

    while ($fechaActual <= $fin) {
        $fechaTexto = $fechaActual->format('Y-m-d');
        $disponibilidad[] = [
            'fecha' => $fechaTexto,
            'bloques' => generarBloquesLibresDelDia($fechaTexto, $duracionMin, $ocupados),
        ];
        $fechaActual->modify('+1 day');
    }

    // 7. Respuesta exitosa con la matriz de disponibilidad por día
    echo json_encode([
        'servicio' => $servicio['nombre'],
        'duracion_min' => $duracionMin,
        'disponibilidad' => $disponibilidad,
    ]);
}

/**
 * Función auxiliar que genera las franjas horarias libres para un día específico.
 *
 * @param string $fecha       Fecha a evaluar en formato 'Y-m-d'.
 * @param int    $duracionMin Duración del servicio en minutos.
 * @param array  $ocupados    Listado de reservas vigentes obtenidas de la base de datos.
 * @return array Lista de rangos de texto con los bloques libres (ej. ["09:00-09:30", ...]).
 */
function generarBloquesLibresDelDia(string $fecha, int $duracionMin, array $ocupados): array {
    $bloques = [];

    // Delimitación de los límites del día evaluado
    $horaActual = new DateTime("$fecha " . HORA_APERTURA);
    $horaCierre = new DateTime("$fecha " . HORA_CIERRE);
    $ahora = new DateTime(); // Para descartar horas que ya pasaron el día de hoy

    // Recorrido por bloques seguidos según la duración del servicio
    while (true) {
        $horaFinBloque = (clone $horaActual)->modify("+{$duracionMin} minutes");

        // Si el bloque excede el horario de cierre, detenemos la evaluación del día
        if ($horaFinBloque > $horaCierre) {
            break;
        }

        // Verificación si el bloque pertenece al pasado (comparado contra el momento actual)
        $yaPaso = $horaActual < $ahora;

        // Comprobación de cruces con reservas preexistentes (Algoritmo de traslape)
        $seCruzaConOcupado = false;
        foreach ($ocupados as $ocupado) {
            $ocupadoInicio = new DateTime($ocupado['fecha_hora_inicio']);
            $ocupadoFin = new DateTime($ocupado['fecha_hora_fin']);

            // Condición de solapamiento de rangos de tiempo
            if ($horaActual < $ocupadoFin && $horaFinBloque > $ocupadoInicio) {
                $seCruzaConOcupado = true;
                break;
            }
        }

        // Si el bloque está en el futuro y no tiene colisiones, se añade a las opciones libres
        if (!$yaPaso && !$seCruzaConOcupado) {
            $bloques[] = $horaActual->format('H:i') . '-' . $horaFinBloque->format('H:i');
        }

        // Avanzar el cursor a la siguiente franja horaria
        $horaActual->modify("+{$duracionMin} minutes");
    }

    return $bloques;
}

/* ==========================================================================
   ENDPOINT 2: Crear Reserva
   Método: POST
   Cuerpo JSON esperado: {"id_servicio": 1, "fecha_hora_inicio": "2026-09-18 10:00:00"} 
   ========================================================================== */

/**
 * Crea una nueva reserva para el usuario autenticado procesando datos vía JSON payload.
 *
 * @param Reserva  $reservaModel  Instancia del modelo de Reservas.
 * @param Servicio $servicioModel Instancia del modelo de Servicios.
 */
function crearReserva (Reserva $reservaModel, Servicio $servicioModel): void {
    $_SESSION['id_usuario'] = 1; // ID de usuario de prueba
    // 1. Verificación de Autenticación mediante sesión del usuario
    asegurarSesion();

    if (!isset($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Debe iniciar sesión para reservar']);
        return;
    }

    // 2. Lectura y decodificación del cuerpo de la petición (JSON Raw Body)
    $datos = json_decode(file_get_contents('php://input'), true);

    $idServicio = $datos['id_servicio'] ?? null;
    $fechaHoraInicio = $datos['fecha_hora_inicio'] ?? null;

    // Validación de campos obligatorios recibidos
    if (!$idServicio || !is_numeric($idServicio) || !$fechaHoraInicio) {
        http_response_code(400);
        echo json_encode(['error' => 'Debe indicar id_servicio y fecha_hora_inicio']);
        return;
    }
 
    // 3. Conversión de la fecha enviada
    $inicio = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHoraInicio);

    if (!$inicio) {
        http_response_code(400);
        echo json_encode(['error' => 'fecha_hora_inicio debe tener el formato AAAA-MM-DD HH:MM:SS']);
        return;
    }

    // Validacion para evitar reservas en el pasado
    if ($inicio < new DateTime()) {
        http_response_code(400);
        echo json_encode(['error' => 'No se pueden realizar reservas en fechas u horas pasadas']);
        return;
    }
 
    // 4. Verificación del servicio y estado
    $servicio = $servicioModel->buscarPorId((int) $idServicio);

    // Validar si el servicio existe y si esta activo
    if (!$servicio || !$servicio['activo']) {
        http_response_code(404);
        echo json_encode(['error' => 'Servicio no encontrado o inactivo']);
        return;
    }

    $duracionMin = (int) $servicio['duracion_min'];

    // 5. Validacion de horario laboral y límites del día
    $horaApertura = DateTime::createFromFormat('Y-m-d H:i:s', $inicio->format('Y-m-d') . ' ' . HORA_APERTURA);
    $horaCierre = DateTime::createFromFormat('Y-m-d H:i:s', $inicio->format('Y-m-d') . ' ' . HORA_CIERRE);
    $fin = (clone $inicio)->modify('+' . (int) $servicio['duracion_min'] . ' minutes');

    if ($inicio < $horaApertura || $fin > $horaCierre) {
        http_response_code(400);
        echo json_encode(['error' => 'La reserva debe estar dentro del horario de atención (' . HORA_APERTURA . ' a ' . HORA_CIERRE . ')']);
        return;
    }

    // 6. Validar que la hora enviada encaje exactamente en un intervalo válido (Alineación con los bloques)
    $diferenciaMinutos = ($inicio->getTimestamp() - $horaApertura->getTimestamp()) / 60;
    if ($diferenciaMinutos % $duracionMin !== 0) {
        http_response_code(400);
        echo json_encode(['error' => "El horario de inicio debe coincidir con un bloque válido de {$duracionMin} minutos"]);
        return;
    }

    // 7. Intento de persistencia e inserción en la base de datos
    try {
        $idReserva = $reservaModel->crear(
            (int) $_SESSION['id_usuario'],
            (int) $idServicio,
            $inicio->format('Y-m-d H:i:s'),
            $fin->format('Y-m-d H:i:s')
        );

        http_response_code(201);
        echo json_encode([
            'mensaje' => 'Reserva creada correctamente',
            'id_reserva' => $idReserva,
            'fecha_hora_inicio' => $inicio->format('Y-m-d H:i:s'),
            'fecha_hora_fin' => $fin->format('Y-m-d H:i:s'),
        ]);
    } catch (PDOException $e) {

        // Manejo controlado de la excepción enviada por la BD cuando hay solapamiento
        if ($e->getCode() === '45000') {
            // '45000' es el código que usa MySQL cuando un TRIGGER ejecuta un
            // "SIGNAL" manual (ver trg_validar_solapamiento_insert en bd.sql).
            // O sea: la BASE DE DATOS misma detectó que ese horario ya está ocupado,
            // que es exactamente lo que pide el criterio de aceptación de esta tarea.
            http_response_code(409);
            echo json_encode(['error' => 'Ya existe una reserva en ese bloque de tiempo para este servicio']);
        } else {
            // Manejo de errores no previstos del servidor de base de datos
            error_log('Error al crear reserva: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'No se puede crear la reserva']);
        }

    }

}

/* ==========================================================================
   ENDPOINT 3: Listar Reservas del Usuario
   Método: GET 
   Ejemplo: ReservaController.php?accion=listarPorUsuario
   ========================================================================== */

/**
 * Consulta y devuelve el historial de reservas pertenecientes al usuario autenticado.
 *
 * @param Reserva $reservaModel Instancia del modelo de Reservas.
 */
function listarPorUsuario(Reserva $reservaModel): void {
    // 1. Verificación de sesión activa
    asegurarSesion();

    if(!isset($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Debe iniciar sesión para ver sus reservas']);
        return;
    }

    // 2. Consulta al modelo y envío del array resultante en JSON
    $reservas = $reservaModel->listarPorUsuario((int) $_SESSION['id_usuario']);

    echo json_encode(['reservas' => $reservas]);

}