<?php
// front/admin.php — endpoints del panel de administrador
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($d, $c = 200) { http_response_code($c); echo json_encode($d); exit; }

// ---- Conexión (misma que login.php) ----
try {
    $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASSWORD'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    out(['status' => 'error', 'message' => 'Error de conexión a la BD'], 500);
}

// ---- Sesión ----
if (empty($_SESSION['id'])) out(['status' => 'error', 'code' => 'no_auth', 'message' => 'Sesión no iniciada'], 401);
$accion = $_GET['accion'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?? [];

// Cualquier usuario logueado puede avisar que está en línea
if ($accion === 'ping') {
    $pdo->prepare("UPDATE user SET ultima_actividad = NOW() WHERE id = ?")->execute([$_SESSION['id']]);
    out(['status' => 'success']);
}

// Todo lo demás: solo administrador
if (($_SESSION['rol'] ?? '') !== 'administrador') out(['status' => 'error', 'code' => 'no_auth', 'message' => 'Acceso denegado'], 403);
$pdo->prepare("UPDATE user SET ultima_actividad = NOW() WHERE id = ?")->execute([$_SESSION['id']]);

try {
    switch ($accion) {

    case 'todo':
        $admin = $pdo->prepare("SELECT nombre, apellido FROM user WHERE id = ?");
        $admin->execute([$_SESSION['id']]);

        $personas = function ($rol) use ($pdo) {
            $s = $pdo->prepare("SELECT u.id, u.nombre, u.apellido, u.cedula, u.email,
                    (u.ultima_actividad >= NOW() - INTERVAL 5 MINUTE) AS online,
                    (SELECT c.estado FROM cuotas c WHERE c.usuario_id = u.id ORDER BY c.estado = 'Pagada', c.estado = 'Pendiente', c.id DESC LIMIT 1) AS cuota,
                    (SELECT c.monto  FROM cuotas c WHERE c.usuario_id = u.id ORDER BY c.estado = 'Pagada', c.estado = 'Pendiente', c.id DESC LIMIT 1) AS monto
                FROM user u WHERE LOWER(u.rol) = ? AND u.estado = 'Activo' ORDER BY u.nombre");
            $s->execute([$rol]);
            return array_map(function ($r) { $r['online'] = (bool)$r['online']; return $r; }, $s->fetchAll(PDO::FETCH_ASSOC));
        };

        // Rutinas con sus ejercicios
        $rutinas = $pdo->query("SELECT id, nombre FROM rutinas ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        $ej = $pdo->query("SELECT re.id_rutina, e.id, e.nombre, e.media_ejercicio AS gif, COALESCE(re.series, e.series) AS series, COALESCE(re.repeticiones, e.repeticiones) AS reps
                           FROM rutina_ejercicio re JOIN ejercicios e ON e.id = re.id_ejercicio
                           ORDER BY re.id_rutina, re.orden")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rutinas as &$r) {
            $r['ej'] = [];
            foreach ($ej as $x) if ($x['id_rutina'] == $r['id']) $r['ej'][] = ['id' => $x['id'], 'n' => $x['nombre'], 's' => $x['series'] . ' x ' . $x['reps'], 'g' => $x['gif']];
        }
        unset($r);

        // Finanzas: cobrado por mes (últimos 6) + deuda
        $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $cobr = $pdo->query("SELECT DATE_FORMAT(fecha_pago,'%Y-%m') ym, SUM(monto) t FROM cuotas
                             WHERE estado = 'Pagada' AND fecha_pago >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
                             GROUP BY ym")->fetchAll(PDO::FETCH_KEY_PAIR);
        $serie = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = new DateTime("first day of -$i month");
            $serie[] = ['m' => $meses[(int)$d->format('n') - 1], 'v' => (float)($cobr[$d->format('Y-m')] ?? 0)];
        }
        $deuda = $pdo->query("SELECT estado, SUM(monto) FROM cuotas WHERE estado IN ('Pendiente','Vencida') GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Quejas que escribieron los socios desde su portal (las más nuevas primero)
        $quejas = $pdo->query("SELECT q.id, q.texto, q.fecha, u.nombre, u.apellido, u.email, u.estado
                               FROM quejas q JOIN user u ON u.id = q.id_user
                               ORDER BY q.fecha DESC, q.id DESC")->fetchAll(PDO::FETCH_ASSOC);

        out(['status' => 'success', 'data' => [
            'admin' => $admin->fetch(PDO::FETCH_ASSOC),
            'usuarios' => $personas('socio'),
            'entrenadores' => $personas('entrenador'),
            'rutinas' => $rutinas,
            'fin' => ['meses' => $serie, 'pendiente' => (float)($deuda['Pendiente'] ?? 0), 'vencida' => (float)($deuda['Vencida'] ?? 0)],
            'quejas' => $quejas
        ]]);

    case 'crear_persona':
        $rol = ($in['rol'] ?? '') === 'Entrenador' ? 'Entrenador' : 'Socio';
        foreach (['nombre', 'apellido', 'cedula', 'email', 'contrasena'] as $k)
            if (trim($in[$k] ?? '') === '') out(['status' => 'error', 'message' => 'Completá todos los campos'], 400);
        if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) out(['status' => 'error', 'message' => 'Email inválido'], 400);

        // Al dar de baja a alguien no se lo borra: queda como Inactivo (para no perder su historial).
        // Por eso, si la cédula o el email pertenecen a una persona dada de baja, en vez de decir
        // "ya existe" se la vuelve a dar de alta con los datos nuevos.
        $s = $pdo->prepare("SELECT id, estado FROM user WHERE cedula = ? OR email = ?");
        $s->execute([trim($in['cedula']), trim($in['email'])]);
        $existentes = $s->fetchAll(PDO::FETCH_ASSOC);
        if ($existentes) {
            foreach ($existentes as $e)
                if ($e['estado'] === 'Activo') out(['status' => 'error', 'message' => 'Esa cédula o email ya existe'], 409);
            // La cédula es de una persona dada de baja y el email de otra: no se puede saber cuál reactivar
            if (count($existentes) > 1) out(['status' => 'error', 'message' => 'Esa cédula y ese email pertenecen a dos personas dadas de baja distintas'], 409);
            // Se reactiva como un alta nueva: datos y contraseña nuevos, sin plan, entrenador ni objetivo anteriores
            $pdo->prepare("UPDATE user SET nombre = ?, apellido = ?, cedula = ?, rol = ?, email = ?, contrasena = ?, estado = 'Activo',
                                          id_plan = NULL, id_entrenador = NULL, objetivo = NULL
                           WHERE id = ?")
                ->execute([trim($in['nombre']), trim($in['apellido']), trim($in['cedula']), $rol, trim($in['email']),
                           password_hash($in['contrasena'], PASSWORD_DEFAULT), $existentes[0]['id']]);
            out(['status' => 'success', 'message' => 'Se volvió a dar de alta a la persona']);
        }

        try {
            $pdo->prepare("INSERT INTO user (nombre, apellido, cedula, rol, email, contrasena, estado) VALUES (?,?,?,?,?,?, 'Activo')")
                ->execute([trim($in['nombre']), trim($in['apellido']), trim($in['cedula']), $rol, trim($in['email']), password_hash($in['contrasena'], PASSWORD_DEFAULT)]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) out(['status' => 'error', 'message' => 'Esa cédula o email ya existe'], 409);
            throw $e;
        }
        out(['status' => 'success']);

    case 'editar_persona':
        // Edita nombre, apellido, cédula, email y (opcional) contraseña de un socio o entrenador
        $id = (int)($in['id'] ?? 0);
        foreach (['nombre', 'apellido', 'cedula', 'email'] as $k)
            if (trim($in[$k] ?? '') === '') out(['status' => 'error', 'message' => 'Completá nombre, apellido, cédula y email'], 400);
        if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) out(['status' => 'error', 'message' => 'Email inválido'], 400);
        $sql = "UPDATE user SET nombre = ?, apellido = ?, cedula = ?, email = ?";
        $par = [trim($in['nombre']), trim($in['apellido']), trim($in['cedula']), trim($in['email'])];
        if (trim($in['contrasena'] ?? '') !== '') { $sql .= ", contrasena = ?"; $par[] = password_hash($in['contrasena'], PASSWORD_DEFAULT); }
        $sql .= " WHERE id = ? AND LOWER(rol) IN ('socio','entrenador')";
        $par[] = $id;
        try {
            $pdo->prepare($sql)->execute($par);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) out(['status' => 'error', 'message' => 'Esa cédula o email ya la usa otra persona'], 409);
            throw $e;
        }
        out(['status' => 'success']);

    case 'quitar_persona':
        // Solo socios y entrenadores; se pasa a Inactivo (no se borra el historial)
        $s = $pdo->prepare("UPDATE user SET estado = 'Inactivo' WHERE id = ? AND LOWER(rol) IN ('socio','entrenador')");
        $s->execute([(int)($in['id'] ?? 0)]);
        out(['status' => 'success']);

    case 'borrar_queja':
        // El administrador puede eliminar una queja (por ejemplo, cuando ya la resolvió)
        $pdo->prepare("DELETE FROM quejas WHERE id = ?")->execute([(int)($in['id'] ?? 0)]);
        out(['status' => 'success']);

    case 'agregar_ejercicio':
        $rid = (int)($in['rutina_id'] ?? 0);
        $nombre = trim($in['nombre'] ?? ''); $series = (int)($in['series'] ?? 0); $reps = (int)($in['repeticiones'] ?? 0);
        if ($nombre === '' || $series < 1 || $reps < 1) out(['status' => 'error', 'message' => 'Ejercicio, series y repeticiones válidos'], 400);
        $r = $pdo->prepare("SELECT dificultad, grupo_muscular FROM rutinas WHERE id = ?"); $r->execute([$rid]);
        $rut = $r->fetch(PDO::FETCH_ASSOC);
        if (!$rut) out(['status' => 'error', 'message' => 'Rutina inexistente'], 404);
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO ejercicios (nombre, repeticiones, series, dificultad, grupo_muscular, tipo_ejercicio) VALUES (?,?,?,?,?, 'General')")
            ->execute([$nombre, $reps, $series, $rut['dificultad'], $rut['grupo_muscular']]);
        $eid = $pdo->lastInsertId();
        $o = $pdo->prepare("SELECT COALESCE(MAX(orden),0)+1 FROM rutina_ejercicio WHERE id_rutina = ?"); $o->execute([$rid]);
        $pdo->prepare("INSERT INTO rutina_ejercicio (id_rutina, id_ejercicio, repeticiones, orden) VALUES (?,?,?,?)")
            ->execute([$rid, $eid, $reps, $o->fetchColumn()]);
        $pdo->commit();
        out(['status' => 'success']);

    case 'quitar_ejercicio':
        // Solo desvincula de la rutina; el ejercicio queda por si está en otras
        $pdo->prepare("DELETE FROM rutina_ejercicio WHERE id_rutina = ? AND id_ejercicio = ?")
            ->execute([(int)($in['rutina_id'] ?? 0), (int)($in['ejercicio_id'] ?? 0)]);
        out(['status' => 'success']);

    default:
        out(['status' => 'error', 'message' => 'Acción desconocida'], 400);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('admin.php: ' . $e->getMessage());
    out(['status' => 'error', 'message' => 'Error en la base de datos'], 500);
}