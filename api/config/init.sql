-- ========================================================
-- SCRIPT DE CREACIÓN: SISTEMA DE GESTIÓN FITPOWER
-- Incluye todos los cambios de los paneles de admin, socio y entrenador
-- (equivale a init.sql original + migracion_admin + migracion_socio
--  + migracion_entrenador + datos_ejemplo)
-- Se ejecuta solo cuando el volumen de MySQL está vacío.
-- ========================================================

-- ========================================================
-- 1. CREACIÓN DE TABLAS (DDL)
-- ========================================================

-- --------------------------------------------------------
-- TABLA: user
-- especialidad  -> solo entrenadores
-- objetivo, id_entrenador, id_plan -> solo socios
-- ultima_actividad -> para saber quién está en línea
-- --------------------------------------------------------

CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    cedula VARCHAR(20) NOT NULL,
    numero_telefono VARCHAR(20) NULL,
    huella_dactilar VARCHAR(255) NULL,
    rol VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    ultima_actividad DATETIME NULL,
    especialidad VARCHAR(100) NULL,
    objetivo VARCHAR(100) NULL,
    id_entrenador INT NULL,
    id_plan INT NULL,

    UNIQUE (cedula),
    UNIQUE (email)
);


-- --------------------------------------------------------
-- TABLA: ejercicios
-- --------------------------------------------------------

CREATE TABLE ejercicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    repeticiones INT NOT NULL,
    series INT NOT NULL,
    dificultad VARCHAR(50) NOT NULL,
    grupo_muscular VARCHAR(100) NOT NULL,
    tipo_ejercicio VARCHAR(50) NOT NULL,
    info_ejercicio VARCHAR(500) NULL,
    media_ejercicio VARCHAR(255) NULL,
    destaca BOOLEAN DEFAULT FALSE
);


-- --------------------------------------------------------
-- TABLA: rutinas
-- --------------------------------------------------------

CREATE TABLE rutinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    dificultad ENUM('Principiante', 'Intermedio', 'Avanzado') NOT NULL,
    grupo_muscular VARCHAR(100) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);


-- --------------------------------------------------------
-- TABLA: rutina_ejercicio
-- Relaciona rutinas con ejercicios (series y repeticiones propias de cada rutina)
-- --------------------------------------------------------

CREATE TABLE rutina_ejercicio (
    id_rutina INT NOT NULL,
    id_ejercicio INT NOT NULL,
    repeticiones INT NULL,
    series INT NULL,
    orden INT NULL,
    tiempo_descanso INT NULL,

    PRIMARY KEY (id_rutina, id_ejercicio),

    FOREIGN KEY (id_rutina)
        REFERENCES rutinas(id),

    FOREIGN KEY (id_ejercicio)
        REFERENCES ejercicios(id)
);


-- --------------------------------------------------------
-- TABLA: rutina_asignada
-- Rutinas que el entrenador le asigna a cada socio
-- --------------------------------------------------------

CREATE TABLE rutina_asignada (
    id_user INT NOT NULL,
    id_rutina INT NOT NULL,
    id_entrenador INT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_user, id_rutina),

    FOREIGN KEY (id_user)
        REFERENCES user(id),

    FOREIGN KEY (id_rutina)
        REFERENCES rutinas(id),

    FOREIGN KEY (id_entrenador)
        REFERENCES user(id)
);


-- --------------------------------------------------------
-- TABLA: admin
-- --------------------------------------------------------

CREATE TABLE admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    nivel_acceso INT NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_usuario)
        REFERENCES user(id)
);


-- --------------------------------------------------------
-- TABLA: parking
-- --------------------------------------------------------

CREATE TABLE parking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    espacios_disponibles INT NOT NULL,
    espacios_ocupados INT NOT NULL,
    matriculas_vehiculo VARCHAR(20) NULL
);


-- --------------------------------------------------------
-- TABLA: inventario_premios
-- --------------------------------------------------------

CREATE TABLE inventario_premios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    cantidad INT NOT NULL DEFAULT 0,
    estado ENUM('Disponible', 'Agotado', 'Inactivo') DEFAULT 'Disponible'
);


-- --------------------------------------------------------
-- TABLA: competencias
-- --------------------------------------------------------

