<?php

namespace App\Models\Auth;

use PDO;

class AuthModel {
    private PDO $db;

    public function __construct(PDO $databaseConnection) {
        $this->db = $databaseConnection;
    }

    /**
     * Busca un usuario activo por email o cédula.
     */
    public function obtenerPorEmailOUsuario(string $identificador): array|false {
        $sql = "SELECT id, nombre, apellido, cedula, rol, email, contrasena, estado 
                FROM user 
                WHERE (email = :identificador OR cedula = :identificador)
                  AND estado = 'Activo'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':identificador', $identificador, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Registra un nuevo usuario aplicando password_hash en 'contrasena'.
     */
    public function crearUsuario(array $datos): bool {
        $sql = "INSERT INTO user (nombre, apellido, cedula, numero_telefono, rol, email, contrasena, estado) 
                VALUES (:nombre, :apellido, :cedula, :telefono, :rol, :email, :contrasena, 'Activo')";

        $stmt = $this->db->prepare($sql);

        $passwordHash = password_hash($datos['contrasena'], PASSWORD_BCRYPT);

        $stmt->bindValue(':nombre', $datos['nombre'], PDO::PARAM_STR);
        $stmt->bindValue(':apellido', $datos['apellido'], PDO::PARAM_STR);
        $stmt->bindValue(':cedula', $datos['cedula'], PDO::PARAM_STR);
        $stmt->bindValue(':telefono', $datos['numero_telefono'] ?? null, PDO::PARAM_STR);
        $stmt->bindValue(':rol', $datos['rol'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $datos['email'], PDO::PARAM_STR);
        $stmt->bindValue(':contrasena', $passwordHash, PDO::PARAM_STR);

        return $stmt->execute();
    }
}