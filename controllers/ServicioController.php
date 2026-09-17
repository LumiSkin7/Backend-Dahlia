<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Servicio.php';

header('Content-Type: application/json');

$servicioModel = new Servicio($pdo);

$accion = $_GET['accion'] ?? '';

if($accion === 'listar'){

    listar($servicioModel);

} elseif ($accion ==='listarTodos') {

    listarTodos($servicioModel);

} elseif ($accion === 'detalle') {

    detalle($servicioModel);

} else {

    http_response_code(400);
    
    echo json_encode(['error' => 'Acción no válida']);
}


function listar(Servicio $servicioModel):void{

    $servicios = $servicioModel->listarActivos();

    echo json_encode(['servicios' => $servicios]);

}

function listarTodos(Servicio $servicioModel): void{

    $servicios = $servicioModel->listarTodos();

    echo json_encode(['servicios' => $servicios]);

}

function detalle(Servicio $servicioModel): void{

    $id = $_GET['id'] ?? null;

    if (!$id || !is_numeric($id)) {

        http_response_code(400);
        
        echo json_encode(['error' => 'Debe indicar un id de servicio válido']);
        return;

    }


    $servicio = $servicioModel->buscarPorId((int) $id);

    if(!$servicio){
        http_response_code(404);

        echo json_encode(['error' => 'Servicio no encontrado']);
        return;
    }
    
    echo json_encode(['servicio' => $servicio]);

}