CREATE TABLE competencias (
    id_competencia INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    fecha DATE NOT NULL,
    lugar VARCHAR(100) NULL,
    estado VARCHAR(50) NOT NULL
);


-- --------------------------------------------------------
-- TABLA: inscripciones
-- Relaciona usuarios con competencias
-- --------------------------------------------------------

CREATE TABLE inscripciones (
    id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_competencia INT NOT NULL,

    FOREIGN KEY (id_user)
        REFERENCES user(id),

    FOREIGN KEY (id_competencia)
        REFERENCES competencias(id_competencia)
);


-- --------------------------------------------------------
-- TABLA: premios_competencia
-- Relaciona competencias con sus premios
-- --------------------------------------------------------

CREATE TABLE premios_competencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    premio_id INT NOT NULL,
    puesto INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,

    FOREIGN KEY (competencia_id)
        REFERENCES competencias(id_competencia),

    FOREIGN KEY (premio_id)
        REFERENCES inventario_premios(id)
);


-- --------------------------------------------------------
-- TABLA: funcional_futbol
-- --------------------------------------------------------

CREATE TABLE funcional_futbol (
    id_funcional INT AUTO_INCREMENT PRIMARY KEY,
    equipo VARCHAR(100) NOT NULL,
    lugar VARCHAR(100) NOT NULL
);


-- --------------------------------------------------------
-- TABLA: inscripciones_funcional
-- Relaciona usuarios con funcional/fútbol
-- --------------------------------------------------------

CREATE TABLE inscripciones_funcional (
    id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    id_funcional INT NOT NULL,

    FOREIGN KEY (id_user)
        REFERENCES user(id),

    FOREIGN KEY (id_funcional)
        REFERENCES funcional_futbol(id_funcional)
);


-- --------------------------------------------------------
-- TABLA: cuotas
-- --------------------------------------------------------

CREATE TABLE cuotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_pago DATE NULL,
    fecha_vencimiento DATE NOT NULL,
    estado ENUM('Pagada', 'Pendiente', 'Vencida') DEFAULT 'Pendiente',

    FOREIGN KEY (usuario_id)
        REFERENCES user(id)
);


-- --------------------------------------------------------
-- TABLA: planes
-- Planes que el socio puede comprar
-- --------------------------------------------------------

CREATE TABLE planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    descripcion VARCHAR(255) NULL
);


-- --------------------------------------------------------
-- TABLA: clases
-- --------------------------------------------------------

CREATE TABLE clases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    id_entrenador INT NULL,
    dia_hora DATETIME NOT NULL,
    cupo INT NOT NULL DEFAULT 20,

    FOREIGN KEY (id_entrenador)
        REFERENCES user(id)
);


-- --------------------------------------------------------
-- TABLA: inscripciones_clase
-- Relaciona socios con clases
-- --------------------------------------------------------

CREATE TABLE inscripciones_clase (
    id_user INT NOT NULL,
    id_clase INT NOT NULL,

    PRIMARY KEY (id_user, id_clase),

    FOREIGN KEY (id_user)
        REFERENCES user(id),

    FOREIGN KEY (id_clase)
        REFERENCES clases(id)
);


-- --------------------------------------------------------
-- TABLA: quejas
-- Diario privado: cada fila solo la ve el socio que la escribió
-- --------------------------------------------------------

CREATE TABLE quejas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    texto TEXT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (id_user)
        REFERENCES user(id)
);


-- ========================================================
-- 2. POBLACIÓN DE DATOS
-- Contraseña de todos los usuarios de prueba: 1234 (admin: 123456)
-- ========================================================

-- --------------------------------------------------------
-- PREMIOS DEL INVENTARIO
-- --------------------------------------------------------

