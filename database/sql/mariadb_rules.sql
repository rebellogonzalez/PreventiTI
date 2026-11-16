SET time_zone = '+00:00';

-- Reglas del motor MariaDB; la migración es una operación nula en SQLite.
ALTER TABLE consumibles ADD CONSTRAINT chk_consumible_porcentaje CHECK (nivel BETWEEN 0 AND 100);
ALTER TABLE tecnicos ADD CONSTRAINT fk_tecnico_cuenta FOREIGN KEY (id) REFERENCES usuarios(id) ON DELETE RESTRICT;
ALTER TABLE jefes_tecnicos ADD CONSTRAINT fk_jefe_cuenta FOREIGN KEY (id) REFERENCES usuarios(id) ON DELETE RESTRICT;
ALTER TABLE reportes MODIFY fecha_creacion DATETIME(6) NOT NULL, MODIFY fecha_cierre DATETIME(6) NULL,
  MODIFY estado_reporte VARCHAR(50) COLLATE utf8mb4_bin NOT NULL DEFAULT 'Recibido/Derivado';
ALTER TABLE tecnico_jefe_tecnico
  MODIFY fecha_asignacion DATETIME(6) NOT NULL,
  MODIFY fecha_finalizacion DATETIME(6) NULL,
  ADD COLUMN reporte_vigente BIGINT UNSIGNED AS (CASE WHEN fecha_finalizacion IS NULL THEN reporte_id ELSE NULL END) PERSISTENT,
  ADD UNIQUE KEY uq_asignacion_vigente (reporte_vigente),
  ADD KEY idx_asignacion_tecnico_vigente (tecnico_id,fecha_finalizacion),
  ADD CONSTRAINT chk_periodo_asignacion CHECK (fecha_finalizacion IS NULL OR fecha_finalizacion >= fecha_asignacion);
ALTER TABLE reportes
  ADD CONSTRAINT chk_estado_reporte CHECK (BINARY estado_reporte IN ('Abierto','Recibido/Derivado','En curso','Diagnóstico','Finalizado','Resuelto','Cerrado','Cancelado')),
  ADD CONSTRAINT chk_fechas_reporte CHECK (fecha_cierre IS NULL OR fecha_cierre >= fecha_creacion);
ALTER TABLE activos
  ADD CONSTRAINT chk_estado_activo CHECK (BINARY estado_activo IN ('Operativo','En Mantenimiento','Fuera de Servicio','Desconectado','Retirado')),
  ADD CONSTRAINT chk_fecha_baja_activo CHECK ((BINARY estado_activo=BINARY 'Retirado' AND fecha_baja IS NOT NULL) OR (BINARY estado_activo<>BINARY 'Retirado' AND fecha_baja IS NULL));

