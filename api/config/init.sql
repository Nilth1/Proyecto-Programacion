-- ========================================================
-- SCRIPT DE CREACIÓN: SISTEMA DE GESTIÓN FITPOWER
-- ========================================================

-- ========================================================
-- 1. CREACIÓN DE TABLAS (DDL)
-- ========================================================

-- --------------------------------------------------------
-- TABLA: user
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
-- Relaciona rutinas con ejercicios
-- --------------------------------------------------------

CREATE TABLE rutina_ejercicio (
    id_rutina INT NOT NULL,
    id_ejercicio INT NOT NULL,
    repeticiones INT NULL,
    orden INT NULL,
    tiempo_descanso INT NULL,

    PRIMARY KEY (id_rutina, id_ejercicio),

    FOREIGN KEY (id_rutina)
        REFERENCES rutinas(id),

    FOREIGN KEY (id_ejercicio)
        REFERENCES ejercicios(id)
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

CREATE TABLE premios_competencia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competencia_id INT NOT NULL,
    premio_id INT NOT NULL,
    puesto INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    FOREIGN KEY (competencia_id) REFERENCES competencias(id_competencia),
    FOREIGN KEY (premio_id) REFERENCES inventario_premios(id)
);

-- ========================================================
-- 2. POBLACIÓN DE DATOS
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