INSERT INTO inventario_premios
(nombre, descripcion, cantidad)
VALUES
('Gatorade', 'Bebida deportiva', 30),
('Pack Gatorade', 'Pack de bebidas deportivas', 10),
('Proteína Whey', 'Suplemento de proteína en polvo', 8),
('Creatina Monohidratada', 'Suplemento de creatina', 12),
('Barras Proteicas', 'Barras con alto contenido de proteína', 25),
('Pre-entreno', 'Suplemento pre-entrenamiento', 6),
('Multivitamínico', 'Suplemento de vitaminas y minerales', 10),
('Shaker FitPower', 'Vaso mezclador para suplementos', 15),
('Botella Deportiva', 'Botella reutilizable', 20),
('Remera FitPower', 'Remera deportiva oficial', 10),
('Toalla Deportiva', 'Toalla deportiva con logo FitPower', 8),
('Mochila Deportiva', 'Mochila para entrenamiento', 5);

-- --------------------------------------------------------
-- PLANES
-- --------------------------------------------------------

INSERT INTO planes (nombre, precio, descripcion) VALUES
('Básico',  25, 'Acceso a sala de musculación en horario libre'),
('Pro',     40, 'Sala + clases grupales ilimitadas'),
('Premium', 60, 'Todo Pro + seguimiento personalizado con entrenador');

-- --------------------------------------------------------
-- USUARIOS: administrador, socio y entrenador originales
-- --------------------------------------------------------

INSERT INTO user
(nombre, apellido, cedula, numero_telefono, huella_dactilar, rol, email, contrasena, fecha_registro, estado, especialidad)
VALUES
('Admin',   'FitPower',  '12345678', '099123456', NULL, 'Administrador', 'admin@fitpower.com',   '123456', CURDATE(), 'Activo', NULL),
('Lucas',   'Abestia',   '57654321', '099876543', NULL, 'Socio',         'lucas@gmail.com',      '1234',   CURDATE(), 'Activo', NULL),
('Sofiav',  'Rodriguez', '41223344', '099112233', NULL, 'Entrenador',    'SofiaE@fitpower.com',  '1234',   CURDATE(), 'Activo', 'Ganar masa muscular');

-- --------------------------------------------------------
-- ENTRENADORES (uno por objetivo)
-- --------------------------------------------------------

INSERT INTO user (nombre, apellido, cedula, numero_telefono, rol, email, contrasena, estado, especialidad) VALUES
('Martín',  'Gómez',    '45123456', '099111001', 'Entrenador', 'martin@fitpower.com',  '1234', 'Activo', 'Perder peso'),
('Lucía',   'Bravo',    '47234567', '099111002', 'Entrenador', 'lucia@fitpower.com',   '1234', 'Activo', 'Tonificar'),
('Joaquín', 'Ríos',     '46345678', '099111003', 'Entrenador', 'joaquin@fitpower.com', '1234', 'Activo', 'Resistencia y cardio'),
('Valeria', 'Cabrera',  '44456789', '099111004', 'Entrenador', 'valeria@fitpower.com', '1234', 'Activo', 'Rehabilitación'),
('Nicolás', 'Ferreira', '48567890', '099111005', 'Entrenador', 'nicolas@fitpower.com', '1234', 'Activo', 'Ganar masa muscular');

-- --------------------------------------------------------
-- SOCIOS
-- --------------------------------------------------------

INSERT INTO user (nombre, apellido, cedula, numero_telefono, rol, email, contrasena, estado) VALUES
('Camila',    'Rossi',  '50111222', '099222001', 'Socio', 'camila@gmail.com',    '1234', 'Activo'),
('Mateo',     'Silva',  '50222333', '099222002', 'Socio', 'mateo@gmail.com',     '1234', 'Activo'),
('Valentina', 'Cruz',   '50333444', '099222003', 'Socio', 'valentina@gmail.com', '1234', 'Activo'),
('Diego',     'Núñez',  '50444555', '099222004', 'Socio', 'diego@gmail.com',     '1234', 'Activo'),
('Sofía',     'Méndez', '50555666', '099222005', 'Socio', 'sofia@gmail.com',     '1234', 'Activo');

