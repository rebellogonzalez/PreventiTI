-- =============================================================================
-- PREVENTITI — MODELO FÍSICO HITO 1
-- MariaDB 10.11+ | InnoDB | utf8mb4 | 3FN
-- Base: DDL original recibido del proyecto, con las extensiones necesarias
-- para autenticación Laravel, trazabilidad de asignaciones y el flujo acordado.
-- El calendario, sesiones, caché y colas son soporte técnico de Laravel y no
-- pertenecen al MER académico.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS preventiti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE preventiti;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tecnico_jefe_tecnico;
DROP TABLE IF EXISTS activo_alerta;
DROP TABLE IF EXISTS alertas;
DROP TABLE IF EXISTS consumibles;
DROP TABLE IF EXISTS mantenimientos;
DROP TABLE IF EXISTS tareas_calendario;
DROP TABLE IF EXISTS reporte_activo;
DROP TABLE IF EXISTS usuario_reporte;
DROP TABLE IF EXISTS reportes;
DROP TABLE IF EXISTS auditorias;
DROP TABLE IF EXISTS activo_ubicaciones;
DROP TABLE IF EXISTS activos;
DROP TABLE IF EXISTS categorias_activo;
DROP TABLE IF EXISTS jefe_nombres;
DROP TABLE IF EXISTS jefes_tecnicos;
DROP TABLE IF EXISTS tecnicos;
DROP TABLE IF EXISTS usuario_direcciones;
DROP TABLE IF EXISTS usuario_nombres;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- Usuario + atributos compuestos normalizados. password/activo/token son soporte
-- técnico necesario para conservar autenticación Laravel.
CREATE TABLE usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(150) NULL,
    cedula VARCHAR(30) NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    direccion VARCHAR(200) NULL,
    rol ENUM('Administrador', 'Empleado') NOT NULL,
    numero_telefono VARCHAR(30) NULL,
    password VARCHAR(255) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Soporte Laravel para recuperación de contraseña. No forma parte del MER
-- original, pero es requerido por la cuenta activa y la recuperación de sesión.
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
) ENGINE=InnoDB;

-- Sesiones persistidas por Laravel. Junto con remember_token permiten que la
-- sesión pueda recuperarse de acuerdo con la política de autenticación.
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    INDEX idx_sessions_user_id (user_id),
    INDEX idx_sessions_last_activity (last_activity)
) ENGINE=InnoDB;

