-- ============================================
-- Plataforma de Gestión de Citas — Centro de Estética
-- Script completo para MySQL / XAMPP
-- Cómo usarlo: abre phpMyAdmin → pestaña "Importar" → elige este archivo → Continuar
-- Se crea la base de datos completa de un solo golpe, no hace falta hacer nada más antes.
-- ============================================

CREATE DATABASE IF NOT EXISTS citas_estetica CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE citas_estetica;

-- ============================================
-- TABLAS
-- ============================================

CREATE TABLE Roles (
  id_rol INT AUTO_INCREMENT PRIMARY KEY,
  nombre_rol VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE Usuarios (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  id_rol INT NOT NULL,
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_rol) REFERENCES Roles(id_rol)
) ENGINE=InnoDB;

CREATE TABLE Servicios (
  id_servicio INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  duracion_min INT NOT NULL,
  precio DECIMAL(10,2) NOT NULL,
  activo BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE Reservas (
  id_reserva INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_servicio INT NOT NULL,
  fecha_hora_inicio DATETIME NOT NULL,
  fecha_hora_fin DATETIME NOT NULL,
  estado_actual VARCHAR(20) DEFAULT 'pendiente',
  creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario),
  FOREIGN KEY (id_servicio) REFERENCES Servicios(id_servicio)
) ENGINE=InnoDB;

CREATE TABLE Historial_Estados (
  id_historial INT AUTO_INCREMENT PRIMARY KEY,
  id_reserva INT NOT NULL,
  estado_anterior VARCHAR(20),
  estado_nuevo VARCHAR(20),
  cambiado_por INT,
  cambiado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_reserva) REFERENCES Reservas(id_reserva),
  FOREIGN KEY (cambiado_por) REFERENCES Usuarios(id_usuario)
) ENGINE=InnoDB;

CREATE TABLE Logs_Auditoria (
  id_log INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT,
  accion VARCHAR(50) NOT NULL,
  tabla_afectada VARCHAR(50),
  id_registro_afectado INT,
  detalle JSON,
  ejecutado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario)
) ENGINE=InnoDB;

-- ============================================
-- ÍNDICES
-- ============================================

CREATE INDEX idx_disponibilidad ON Reservas (id_servicio, fecha_hora_inicio, fecha_hora_fin);
CREATE INDEX idx_estado_reserva ON Reservas (estado_actual);

-- ============================================
-- TRIGGERS — validar solapamiento
-- MySQL no tiene OVERLAPS ni permite un trigger para INSERT y UPDATE juntos,
-- por eso van separados y la comparación de rangos es manual.
-- ============================================

DELIMITER $$

CREATE TRIGGER trg_validar_solapamiento_insert
BEFORE INSERT ON Reservas
FOR EACH ROW
BEGIN
  DECLARE conflictos INT;

  SELECT COUNT(*) INTO conflictos
  FROM Reservas
  WHERE id_servicio = NEW.id_servicio
    AND estado_actual <> 'cancelado'
    AND NEW.fecha_hora_inicio < fecha_hora_fin
    AND NEW.fecha_hora_fin > fecha_hora_inicio;

  IF conflictos > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe una reserva en ese bloque de tiempo';
  END IF;
END$$

CREATE TRIGGER trg_validar_solapamiento_update
BEFORE UPDATE ON Reservas
FOR EACH ROW
BEGIN
  DECLARE conflictos INT;

  SELECT COUNT(*) INTO conflictos
  FROM Reservas
  WHERE id_servicio = NEW.id_servicio
    AND estado_actual <> 'cancelado'
    AND id_reserva <> OLD.id_reserva
    AND NEW.fecha_hora_inicio < fecha_hora_fin
    AND NEW.fecha_hora_fin > fecha_hora_inicio;

  IF conflictos > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe una reserva en ese bloque de tiempo';
  END IF;
END$$

DELIMITER ;

-- ============================================
-- DATOS BASE — para que la demo de esta noche ya tenga algo que mostrar
-- ============================================

INSERT INTO Roles (nombre_rol) VALUES ('cliente'), ('admin');

INSERT INTO Servicios (nombre, duracion_min, precio, activo) VALUES
  ('Facial hidratante', 45, 80000, TRUE),
  ('Masaje relajante', 60, 120000, TRUE),
  ('Manicure spa', 40, 45000, TRUE),
  ('Depilación facial', 20, 30000, TRUE);

-- Nota: no se insertan usuarios de ejemplo porque la contraseña debe pasar por
-- password_hash() en PHP, no se puede escribir el hash a mano de forma segura.
-- Regístrense desde la app una vez conectada al backend.

-- ============================================
-- DATOS DE EJEMPLO ADICIONALES
-- ============================================


-- ============================================
-- USUARIOS (clientes y administradores)
-- La contraseña es un hash de ejemplo (no funcional para login real,
-- solo para que las tablas tengan datos completos para las consultas)
-- ============================================