-- Plan, objetivo y entrenador de cada socio
UPDATE user SET id_plan = 2, objetivo = 'Ganar masa muscular',  id_entrenador = (SELECT id FROM (SELECT id FROM user WHERE email='nicolas@fitpower.com') t)  WHERE email = 'lucas@gmail.com';
UPDATE user SET id_plan = 2, objetivo = 'Tonificar',            id_entrenador = (SELECT id FROM (SELECT id FROM user WHERE email='lucia@fitpower.com') t)    WHERE email = 'camila@gmail.com';
UPDATE user SET id_plan = 1, objetivo = 'Perder peso',          id_entrenador = (SELECT id FROM (SELECT id FROM user WHERE email='martin@fitpower.com') t)   WHERE email = 'mateo@gmail.com';
UPDATE user SET id_plan = 1, objetivo = 'Resistencia y cardio', id_entrenador = (SELECT id FROM (SELECT id FROM user WHERE email='joaquin@fitpower.com') t)  WHERE email = 'valentina@gmail.com';
UPDATE user SET id_plan = 3, objetivo = 'Rehabilitación',       id_entrenador = (SELECT id FROM (SELECT id FROM user WHERE email='valeria@fitpower.com') t)  WHERE email = 'diego@gmail.com';
UPDATE user SET id_plan = 1 WHERE email = 'sofia@gmail.com';

-- --------------------------------------------------------
-- CUOTAS (semáforo y finanzas con datos)
-- Última cuota: Lucas, Camila y Diego al día · Mateo pendiente
-- Valentina vencida · Sofía sin cuotas
-- --------------------------------------------------------

INSERT INTO cuotas (usuario_id, monto, fecha_pago, fecha_vencimiento, estado) VALUES
-- Historial de meses anteriores (pagadas)
((SELECT id FROM user WHERE email='lucas@gmail.com'),  40, CURDATE() - INTERVAL 4 MONTH, CURDATE() - INTERVAL 4 MONTH + INTERVAL 5 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='lucas@gmail.com'),  40, CURDATE() - INTERVAL 3 MONTH, CURDATE() - INTERVAL 3 MONTH + INTERVAL 5 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='lucas@gmail.com'),  40, CURDATE() - INTERVAL 2 MONTH, CURDATE() - INTERVAL 2 MONTH + INTERVAL 5 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='lucas@gmail.com'),  40, CURDATE() - INTERVAL 1 MONTH, CURDATE() - INTERVAL 1 MONTH + INTERVAL 5 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='camila@gmail.com'), 40, CURDATE() - INTERVAL 3 MONTH, CURDATE() - INTERVAL 3 MONTH + INTERVAL 5 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='camila@gmail.com'), 40, CURDATE() - INTERVAL 2 MONTH, CURDATE() - INTERVAL 2 MONTH + INTERVAL 5 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='camila@gmail.com'), 40, CURDATE() - INTERVAL 1 MONTH, CURDATE() - INTERVAL 1 MONTH + INTERVAL 5 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='diego@gmail.com'),  60, CURDATE() - INTERVAL 2 MONTH, CURDATE() - INTERVAL 2 MONTH + INTERVAL 5 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='diego@gmail.com'),  60, CURDATE() - INTERVAL 1 MONTH, CURDATE() - INTERVAL 1 MONTH + INTERVAL 5 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='mateo@gmail.com'),  25, CURDATE() - INTERVAL 1 MONTH, CURDATE() - INTERVAL 1 MONTH + INTERVAL 5 DAY, 'Pagada'),
-- Cuota del mes actual (define el color del semáforo)
((SELECT id FROM user WHERE email='lucas@gmail.com'),     40, CURDATE(), CURDATE() + INTERVAL 25 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='camila@gmail.com'),    40, CURDATE(), CURDATE() + INTERVAL 25 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='diego@gmail.com'),     60, CURDATE(), CURDATE() + INTERVAL 25 DAY, 'Pagada'),
((SELECT id FROM user WHERE email='mateo@gmail.com'),     25, NULL,      CURDATE() + INTERVAL 5 DAY,  'Pendiente'),
((SELECT id FROM user WHERE email='valentina@gmail.com'), 25, NULL,      CURDATE() - INTERVAL 10 DAY, 'Vencida');

-- --------------------------------------------------------
-- EJERCICIOS
-- --------------------------------------------------------