CREATE TABLE usuario_nombres (
    usuario_id BIGINT UNSIGNED PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NULL,
    CONSTRAINT fk_usuario_nombre_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE usuario_direcciones (
    usuario_id BIGINT UNSIGNED PRIMARY KEY,
    calle VARCHAR(150) NULL,
    numero_calle VARCHAR(20) NULL,
    CONSTRAINT fk_usuario_direccion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE tecnicos (
    id BIGINT UNSIGNED PRIMARY KEY,
    tipo_tecnico ENUM('Hardware', 'Software', 'Redes/Infraestructura') NOT NULL
) ENGINE=InnoDB;

CREATE TABLE jefes_tecnicos (
    id BIGINT UNSIGNED PRIMARY KEY,
    nombre_completo VARCHAR(200) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE jefe_nombres (
    jefe_id BIGINT UNSIGNED PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NULL,
    CONSTRAINT fk_jefe_nombre_jefe FOREIGN KEY (jefe_id) REFERENCES jefes_tecnicos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categorias_activo (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT NULL,
    requiere_consumible BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE activos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    responsable_id BIGINT UNSIGNED NULL,
    categoria_id BIGINT UNSIGNED NOT NULL,
    serial VARCHAR(100) NOT NULL UNIQUE,
    ubicacion VARCHAR(200) NULL,
    descripcion TEXT NOT NULL,
    estado_activo VARCHAR(50) NOT NULL,
    estado_previo_retiro VARCHAR(255) NULL,
    fecha_baja TIMESTAMP NULL,
    fecha_alta DATE NOT NULL,
    frecuencia_service INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_activo_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_activo(id) ON DELETE RESTRICT,
    CONSTRAINT fk_activo_responsable FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_activos_categoria_estado (categoria_id, estado_activo)
) ENGINE=InnoDB;

CREATE TABLE activo_ubicaciones (
    activo_id BIGINT UNSIGNED PRIMARY KEY,
    piso VARCHAR(100) NULL,
    sala VARCHAR(150) NULL,
    CONSTRAINT fk_activo_ubicacion_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE auditorias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha_auditoria DATETIME NOT NULL,
    descripcion TEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE reportes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jefe_id BIGINT UNSIGNED NULL,
    auditoria_id BIGINT UNSIGNED NULL UNIQUE,
    historial TEXT NULL,
    estado_reporte VARCHAR(50) NOT NULL DEFAULT 'Recibido/Derivado',
    urgencia ENUM('Critico', 'Alto', 'Medio', 'Bajo') NOT NULL DEFAULT 'Medio',
    descripcion TEXT NOT NULL,
    fecha_creacion DATETIME NOT NULL,
    fecha_cierre DATETIME NULL,
    resolucion TEXT NULL,
    informe_accionar TEXT NULL,
    clave_envio CHAR(64) NULL UNIQUE,
    revision_flujo INT UNSIGNED NOT NULL DEFAULT 0,
    intervenciones JSON NULL,
    diagnostico_final TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reporte_jefe FOREIGN KEY (jefe_id) REFERENCES jefes_tecnicos(id) ON DELETE SET NULL,
    CONSTRAINT fk_reporte_auditoria FOREIGN KEY (auditoria_id) REFERENCES auditorias(id) ON DELETE SET NULL,
    INDEX idx_reportes_estado_urgencia (estado_reporte, urgencia)
) ENGINE=InnoDB;

CREATE TABLE usuario_reporte (
    usuario_id BIGINT UNSIGNED NOT NULL,
    reporte_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, reporte_id),
    CONSTRAINT fk_usuario_reporte_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_reporte_reporte FOREIGN KEY (reporte_id) REFERENCES reportes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reporte_activo (
    reporte_id BIGINT UNSIGNED NOT NULL,
    activo_id BIGINT UNSIGNED NOT NULL,
    -- Un reporte se genera sobre un único dispositivo exacto.
    PRIMARY KEY (reporte_id),
    CONSTRAINT fk_reporte_activo_reporte FOREIGN KEY (reporte_id) REFERENCES reportes(id) ON DELETE CASCADE,
    CONSTRAINT fk_reporte_activo_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE alertas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nivel_alerta VARCHAR(50) NOT NULL,
    tipo_alerta VARCHAR(100) NOT NULL,
    fecha_generacion DATETIME NOT NULL,
    estado_alerta VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
    urgencia ENUM('Critico', 'Alto', 'Medio', 'Bajo') NOT NULL DEFAULT 'Medio',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE activo_alerta (
    activo_id BIGINT UNSIGNED NOT NULL,
    alerta_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (activo_id, alerta_id),
    CONSTRAINT fk_activo_alerta_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE CASCADE,
    CONSTRAINT fk_activo_alerta_alerta FOREIGN KEY (alerta_id) REFERENCES alertas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE mantenimientos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activo_id BIGINT UNSIGNED NOT NULL,
    reporte_id BIGINT UNSIGNED NULL,
    task_id BIGINT UNSIGNED NULL UNIQUE,
    technician_id BIGINT UNSIGNED NULL,
    technician_name VARCHAR(255) NULL,
    interval_days INT UNSIGNED NULL,
    projected_date DATE NULL,
    requires_review BOOLEAN NOT NULL DEFAULT FALSE,
    submission_key CHAR(64) NULL UNIQUE,
    submission_hash CHAR(64) NULL,
    CONSTRAINT fk_inspeccion_tecnico FOREIGN KEY (technician_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    fecha_mantenimiento DATE NOT NULL,
    descripcion TEXT NOT NULL,
    observaciones TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mantenimiento_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mantenimiento_reporte FOREIGN KEY (reporte_id) REFERENCES reportes(id) ON DELETE SET NULL,
    INDEX idx_mantenimientos_activo_fecha (activo_id, fecha_mantenimiento)
) ENGINE=InnoDB;

CREATE TABLE consumibles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activo_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    nivel DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_consumible_nivel CHECK (nivel BETWEEN 0 AND 100),
    CONSTRAINT fk_consumible_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE CASCADE,
    INDEX idx_consumibles_activo_nivel (activo_id, nivel)
) ENGINE=InnoDB;

-- "Tecnico-Jefe_Tenico" del Hito: la relación se conserva bajo el nombre
-- tecnico_jefe_tecnico. Una fila representa una asignación, no una relación
-- genérica entre roles; así se preservan las reasignaciones por reporte.
CREATE TABLE tecnico_jefe_tecnico (
    tecnico_id BIGINT UNSIGNED NOT NULL,
    jefe_id BIGINT UNSIGNED NOT NULL,
    reporte_id BIGINT UNSIGNED NOT NULL,
    fecha_asignacion DATETIME NOT NULL,
    fecha_finalizacion DATETIME NULL,
    motivo_reasignacion TEXT NULL,
    PRIMARY KEY (tecnico_id, jefe_id, reporte_id, fecha_asignacion),
    CONSTRAINT fk_asignacion_tecnico FOREIGN KEY (tecnico_id) REFERENCES tecnicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_asignacion_jefe FOREIGN KEY (jefe_id) REFERENCES jefes_tecnicos(id) ON DELETE RESTRICT,
    CONSTRAINT fk_asignacion_reporte FOREIGN KEY (reporte_id) REFERENCES reportes(id) ON DELETE CASCADE,
    INDEX idx_asignacion_reporte_activa (reporte_id, fecha_finalizacion)
) ENGINE=InnoDB;

-- Soporte de la funcionalidad de calendario existente (fuera del MER del Hito).
CREATE TABLE tareas_calendario (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    hora VARCHAR(10) NOT NULL DEFAULT '09:00',
    titulo VARCHAR(200) NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'mantenimiento',
    category VARCHAR(100) NOT NULL DEFAULT 'Mantenimiento',
    technician_id BIGINT UNSIGNED NULL,
    technician_name VARCHAR(150) NULL,
    asset_id BIGINT UNSIGNED NULL,
    asset_code VARCHAR(50) NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'Media',
    completed BOOLEAN NOT NULL DEFAULT FALSE,
    reporte_id BIGINT UNSIGNED NULL,
    cancel_reason VARCHAR(255) NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 0,
    submission_key CHAR(64) NULL UNIQUE,
    submission_hash CHAR(64) NULL,
    CONSTRAINT fk_calendario_reporte FOREIGN KEY (reporte_id) REFERENCES reportes(id) ON DELETE RESTRICT,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_calendario_usuario FOREIGN KEY (technician_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_calendario_activo FOREIGN KEY (asset_id) REFERENCES activos(id) ON DELETE SET NULL,
    INDEX idx_calendario_fecha (fecha, completed)
) ENGINE=InnoDB;

ALTER TABLE mantenimientos ADD CONSTRAINT fk_inspeccion_tarea FOREIGN KEY (task_id) REFERENCES tareas_calendario(id) ON DELETE RESTRICT;
