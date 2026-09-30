<?php
session_start();
header('Content-Type: application/json');

// Configuración correcta según tu docker-compose.yml
$host     = 'db';
$dbname   = 'fitpowerbd';
$dbuser   = 'root';
$dbpass   = 'root_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error de conexión a la BD: ' . $e->getMessage()
    ]);
    exit;
}

// Leer payload enviado desde el fetch
$datos = json_decode(file_get_contents('php://input'), true);

$email    = trim($datos['email'] ?? '');
$password = trim($datos['password'] ?? '');
$rol      = trim($datos['rol'] ?? 'socio');

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Completa todos los campos']);
    exit;
}

// CAMBIO: solo usuarios Activos pueden entrar
$stmt = $pdo->prepare("SELECT * FROM user WHERE email = :email AND LOWER(rol) = LOWER(:rol) AND estado = 'Activo' LIMIT 1");
$stmt->execute([
    'email' => $email,
    'rol'   => $rol
]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario) {
    // Comprueba tanto si está en texto plano como si está hasheada
    $passwordCorrecta = ($password === $usuario['contrasena']) || password_verify($password, $usuario['contrasena']);

    if ($passwordCorrecta) {
        // CAMBIO: guardar la sesión para que admin.php sepa quién es
        session_regenerate_id(true);
        $_SESSION['id']  = $usuario['id'];
        $_SESSION['rol'] = strtolower($usuario['rol']);

        // CAMBIO: marcar "en línea" (se ignora si aún no corriste la migración)
        try { $pdo->prepare("UPDATE user SET ultima_actividad = NOW() WHERE id = ?")->execute([$usuario['id']]); } catch (PDOException $e) {}

        http_response_code(200);
        echo json_encode([
            'status'  => 'success',
            'message' => '¡Login correcto!',
            'data'    => [
                'id'     => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email'  => $usuario['email'],
                'rol'    => strtolower($usuario['rol'])
            ]
        ]);
        exit;
    }
}

// Credenciales o rol incorrectos
http_response_code(401);
echo json_encode([
    'status'  => 'error',
    'message' => 'Email, contraseña o rol incorrectos'
]);