INSERT INTO ejercicios (nombre, repeticiones, series, dificultad, grupo_muscular, tipo_ejercicio, info_ejercicio) VALUES
('Sentadilla goblet',         12, 3, 'Principiante', 'Pierna',    'Fuerza',         'Sostené una mancuerna frente al pecho y bajá con la espalda recta.'),
('Flexiones de brazos',       10, 3, 'Principiante', 'Pecho',     'Fuerza',         'Cuerpo en línea recta, bajá hasta casi tocar el suelo.'),
('Remo con mancuerna',        12, 3, 'Principiante', 'Espalda',   'Fuerza',         'Apoyá una mano en el banco y llevá la mancuerna hacia la cadera.'),
('Plancha abdominal',         30, 3, 'Principiante', 'Core',      'Isometría',      'Mantené la posición 30 segundos sin arquear la espalda.'),
('Press banca',                8, 4, 'Intermedio',   'Pecho',     'Fuerza',         'Bajá la barra al pecho de forma controlada y empujá.'),
('Aperturas con mancuernas',  12, 3, 'Intermedio',   'Pecho',     'Fuerza',         'Brazos ligeramente flexionados, abrí y cerrá en arco.'),
('Fondos en banco',           12, 3, 'Intermedio',   'Tríceps',   'Fuerza',         'Manos en el banco, bajá flexionando los codos.'),
('Dominadas asistidas',        8, 4, 'Intermedio',   'Espalda',   'Fuerza',         'Usá la máquina de asistencia y subí hasta pasar el mentón.'),
('Curl de bíceps',            12, 3, 'Intermedio',   'Bíceps',    'Fuerza',         'Codos pegados al cuerpo, sin balancear el torso.'),
('Sentadilla con barra',       5, 5, 'Avanzado',     'Pierna',    'Fuerza',         'Barra apoyada en la espalda alta, bajá hasta paralelo.'),
('Peso muerto rumano',         8, 4, 'Avanzado',     'Pierna',    'Fuerza',         'Cadera hacia atrás con las piernas casi rectas.'),
('Puente de glúteos',         12, 4, 'Intermedio',   'Glúteos',   'Fuerza',         'Empujá la cadera hacia arriba y apretá arriba un segundo.'),
('Burpees',                   15, 5, 'Intermedio',   'Cardio',    'Cardio',         'Sentadilla, plancha, flexión y salto en un solo movimiento.'),
('Saltos de soga',            60, 6, 'Intermedio',   'Cardio',    'Cardio',         'Sesenta segundos de saltos continuos por serie.'),
('Mountain climbers',         30, 4, 'Intermedio',   'Cardio',    'Cardio',         'En posición de plancha, llevá las rodillas al pecho rápido.'),
('Movilidad de cadera',       10, 3, 'Principiante', 'Movilidad', 'Movilidad',      'Círculos amplios y controlados con cada pierna.'),
('Rotación de hombros',       12, 3, 'Principiante', 'Movilidad', 'Movilidad',      'Con banda elástica, rotá hacia afuera sin dolor.'),
('Elevación de talones',      15, 3, 'Principiante', 'Pierna',    'Rehabilitación', 'Subí y bajá lentamente sobre la punta de los pies.');

-- --------------------------------------------------------
-- RUTINAS
-- --------------------------------------------------------

INSERT INTO rutinas (nombre, descripcion, dificultad, grupo_muscular) VALUES
('Full Body Principiante',     'Rutina de cuerpo completo para empezar',            'Principiante', 'Cuerpo completo'),
('Pecho y Tríceps',            'Empuje: pecho, hombros y tríceps',                  'Intermedio',   'Pecho'),
('Espalda y Bíceps',           'Tirón: espalda y bíceps',                           'Intermedio',   'Espalda'),
('Pierna y Glúteos',           'Fuerza de tren inferior',                           'Avanzado',     'Pierna'),
('HIIT Quema Grasa',           'Intervalos de alta intensidad para bajar de peso',  'Intermedio',   'Cardio'),
('Movilidad y Rehabilitación', 'Ejercicios suaves para recuperar movimiento',       'Principiante', 'Movilidad');

-- --------------------------------------------------------
-- VÍNCULO RUTINA <-> EJERCICIO (por nombre, sin depender de los IDs)
-- --------------------------------------------------------

CREATE TEMPORARY TABLE tmp_link (
    rutina VARCHAR(100), ejercicio VARCHAR(100), series INT, reps INT, orden INT, descanso INT
);

