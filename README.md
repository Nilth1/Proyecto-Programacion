# Proyecto-Programacion
Proyecto-Programacion

# FitPower

Sistema de gestión de gimnasio. PHP 8.2 + MySQL 8 + Docker.

## Cómo levantarlo
1. Tener Docker Desktop abierto.
2. En la carpeta del proyecto, ejecutar:
   Copy-Item .env.example .env
   docker compose down -v
   docker compose up -d --build
3. Esperar 30 segundos (MySQL tarda en arrancar la primera vez).
4. Entrar a http://localhost:8080

## Usuarios de prueba
- Administrador: admin@fitpower.com / 123456
- Socio (Elite): lucas@gmail.com / 1234
- Socio (Power): diego@gmail.com / 1234
- Socio (Basic): mateo@gmail.com / 1234
- Entrenador: nicolas@fitpower.com / 1234

phpMyAdmin: http://localhost:8081