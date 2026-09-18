<?php

namespace App\Middleware;

class AuthMiddleware {

    public static function verificarAutenticacion(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['activo']) || empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'No autorizado. Inicie sesión.'
            ]);
            exit;
        }
    }

    public static function verificarRol(array $rolesPermitidos): void {
        self::verificarAutenticacion();

        if (!in_array($_SESSION['rol'] ?? '', $rolesPermitidos, true)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Acceso denegado: permisos insuficientes.'
            ]);
            exit;
        }
    }
}