INSERT INTO Usuarios (nombre, correo, password_hash, id_rol, creado_en) VALUES
('Valentina Gómez', 'valentina.gomez@correo.com', '$2y$10$examplehash1', 1, '2026-07-01 09:00:00'),
('Camila Rodríguez', 'camila.rodriguez@correo.com', '$2y$10$examplehash2', 1, '2026-07-02 10:15:00'),
('Sofía Martínez', 'sofia.martinez@correo.com', '$2y$10$examplehash3', 1, '2026-07-03 11:30:00'),
('Isabella Torres', 'isabella.torres@correo.com', '$2y$10$examplehash4', 1, '2026-07-04 14:00:00'),
('Mariana Pérez', 'mariana.perez@correo.com', '$2y$10$examplehash5', 1, '2026-07-05 16:45:00'),
('Daniela Ríos', 'daniela.rios@correo.com', '$2y$10$examplehash6', 1, '2026-07-06 08:20:00'),
('Laura Jiménez', 'admin@dahliabeaute.com', '$2y$10$examplehashadmin', 2, '2026-07-01 08:00:00');

-- ============================================
-- RESERVAS (variadas: distintos servicios, fechas, estados)
-- ============================================

INSERT INTO Reservas (id_usuario, id_servicio, fecha_hora_inicio, fecha_hora_fin, estado_actual, creado_en) VALUES
(1, 1, '2026-08-01 10:00:00', '2026-08-01 10:45:00', 'finalizado', '2026-07-25 09:00:00'),
(2, 2, '2026-08-01 11:00:00', '2026-08-01 12:00:00', 'finalizado', '2026-07-25 09:10:00'),
(3, 1, '2026-08-02 09:00:00', '2026-08-02 09:45:00', 'finalizado', '2026-07-26 10:00:00'),
(4, 3, '2026-08-02 15:00:00', '2026-08-02 15:40:00', 'cancelado', '2026-07-26 11:00:00'),
(5, 4, '2026-08-03 10:00:00', '2026-08-03 10:20:00', 'finalizado', '2026-07-27 08:30:00'),
(1, 2, '2026-08-05 14:00:00', '2026-08-05 15:00:00', 'en_proceso', '2026-07-28 09:00:00'),
(2, 1, '2026-08-06 09:00:00', '2026-08-06 09:45:00', 'pendiente', '2026-07-29 10:00:00'),
(6, 3, '2026-08-06 11:00:00', '2026-08-06 11:40:00', 'pendiente', '2026-07-29 12:00:00'),
(3, 4, '2026-08-07 16:00:00', '2026-08-07 16:20:00', 'pendiente', '2026-07-30 09:00:00'),
(4, 1, '2026-08-08 10:00:00', '2026-08-08 10:45:00', 'pendiente', '2026-07-30 15:00:00'),
(5, 2, '2026-08-08 13:00:00', '2026-08-08 14:00:00', 'pendiente', '2026-07-31 09:00:00'),
(6, 1, '2026-08-09 09:00:00', '2026-08-09 09:45:00', 'pendiente', '2026-07-31 10:00:00');

-- ============================================
-- HISTORIAL_ESTADOS (algunos cambios de estado ya registrados)
-- ============================================

INSERT INTO Historial_Estados (id_reserva, estado_anterior, estado_nuevo, cambiado_por, cambiado_en) VALUES
(1, 'pendiente', 'en_proceso', 7, '2026-08-01 10:00:00'),
(1, 'en_proceso', 'finalizado', 7, '2026-08-01 10:45:00'),
(2, 'pendiente', 'finalizado', 7, '2026-08-01 12:00:00'),
(3, 'pendiente', 'finalizado', 7, '2026-08-02 09:45:00'),
(4, 'pendiente', 'cancelado', 4, '2026-07-30 08:00:00'),
(5, 'pendiente', 'finalizado', 7, '2026-08-03 10:20:00'),
(6, 'pendiente', 'en_proceso', 7, '2026-08-05 14:00:00');

-- ============================================
-- LOGS_AUDITORIA (acciones sensibles registradas)
-- ============================================

INSERT INTO Logs_Auditoria (id_usuario, accion, tabla_afectada, id_registro_afectado, ejecutado_en) VALUES
(1, 'registro', 'Usuarios', 1, '2026-07-01 09:00:00'),
(2, 'registro', 'Usuarios', 2, '2026-07-02 10:15:00'),
(1, 'login', 'Usuarios', 1, '2026-07-25 08:55:00'),
(7, 'login', 'Usuarios', 7, '2026-08-01 09:55:00'),
(7, 'cambiar_estado', 'Reservas', 1, '2026-08-01 10:45:00'),
(4, 'cancelar_reserva', 'Reservas', 4, '2026-07-30 08:00:00');