INSERT INTO tmp_link VALUES
('Full Body Principiante',     'Sentadilla goblet',        3, 12, 1, 60),
('Full Body Principiante',     'Flexiones de brazos',      3, 10, 2, 60),
('Full Body Principiante',     'Remo con mancuerna',       3, 12, 3, 60),
('Full Body Principiante',     'Plancha abdominal',        3, 30, 4, 45),
('Pecho y Tríceps',            'Press banca',              4,  8, 1, 90),
('Pecho y Tríceps',            'Aperturas con mancuernas', 3, 12, 2, 60),
('Pecho y Tríceps',            'Fondos en banco',          3, 12, 3, 60),
('Pecho y Tríceps',            'Flexiones de brazos',      3, 15, 4, 45),
('Espalda y Bíceps',           'Dominadas asistidas',      4,  8, 1, 90),
('Espalda y Bíceps',           'Remo con mancuerna',       3, 12, 2, 60),
('Espalda y Bíceps',           'Curl de bíceps',           3, 12, 3, 60),
('Pierna y Glúteos',           'Sentadilla con barra',     5,  5, 1, 120),
('Pierna y Glúteos',           'Peso muerto rumano',       4,  8, 2, 90),
('Pierna y Glúteos',           'Puente de glúteos',        4, 12, 3, 60),
('HIIT Quema Grasa',           'Burpees',                  5, 15, 1, 30),
('HIIT Quema Grasa',           'Saltos de soga',           6, 60, 2, 30),
('HIIT Quema Grasa',           'Mountain climbers',        4, 30, 3, 30),
('Movilidad y Rehabilitación', 'Movilidad de cadera',      3, 10, 1, 30),
('Movilidad y Rehabilitación', 'Rotación de hombros',      3, 12, 2, 30),
('Movilidad y Rehabilitación', 'Elevación de talones',     3, 15, 3, 30);

INSERT INTO rutina_ejercicio (id_rutina, id_ejercicio, series, repeticiones, orden, tiempo_descanso)
SELECT r.id, e.id, t.series, t.reps, t.orden, t.descanso
FROM tmp_link t
JOIN rutinas r    ON r.nombre = t.rutina
JOIN ejercicios e ON e.nombre = t.ejercicio;

DROP TEMPORARY TABLE tmp_link;

-- --------------------------------------------------------
-- RUTINAS ASIGNADAS: Lucas recibe todas para probar la vista de socio
-- --------------------------------------------------------

INSERT INTO rutina_asignada (id_user, id_rutina)
SELECT u.id, r.id FROM user u JOIN rutinas r WHERE u.email = 'lucas@gmail.com';

-- --------------------------------------------------------
-- CLASES (próximos días)
-- --------------------------------------------------------

INSERT INTO clases (nombre, id_entrenador, dia_hora, cupo) VALUES
('Funcional',      (SELECT id FROM user WHERE email='SofiaE@fitpower.com'),  NOW() + INTERVAL 1 DAY,                   15),
('Spinning',       (SELECT id FROM user WHERE email='SofiaE@fitpower.com'),  NOW() + INTERVAL 2 DAY,                   12),
('Yoga',           (SELECT id FROM user WHERE email='SofiaE@fitpower.com'),  NOW() + INTERVAL 3 DAY,                   20),
('Cross Training', (SELECT id FROM user WHERE email='martin@fitpower.com'),  NOW() + INTERVAL 1 DAY + INTERVAL 2 HOUR, 15),
('Pilates',        (SELECT id FROM user WHERE email='lucia@fitpower.com'),   NOW() + INTERVAL 2 DAY + INTERVAL 3 HOUR, 12),
('Running Grupal', (SELECT id FROM user WHERE email='joaquin@fitpower.com'), NOW() + INTERVAL 3 DAY + INTERVAL 1 HOUR, 20),
('Movilidad',      (SELECT id FROM user WHERE email='valeria@fitpower.com'), NOW() + INTERVAL 4 DAY + INTERVAL 4 HOUR, 10),
('Fuerza Total',   (SELECT id FROM user WHERE email='nicolas@fitpower.com'), NOW() + INTERVAL 5 DAY + INTERVAL 2 HOUR, 15);