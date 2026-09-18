<?php

namespace App\Controllers;

use App\Models\Auth\AuthModel;

class AuthController {
    private $db;
    private $model;

    public function __construct($db) { 
        $this->db = $db;
        $this->model = new AuthModel($db);
    }

    public function login(): void {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $identificador = trim($input['identificador'] ?? $input['email'] ?? '');
        $password = trim($input['password'] ?? $input['contrasena'] ?? '');

        if (empty($identificador) || empty($password)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Por favor, completa todos los campos.'
            ]);
            return;
        }

        $usuario = $this->model->obtenerPorEmailOUsuario($identificador);

        if ($usuario && password_verify($password, $usuario['contrasena'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);

            $_SESSION['user_id'] = $usuario['id'];
            $_SESSION['nombre'] = $usuario['nombre'];
            $_SESSION['apellido'] = $usuario['apellido'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['activo'] = true;

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Inicio de sesión exitoso.',
                'data' => [
                    'id' => $usuario['id'],
                    'nombre' => $usuario['nombre'],
                    'apellido' => $usuario['apellido'],
                    'rol' => $usuario['rol']
                ]
            ]);
            return;
        }

        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Usuario o contraseña incorrectos.'
        ]);
    }

    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Sesión cerrada correctamente.'
        ]);
    }
}