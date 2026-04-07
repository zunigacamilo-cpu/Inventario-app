-- Esquema: salón social — reservas con anticipación, inventario del salón, comunicación administración–residentes
-- Ejecutar en phpMyAdmin o: /opt/lampp/bin/mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS inventario_app
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventario_app;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS comunicados;
DROP TABLE IF EXISTS reservas_salon;
DROP TABLE IF EXISTS salon_config;
DROP TABLE IF EXISTS relaciones_insumo;
DROP TABLE IF EXISTS insumos;
DROP TABLE IF EXISTS categorias_insumo;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS perfiles;

SET FOREIGN_KEY_CHECKS = 1;

-- Perfiles de trabajo (mínimos requeridos)
CREATE TABLE perfiles (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(32) NOT NULL,
  descripcion VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_perfiles_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO perfiles (id, nombre, descripcion) VALUES
  (1, 'administrador', 'Gestión de usuarios, condiciones del salón, reservas e inventario'),
  (2, 'residente', 'Reservas del salón, consulta de inventario y avisos de administración'),
  (3, 'supervisor', 'Consulta de reservas, inventario y avisos; apoyo a la administración');

-- Usuarios del sistema (solo administrador puede alta/baja/activación vía aplicación)
CREATE TABLE usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  email VARCHAR(128) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  perfil_id TINYINT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_username (username),
  UNIQUE KEY uk_usuarios_email (email),
  KEY idx_usuarios_perfil (perfil_id),
  KEY idx_usuarios_activo (activo),
  CONSTRAINT fk_usuarios_perfil
    FOREIGN KEY (perfil_id) REFERENCES perfiles (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario inicial (SOLO DESARROLLO): admin / password
-- Cambie la contraseña en producción (p. ej. password_hash en PHP o panel).
INSERT INTO usuarios (username, email, password_hash, perfil_id, activo) VALUES
  ('admin', 'admin@localhost', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1);

-- Categorías opcionales para clasificar insumos
CREATE TABLE categorias_insumo (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT,
  PRIMARY KEY (id),
  UNIQUE KEY uk_cat_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo de insumos (existencias / ítems de inventario)
CREATE TABLE insumos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50) NOT NULL,
  nombre VARCHAR(200) NOT NULL,
  descripcion TEXT,
  categoria_id INT UNSIGNED DEFAULT NULL,
  unidad_medida VARCHAR(20) NOT NULL DEFAULT 'unidad',
  stock_actual DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  stock_minimo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  ubicacion VARCHAR(100) DEFAULT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_insumos_codigo (codigo),
  KEY idx_insumos_categoria (categoria_id),
  KEY idx_insumos_activo (activo),
  CONSTRAINT fk_insumos_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias_insumo (id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relaciones entre insumos: define vínculos semánticos entre dos ítems del inventario
-- insumo_origen: punto de vista del vínculo (ej. "kit A" relacionado con "tornillo B")
-- insumo_destino: el otro extremo
-- tipo_relacion: significado del vínculo (documentado en CHECK / ENUM)
CREATE TABLE relaciones_insumo (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  insumo_origen_id INT UNSIGNED NOT NULL COMMENT 'Insumo desde el que se declara la relación',
  insumo_destino_id INT UNSIGNED NOT NULL COMMENT 'Insumo relacionado en el otro extremo',
  tipo_relacion ENUM(
    'complemento',
    'sustituto',
    'componente_de',
    'incompatible',
    'asociado'
  ) NOT NULL DEFAULT 'asociado',
  cantidad_referencia DECIMAL(12,4) DEFAULT NULL COMMENT 'Cantidad del destino por unidad de origen si aplica',
  notas TEXT,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_relacion_par_tipo (insumo_origen_id, insumo_destino_id, tipo_relacion),
  KEY idx_rel_destino (insumo_destino_id),
  KEY idx_rel_tipo (tipo_relacion),
  CONSTRAINT fk_rel_origen
    FOREIGN KEY (insumo_origen_id) REFERENCES insumos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_rel_destino
    FOREIGN KEY (insumo_destino_id) REFERENCES insumos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_rel_distintos CHECK (insumo_origen_id <> insumo_destino_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Política del salón: anticipación mínima para reservar (horas). Editable por administrador vía API/panel.
CREATE TABLE salon_config (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  anticipacion_horas INT UNSIGNED NOT NULL DEFAULT 48 COMMENT 'Horas mínimas de anticipación para una reserva',
  texto_condiciones TEXT DEFAULT NULL COMMENT 'Condiciones de uso visibles para residentes',
  CONSTRAINT chk_salon_config_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO salon_config (id, anticipacion_horas, texto_condiciones) VALUES
  (1, 48,
   'Las reservas deben solicitarse con la anticipación indicada por administración. El uso del salón queda sujeto a confirmación. Respete horarios, aforo y el cuidado del mobiliario e insumos.');

-- Reservas del salón social (evita solapes entre pendiente/confirmada en la aplicación)
CREATE TABLE reservas_salon (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  inicio DATETIME NOT NULL,
  fin DATETIME NOT NULL,
  motivo VARCHAR(500) NOT NULL,
  estado ENUM('pendiente', 'confirmada', 'cancelada', 'rechazada') NOT NULL DEFAULT 'pendiente',
  notas_admin VARCHAR(500) DEFAULT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reservas_inicio (inicio),
  KEY idx_reservas_estado (estado),
  KEY idx_reservas_usuario (usuario_id),
  CONSTRAINT fk_reservas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_reservas_fin_después_inicio CHECK (fin > inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comunicados de administración a residentes
CREATE TABLE comunicados (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(200) NOT NULL,
  cuerpo TEXT NOT NULL,
  creado_por_id INT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comunicados_creado (creado_en),
  CONSTRAINT fk_comunicados_usuario
    FOREIGN KEY (creado_por_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventario orientado al salón (ejemplos)
INSERT INTO categorias_insumo (nombre, descripcion) VALUES
  ('Mobiliario', 'Sillas, mesas y elementos fijos o movibles del salón'),
  ('Insumos y servicio', 'Mantelería, vajilla, decoración y consumibles del evento');

INSERT INTO insumos (codigo, nombre, descripcion, categoria_id, unidad_medida, stock_actual, stock_minimo, ubicacion, activo) VALUES
  ('SAL-SILLA', 'Sillas apilables', 'Sillas para eventos en el salón', 1, 'unidad', 80, 60, 'Salón social — almacén lateral', 1),
  ('SAL-MESA', 'Mesas rectangulares', 'Mesas para montaje de eventos', 1, 'unidad', 20, 12, 'Salón social — almacén lateral', 1),
  ('SAL-MANTEL', 'Manteles estándar', 'Tela lavable tamaño mesa rectangular', 2, 'unidad', 24, 18, 'Salón social — armario', 1),
  ('SAL-VAJILLA', 'Sets vajilla básica', 'Plato, cubiertos y vaso por persona (set)', 2, 'set', 100, 40, 'Salón social — cocineta', 1);

INSERT INTO relaciones_insumo (insumo_origen_id, insumo_destino_id, tipo_relacion, cantidad_referencia, notas) VALUES
  (2, 1, 'complemento', 8.0000, 'Referencia orientativa: ~8 sillas por mesa rectangular'),
  (2, 3, 'complemento', 1.0000, 'Un mantel por mesa'),
  (1, 4, 'asociado', NULL, 'Coordinar cantidad de sets con sillas ocupadas');