CREATE TABLE historial_estados_activo (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  activo_id BIGINT UNSIGNED NOT NULL,
  estado_anterior VARCHAR(50) COLLATE utf8mb4_bin NOT NULL,
  estado_nuevo VARCHAR(50) COLLATE utf8mb4_bin NOT NULL,
  fecha_cambio DATETIME(6) NOT NULL,
  usuario_id BIGINT UNSIGNED NULL,
  motivo TEXT NULL,
  CONSTRAINT fk_historial_activo FOREIGN KEY (activo_id) REFERENCES activos(id) ON DELETE RESTRICT,
  CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  KEY idx_historial_activo_fecha (activo_id,fecha_cambio,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER //

CREATE PROCEDURE validar_asignacion_vigente(IN p_reporte BIGINT UNSIGNED, IN p_tecnico BIGINT UNSIGNED, IN p_jefe BIGINT UNSIGNED)
BEGIN
  DECLARE v_activo BOOLEAN DEFAULT NULL;
  DECLARE v_estado VARCHAR(50) DEFAULT NULL;
  DECLARE v_cierre DATETIME(6) DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_activo = NULL;
  SELECT activo INTO v_activo FROM usuarios WHERE id=p_tecnico LOCK IN SHARE MODE;
  IF COALESCE(v_activo,0) <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El técnico debe tener una cuenta activa.'; END IF;
  IF NOT EXISTS (SELECT 1 FROM tecnicos WHERE id=p_tecnico) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No existe el perfil técnico.'; END IF;
  IF EXISTS (SELECT 1 FROM jefes_tecnicos WHERE id=p_tecnico) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El jefe no puede ser el especialista asignado.'; END IF;
  SELECT activo INTO v_activo FROM usuarios WHERE id=p_jefe LOCK IN SHARE MODE;
  IF COALESCE(v_activo,0) <> 1 OR NOT EXISTS (SELECT 1 FROM jefes_tecnicos WHERE id=p_jefe) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La jefatura debe existir y tener cuenta activa.'; END IF;
  SELECT estado_reporte,fecha_cierre INTO v_estado,v_cierre FROM reportes WHERE id=p_reporte FOR UPDATE;
  IF v_estado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No existe el reporte.'; END IF;
  IF v_cierre IS NOT NULL OR BINARY v_estado IN ('Finalizado','Resuelto','Cerrado','Cancelado') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede asignar un reporte cerrado.'; END IF;
END//

CREATE TRIGGER asignacion_bi BEFORE INSERT ON tecnico_jefe_tecnico FOR EACH ROW
BEGIN
  IF NEW.fecha_finalizacion IS NULL THEN
    CALL validar_asignacion_vigente(NEW.reporte_id,NEW.tecnico_id,NEW.jefe_id);
  END IF;
END//

CREATE TRIGGER asignacion_bu BEFORE UPDATE ON tecnico_jefe_tecnico FOR EACH ROW
BEGIN
  IF NEW.tecnico_id<>OLD.tecnico_id OR NEW.jefe_id<>OLD.jefe_id OR NEW.reporte_id<>OLD.reporte_id OR NEW.fecha_asignacion<>OLD.fecha_asignacion THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La identidad de una asignación es inmutable; inserte otra fila.';
  END IF;
  IF OLD.fecha_finalizacion IS NOT NULL AND NOT (NEW.fecha_finalizacion <=> OLD.fecha_finalizacion) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede reabrir ni reescribir un período terminado.';
  END IF;
  IF NEW.fecha_finalizacion IS NULL THEN
    CALL validar_asignacion_vigente(NEW.reporte_id,NEW.tecnico_id,NEW.jefe_id);
  END IF;
END//

CREATE TRIGGER reporte_bi BEFORE INSERT ON reportes FOR EACH ROW
BEGIN
  IF BINARY NEW.estado_reporte <> BINARY 'Recibido/Derivado' OR NEW.fecha_cierre IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Los reportes nuevos deben comenzar en Recibido/Derivado y sin cierre.';
  END IF;
  IF CHAR_LENGTH(TRIM(NEW.descripcion))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Se requiere la descripción del reporte.'; END IF;
END//

CREATE TRIGGER reporte_bu BEFORE UPDATE ON reportes FOR EACH ROW
BEGIN
  DECLARE v_tecnico BIGINT UNSIGNED DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_tecnico=NULL;
  IF NOT (BINARY NEW.estado_reporte <=> BINARY OLD.estado_reporte) THEN
    IF OLD.fecha_cierre IS NOT NULL OR BINARY OLD.estado_reporte IN ('Finalizado','Resuelto','Cerrado','Cancelado') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede cambiar el estado de un reporte cerrado.';
    END IF;
    IF NOT ((BINARY OLD.estado_reporte=BINARY 'Recibido/Derivado' AND BINARY NEW.estado_reporte=BINARY 'En curso')
      OR (BINARY OLD.estado_reporte=BINARY 'En curso' AND BINARY NEW.estado_reporte IN ('Diagnóstico','Finalizado'))
      OR (BINARY OLD.estado_reporte=BINARY 'Diagnóstico' AND BINARY NEW.estado_reporte=BINARY 'Finalizado')) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transición de estado no permitida.';
    END IF;
    SELECT a.tecnico_id INTO v_tecnico FROM tecnico_jefe_tecnico a JOIN usuarios u ON u.id=a.tecnico_id
      WHERE a.reporte_id=OLD.id AND a.fecha_finalizacion IS NULL AND u.activo=1 LIMIT 1 LOCK IN SHARE MODE;
    IF v_tecnico IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Se requiere un técnico vigente con cuenta activa para avanzar.'; END IF;
    IF BINARY NEW.estado_reporte IN ('Diagnóstico','Finalizado') AND
       (NEW.informe_accionar IS NULL OR CHAR_LENGTH(TRIM(NEW.informe_accionar))<5 OR CHAR_LENGTH(NEW.informe_accionar)>5000) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Se requiere informe de accionar de 5 a 5000 caracteres.';
    END IF;
    IF BINARY NEW.estado_reporte=BINARY 'Diagnóstico' AND
       (NEW.diagnostico_final IS NULL OR CHAR_LENGTH(TRIM(NEW.diagnostico_final))<5 OR CHAR_LENGTH(NEW.diagnostico_final)>5000) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Diagnóstico requiere diagnóstico final de 5 a 5000 caracteres.';
    END IF;
    IF BINARY NEW.estado_reporte=BINARY 'Finalizado' THEN
      SET NEW.fecha_cierre=COALESCE(NEW.fecha_cierre,COALESCE(@preventiti_momento,UTC_TIMESTAMP(6)));
      SET NEW.resolucion=NEW.informe_accionar;
    ELSE
      IF NEW.fecha_cierre IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Un reporte abierto no puede tener fecha de cierre.'; END IF;
    END IF;
    SET NEW.revision_flujo=OLD.revision_flujo+1;
    SET NEW.updated_at=COALESCE(@preventiti_momento,UTC_TIMESTAMP);
  ELSE
    IF NOT (NEW.fecha_cierre <=> OLD.fecha_cierre) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La fecha de cierre solo se establece al finalizar.'; END IF;
  END IF;
  -- Los campos requeridos también se preservan en actualizaciones sin transición.
  IF BINARY NEW.estado_reporte=BINARY 'Diagnóstico' AND
    (COALESCE(CHAR_LENGTH(TRIM(NEW.informe_accionar)),0)<5 OR COALESCE(CHAR_LENGTH(TRIM(NEW.diagnostico_final)),0)<5) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se pueden vaciar los datos de diagnóstico.';
  END IF;
  IF BINARY NEW.estado_reporte=BINARY 'Finalizado' AND
    (NEW.fecha_cierre IS NULL OR COALESCE(CHAR_LENGTH(TRIM(NEW.informe_accionar)),0)<5) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se pueden vaciar los datos de cierre.';
  END IF;
  IF NEW.informe_accionar IS NOT NULL AND (CHAR_LENGTH(TRIM(NEW.informe_accionar))<5 OR CHAR_LENGTH(NEW.informe_accionar)>5000) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El informe debe tener de 5 a 5000 caracteres.';
  END IF;
  IF NEW.diagnostico_final IS NOT NULL AND (CHAR_LENGTH(TRIM(NEW.diagnostico_final))<5 OR CHAR_LENGTH(NEW.diagnostico_final)>5000) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El diagnóstico informado debe tener de 5 a 5000 caracteres.';
  END IF;
END//

CREATE TRIGGER reporte_au AFTER UPDATE ON reportes FOR EACH ROW
BEGIN
  IF OLD.fecha_cierre IS NULL AND NEW.fecha_cierre IS NOT NULL THEN
    UPDATE tecnico_jefe_tecnico SET fecha_finalizacion=NEW.fecha_cierre WHERE reporte_id=NEW.id AND fecha_finalizacion IS NULL;
  END IF;
END//

CREATE TRIGGER asignacion_ai AFTER INSERT ON tecnico_jefe_tecnico FOR EACH ROW
BEGIN
  IF NEW.fecha_finalizacion IS NULL THEN
    -- El trigger de reportes solo cierra asignaciones al cerrar realmente el reporte.
    UPDATE reportes SET estado_reporte='En curso' WHERE id=NEW.reporte_id AND BINARY estado_reporte=BINARY 'Recibido/Derivado';
    IF ROW_COUNT()=0 THEN
      UPDATE reportes SET revision_flujo=revision_flujo+1,updated_at=UTC_TIMESTAMP WHERE id=NEW.reporte_id;
    END IF;
  END IF;
END//

CREATE TRIGGER usuario_au AFTER UPDATE ON usuarios FOR EACH ROW
BEGIN
  DECLARE v_fecha DATETIME(6);
  IF OLD.activo=1 AND NEW.activo=0 THEN
    SET v_fecha=COALESCE(@preventiti_momento,UTC_TIMESTAMP(6));
    UPDATE tecnico_jefe_tecnico SET fecha_finalizacion=v_fecha,motivo_reasignacion='Baja de la cuenta del técnico.'
      WHERE tecnico_id=NEW.id AND fecha_finalizacion IS NULL;
    UPDATE reportes SET revision_flujo=revision_flujo+1,updated_at=UTC_TIMESTAMP
      WHERE id IN (SELECT reporte_id FROM tecnico_jefe_tecnico WHERE tecnico_id=NEW.id AND fecha_finalizacion=v_fecha AND motivo_reasignacion='Baja de la cuenta del técnico.');
  END IF;
END//

CREATE TRIGGER activo_bu BEFORE UPDATE ON activos FOR EACH ROW
BEGIN
  IF NOT (BINARY NEW.estado_activo <=> BINARY OLD.estado_activo) THEN
    IF BINARY NEW.estado_activo=BINARY 'Retirado' THEN
      IF NEW.estado_previo_retiro IS NOT NULL AND BINARY NEW.estado_previo_retiro<>BINARY OLD.estado_activo THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El estado previo debe coincidir con el estado que se retira.';
      END IF;
      SET NEW.estado_previo_retiro=OLD.estado_activo;
      SET NEW.fecha_baja=COALESCE(NEW.fecha_baja,UTC_TIMESTAMP);
    ELSEIF BINARY OLD.estado_activo=BINARY 'Retirado' THEN
      IF BINARY NEW.estado_activo <> BINARY COALESCE(NULLIF(OLD.estado_previo_retiro,'Retirado'),'Fuera de Servicio') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La reincorporación debe recuperar el estado previo o Fuera de Servicio si no existe.';
      END IF;
      SET NEW.estado_previo_retiro=NULL;
      SET NEW.fecha_baja=NULL;
    END IF;
  END IF;
  IF (BINARY NEW.estado_activo=BINARY 'Retirado' AND NEW.fecha_baja IS NULL)
    OR (BINARY NEW.estado_activo<>BINARY 'Retirado' AND NEW.fecha_baja IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fecha de baja incompatible con el estado del activo.';
  END IF;
END//

CREATE TRIGGER activo_au AFTER UPDATE ON activos FOR EACH ROW
BEGIN
  IF NOT (BINARY NEW.estado_activo <=> BINARY OLD.estado_activo) THEN
    INSERT INTO historial_estados_activo(activo_id,estado_anterior,estado_nuevo,fecha_cambio,usuario_id,motivo)
      VALUES(NEW.id,OLD.estado_activo,NEW.estado_activo,COALESCE(@preventiti_momento,UTC_TIMESTAMP(6)),@preventiti_usuario_id,NULLIF(TRIM(@preventiti_motivo),''));
  END IF;
END//

CREATE TRIGGER consumible_au AFTER UPDATE ON consumibles FOR EACH ROW
BEGIN
  DECLARE v_auditoria BIGINT UNSIGNED;
  DECLARE v_reporte BIGINT UNSIGNED;
  DECLARE v_alerta BIGINT UNSIGNED;
  DECLARE v_jefe BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_fecha DATETIME(6);
  IF OLD.nivel>20 AND NEW.nivel<=20 AND NEW.nivel<OLD.nivel THEN
    SET v_fecha=COALESCE(@preventiti_momento,UTC_TIMESTAMP(6));
    SELECT MIN(j.id) INTO v_jefe FROM jefes_tecnicos j JOIN usuarios u ON u.id=j.id WHERE u.activo=1;
    INSERT INTO auditorias(fecha_auditoria,descripcion)
      VALUES(v_fecha,CONCAT('Generación automática por consumible ',NEW.id,': ',OLD.nivel,'% a ',NEW.nivel,'%.'));
    SET v_auditoria=LAST_INSERT_ID();
    INSERT INTO reportes(jefe_id,auditoria_id,historial,estado_reporte,urgencia,descripcion,fecha_creacion,created_at,updated_at)
      VALUES(v_jefe,v_auditoria,CONCAT('Origen automático: consumible ',NEW.id,'.'),'Recibido/Derivado','Medio',
        CONCAT('Consumible ',NEW.id,' del activo ',NEW.activo_id,' descendió de ',OLD.nivel,'% a ',NEW.nivel,'%; umbral 20%.'),v_fecha,v_fecha,v_fecha);
    SET v_reporte=LAST_INSERT_ID();
    INSERT INTO reporte_activo(reporte_id,activo_id) VALUES(v_reporte,NEW.activo_id);
    -- El generador es la base, no un usuario: no se fabrica una fila usuario_reporte.
    INSERT INTO alertas(reporte_id,nivel_alerta,tipo_alerta,fecha_generacion,estado_alerta,urgencia,created_at,updated_at)
      VALUES(v_reporte,'Medio','Consumible',v_fecha,'Pendiente','Medio',v_fecha,v_fecha);
    SET v_alerta=LAST_INSERT_ID();
    INSERT INTO activo_alerta(activo_id,alerta_id) VALUES(NEW.activo_id,v_alerta);
  END IF;
END//

CREATE PROCEDURE asignar_tecnico(IN p_reporte_id BIGINT UNSIGNED, IN p_tecnico_id BIGINT UNSIGNED, IN p_jefe_id BIGINT UNSIGNED, IN p_motivo TEXT)
BEGIN
  DECLARE v_tecnico BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_estado VARCHAR(50);
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_tecnico=NULL;
  IF @@in_transaction=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Inicie una transacción antes de llamar a asignar_tecnico.'; END IF;
  CALL validar_asignacion_vigente(p_reporte_id,p_tecnico_id,p_jefe_id);
  SELECT estado_reporte INTO v_estado FROM reportes WHERE id=p_reporte_id FOR UPDATE;
  SELECT tecnico_id INTO v_tecnico FROM tecnico_jefe_tecnico WHERE reporte_id=p_reporte_id AND fecha_finalizacion IS NULL FOR UPDATE;
  IF v_tecnico=p_tecnico_id THEN
    IF BINARY v_estado=BINARY 'Recibido/Derivado' THEN
      UPDATE reportes SET estado_reporte='En curso' WHERE id=p_reporte_id;
    ELSE
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El técnico ya es el responsable vigente.';
    END IF;
  ELSE
    IF v_tecnico IS NOT NULL THEN
      IF COALESCE(CHAR_LENGTH(TRIM(p_motivo)),0)<5 OR CHAR_LENGTH(p_motivo)>2000 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La reasignación requiere motivo de 5 a 2000 caracteres.';
      END IF;
      UPDATE tecnico_jefe_tecnico SET fecha_finalizacion=COALESCE(@preventiti_momento,UTC_TIMESTAMP(6)),motivo_reasignacion=p_motivo WHERE reporte_id=p_reporte_id AND fecha_finalizacion IS NULL;
    END IF;
    INSERT INTO tecnico_jefe_tecnico(tecnico_id,jefe_id,reporte_id,fecha_asignacion)
      VALUES(p_tecnico_id,p_jefe_id,p_reporte_id,COALESCE(@preventiti_momento,UTC_TIMESTAMP(6)));
  END IF;
END//

CREATE PROCEDURE cambiar_estado_reporte(IN p_reporte_id BIGINT UNSIGNED, IN p_estado VARCHAR(50), IN p_informe TEXT, IN p_diagnostico TEXT)
BEGIN
  DECLARE v_estado VARCHAR(50) DEFAULT NULL;
  DECLARE v_cierre DATETIME(6) DEFAULT NULL;
  IF @@in_transaction=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Inicie una transacción antes de cambiar el estado.'; END IF;
  SELECT estado_reporte,fecha_cierre INTO v_estado,v_cierre FROM reportes WHERE id=p_reporte_id FOR UPDATE;
  IF v_estado IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No existe el reporte.'; END IF;
  IF v_cierre IS NOT NULL OR BINARY v_estado IN ('Finalizado','Resuelto','Cerrado','Cancelado') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No se puede cambiar el estado de un reporte cerrado.';
  END IF;
  IF BINARY v_estado=BINARY p_estado THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El reporte ya está en ese estado.'; END IF;
  UPDATE reportes SET estado_reporte=p_estado,informe_accionar=COALESCE(p_informe,informe_accionar),diagnostico_final=COALESCE(p_diagnostico,diagnostico_final)
    WHERE id=p_reporte_id;
  IF ROW_COUNT()=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No existe el reporte o no hay cambios.'; END IF;
END//

DELIMITER ;
