<?php
// front/socio.php — endpoints del panel de socio
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($d, $c = 200) { http_response_code($c); echo json_encode($d); exit; }

try {
    $pdo = new PDO("mysql:host=db;dbname=fitpowerbd;charset=utf8mb4", 'root', 'root_password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    out(['status' => 'error', 'message' => 'Error de conexión a la BD'], 500);
}

if (empty($_SESSION['id']) || ($_SESSION['rol'] ?? '') !== 'socio')
    out(['status' => 'error', 'code' => 'no_auth', 'message' => 'Sesión no válida'], 401);

$uid = (int)$_SESSION['id'];
$accion = $_GET['accion'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?? [];
const MAX_ALUMNOS = 15;
const PARKING_TOTAL = 25;   // lugares 01-20: plan Pro · 21-25: solo Premium
const PARKING_PRO = 20;
const PARKING_HORAS = 4;    // la reserva vence sola
function planParking($pdo, $uid) {
    $s = $pdo->prepare("SELECT p.nombre FROM user u JOIN planes p ON p.id = u.id_plan WHERE u.id = ?");
    $s->execute([$uid]);
    $n = $s->fetchColumn();
    return in_array($n, ['Pro', 'Premium'], true) ? $n : null;   // el plan Básico no tiene acceso
}
const OBJETIVOS = ['Perder peso', 'Ganar masa muscular', 'Tonificar', 'Resistencia y cardio', 'Rehabilitación'];

try {
    $pdo->prepare("UPDATE user SET ultima_actividad = NOW() WHERE id = ?")->execute([$uid]);

    switch ($accion) {

    case 'todo':
        $q = function ($sql, $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); };

        $yo = $q("SELECT nombre, apellido, objetivo, id_entrenador, id_plan FROM user WHERE id = ?", [$uid])[0];
        $cuota = $q("SELECT id, estado, monto, fecha_vencimiento, pago_mostrador FROM cuotas WHERE usuario_id = ? ORDER BY fecha_vencimiento DESC, id DESC LIMIT 1", [$uid])[0] ?? null;

        $rutinas = $q("SELECT r.id, r.nombre, r.dificultad, r.grupo_muscular FROM rutinas r JOIN rutina_asignada a ON a.id_rutina = r.id WHERE a.id_user = ? ORDER BY a.fecha DESC", [$uid]);
        $ej = $q("SELECT re.id_rutina, e.nombre, COALESCE(re.series, e.series) AS series, COALESCE(re.repeticiones, e.repeticiones) AS reps
                  FROM rutina_ejercicio re JOIN ejercicios e ON e.id = re.id_ejercicio ORDER BY re.id_rutina, re.orden");
        foreach ($rutinas as &$r) {
            $r['ej'] = [];
            foreach ($ej as $x) if ($x['id_rutina'] == $r['id']) $r['ej'][] = ['n' => $x['nombre'], 's' => $x['series'] . ' x ' . $x['reps']];
        }
        unset($r);

        $clases = $q("SELECT c.id, c.nombre, c.dia_hora, c.cupo, CONCAT(t.nombre, ' ', t.apellido) AS entrenador,
                        (SELECT COUNT(*) FROM inscripciones_clase i WHERE i.id_clase = c.id) AS anotados,
                        EXISTS(SELECT 1 FROM inscripciones_clase i WHERE i.id_clase = c.id AND i.id_user = ?) AS inscripto
                      FROM clases c LEFT JOIN user t ON t.id = c.id_entrenador
                      WHERE c.dia_hora >= NOW() ORDER BY c.dia_hora", [$uid]);
        foreach ($clases as &$c) { $c['anotados'] = (int)$c['anotados']; $c['cupo'] = (int)$c['cupo']; $c['inscripto'] = (bool)$c['inscripto']; }
        unset($c);

        $entrenadores = $q("SELECT t.id, t.nombre, t.apellido, t.especialidad,
                              (SELECT COUNT(*) FROM user a WHERE a.id_entrenador = t.id AND a.estado = 'Activo') AS alumnos
                            FROM user t WHERE LOWER(t.rol) = 'entrenador' AND t.estado = 'Activo' ORDER BY t.nombre");
        foreach ($entrenadores as &$t) $t['alumnos'] = (int)$t['alumnos'];
        unset($t);

        out(['status' => 'success', 'data' => [
            'yo' => $yo, 'cuota' => $cuota,
            'planes' => $q("SELECT id, nombre, precio, descripcion FROM planes ORDER BY precio"),
            'rutinas' => $rutinas, 'clases' => $clases, 'entrenadores' => $entrenadores,
            'quejas' => $q("SELECT id, texto, fecha FROM quejas WHERE id_user = ? ORDER BY fecha DESC", [$uid]),
            'max_alumnos' => MAX_ALUMNOS, 'objetivos' => OBJETIVOS
        ]]);

    case 'anotar_clase':
        $cid = (int)($in['clase_id'] ?? 0);
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT cupo FROM clases WHERE id = ? AND dia_hora >= NOW() FOR UPDATE"); $s->execute([$cid]);
        $cupo = $s->fetchColumn();
        if ($cupo === false) { $pdo->rollBack(); out(['status' => 'error', 'message' => 'La clase no existe o ya pasó'], 404); }
        $s = $pdo->prepare("SELECT COUNT(*) FROM inscripciones_clase WHERE id_clase = ?"); $s->execute([$cid]);
        if ($s->fetchColumn() >= $cupo) { $pdo->rollBack(); out(['status' => 'error', 'message' => 'La clase está completa'], 409); }
        $s = $pdo->prepare("INSERT IGNORE INTO inscripciones_clase (id_user, id_clase) VALUES (?, ?)"); $s->execute([$uid, $cid]);
        $pdo->commit();
        out(['status' => 'success']);

    case 'cancelar_clase':
        $pdo->prepare("DELETE FROM inscripciones_clase WHERE id_user = ? AND id_clase = ?")->execute([$uid, (int)($in['clase_id'] ?? 0)]);
        out(['status' => 'success']);

    case 'escribir_queja':
        $t = trim($in['texto'] ?? '');
        if ($t === '') out(['status' => 'error', 'message' => 'Escribí algo primero'], 400);
        $pdo->prepare("INSERT INTO quejas (id_user, texto) VALUES (?, ?)")->execute([$uid, mb_substr($t, 0, 2000)]);
        out(['status' => 'success']);

    case 'borrar_queja':
        $pdo->prepare("DELETE FROM quejas WHERE id = ? AND id_user = ?")->execute([(int)($in['id'] ?? 0), $uid]);
        out(['status' => 'success']);

    case 'comprar_plan':
        $s = $pdo->prepare("SELECT id, precio FROM planes WHERE id = ?"); $s->execute([(int)($in['plan_id'] ?? 0)]);
        $plan = $s->fetch(PDO::FETCH_ASSOC);
        if (!$plan) out(['status' => 'error', 'message' => 'Plan inexistente'], 404);
        $s = $pdo->prepare("SELECT id_plan FROM user WHERE id = ?"); $s->execute([$uid]);
        if ($s->fetchColumn() == $plan['id']) out(['status' => 'error', 'message' => 'Ya tenés este plan'], 409);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE user SET id_plan = ? WHERE id = ?")->execute([$plan['id'], $uid]);
        // Se genera la cuota pendiente: aparece en el semáforo y las finanzas del admin
        $pdo->prepare("INSERT INTO cuotas (usuario_id, monto, fecha_vencimiento, estado) VALUES (?, ?, CURDATE() + INTERVAL 7 DAY, 'Pendiente')")
            ->execute([$uid, $plan['precio']]);
        $pdo->commit();
        out(['status' => 'success']);

    case 'pagar_cuota':
        // MODO DEMO: no hay pasarela real. Con una (Mercado Pago, etc.) acá se confirmaría el pago con su API antes de marcarlo.
        $metodos = ['Tarjeta de crédito', 'Tarjeta de débito', 'Mercado Pago', 'PayPal', 'Apple Pay'];
        $m = $in['metodo'] ?? '';
        if (!in_array($m, $metodos, true)) out(['status' => 'error', 'message' => 'Método de pago inválido'], 400);
        $s = $pdo->prepare("UPDATE cuotas SET estado = 'Pagada', fecha_pago = CURDATE(), metodo_pago = ?, pago_mostrador = NULL
                            WHERE id = ? AND usuario_id = ? AND estado IN ('Pendiente', 'Vencida')");
        $s->execute([$m, (int)($in['cuota_id'] ?? 0), $uid]);
        if ($s->rowCount() === 0) out(['status' => 'error', 'message' => 'Esa cuota ya está paga o no existe'], 409);
        out(['status' => 'success']);

    case 'pagar_mostrador':
        $f = $in['fecha'] ?? '';
        $d = DateTime::createFromFormat('Y-m-d', $f);
        if (!$d || $d->format('Y-m-d') !== $f) out(['status' => 'error', 'message' => 'Fecha inválida'], 400);
        $d->setTime(0, 0);
        $dif = (int)(new DateTime('today'))->diff($d)->format('%r%a');   // -1 se tolera por diferencia horaria
        if ($dif < -1 || $dif > 10) out(['status' => 'error', 'message' => 'Elegí una fecha dentro de los próximos 10 días'], 400);
        $cid = (int)($in['cuota_id'] ?? 0);
        $s = $pdo->prepare("SELECT COUNT(*) FROM cuotas WHERE id = ? AND usuario_id = ? AND estado IN ('Pendiente', 'Vencida')");
        $s->execute([$cid, $uid]);
        if (!$s->fetchColumn()) out(['status' => 'error', 'message' => 'Esa cuota ya está paga o no existe'], 404);
        $pdo->prepare("UPDATE cuotas SET pago_mostrador = ? WHERE id = ? AND usuario_id = ?")->execute([$f, $cid, $uid]);
        out(['status' => 'success']);

    case 'cancelar_mostrador':
        $pdo->prepare("UPDATE cuotas SET pago_mostrador = NULL WHERE id = ? AND usuario_id = ?")->execute([(int)($in['cuota_id'] ?? 0), $uid]);
        out(['status' => 'success']);

    case 'parking':
        $plan = planParking($pdo, $uid);
        if (!$plan) out(['status' => 'error', 'message' => 'El estacionamiento es solo para planes Pro y Premium'], 403);
        $pdo->exec("DELETE FROM parking_reservas WHERE expira <= NOW()");
        $res = [];
        foreach ($pdo->query("SELECT lugar, id_user, TIMESTAMPDIFF(MINUTE, NOW(), expira) AS rest FROM parking_reservas")->fetchAll(PDO::FETCH_ASSOC) as $r) $res[(int)$r['lugar']] = $r;
        $lugares = []; $libres = 0; $lp = 0; $lpr = 0;
        for ($n = 1; $n <= PARKING_TOTAL; $n++) {
            $tipo = $n <= PARKING_PRO ? 'pro' : 'premium';
            $r = $res[$n] ?? null;
            $estado = !$r ? 'libre' : ((int)$r['id_user'] === $uid ? 'mio' : 'ocupado');
            if ($estado === 'libre') { $libres++; if ($tipo === 'pro') $lp++; else $lpr++; }
            $lugares[] = ['n' => $n, 'tipo' => $tipo, 'estado' => $estado, 'rest' => $estado === 'mio' ? (int)$r['rest'] : null];
        }
        out(['status' => 'success', 'data' => ['plan' => $plan, 'puede_premium' => $plan === 'Premium', 'total' => PARKING_TOTAL,
            'libres' => $libres, 'libres_pro' => $lp, 'libres_premium' => $lpr, 'lugares' => $lugares]]);

    case 'parking_reservar':
        $plan = planParking($pdo, $uid);
        if (!$plan) out(['status' => 'error', 'message' => 'El estacionamiento es solo para planes Pro y Premium'], 403);
        $n = (int)($in['lugar'] ?? 0);
        if ($n < 1 || $n > PARKING_TOTAL) out(['status' => 'error', 'message' => 'Lugar inválido'], 400);
        if ($n > PARKING_PRO && $plan !== 'Premium') out(['status' => 'error', 'message' => 'Ese lugar es exclusivo del plan Premium'], 403);
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM parking_reservas WHERE expira <= NOW()");
        $pdo->prepare("DELETE FROM parking_reservas WHERE id_user = ?")->execute([$uid]);   // si ya tenía uno, se cambia
        try {
            $pdo->prepare("INSERT INTO parking_reservas (lugar, id_user, expira) VALUES (?, ?, NOW() + INTERVAL " . PARKING_HORAS . " HOUR)")->execute([$n, $uid]);
        } catch (PDOException $e) {
            $pdo->rollBack();   // conserva la reserva anterior
            if ($e->getCode() == 23000) out(['status' => 'error', 'message' => 'Ese lugar ya fue reservado'], 409);
            throw $e;
        }
        $pdo->commit();
        out(['status' => 'success']);

    case 'parking_cancelar':
        $pdo->prepare("DELETE FROM parking_reservas WHERE id_user = ?")->execute([$uid]);
        out(['status' => 'success']);

    case 'guardar_objetivo':
        $o = trim($in['objetivo'] ?? '');
        if (!in_array($o, OBJETIVOS, true)) out(['status' => 'error', 'message' => 'Objetivo inválido'], 400);
        $pdo->prepare("UPDATE user SET objetivo = ? WHERE id = ?")->execute([$o, $uid]);
        out(['status' => 'success']);

    case 'elegir_entrenador':
        $tid = (int)($in['entrenador_id'] ?? 0);
        $s = $pdo->prepare("SELECT (SELECT COUNT(*) FROM user a WHERE a.id_entrenador = t.id AND a.estado = 'Activo' AND a.id <> ?) FROM user t
                            WHERE t.id = ? AND LOWER(t.rol) = 'entrenador' AND t.estado = 'Activo'");
        $s->execute([$uid, $tid]);
        $n = $s->fetchColumn();
        if ($n === false) out(['status' => 'error', 'message' => 'Entrenador no disponible'], 404);
        if ($n >= MAX_ALUMNOS) out(['status' => 'error', 'message' => 'Ese entrenador no tiene cupos'], 409);
        $pdo->prepare("UPDATE user SET id_entrenador = ? WHERE id = ?")->execute([$tid, $uid]);
        out(['status' => 'success']);

    default:
        out(['status' => 'error', 'message' => 'Acción desconocida'], 400);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('socio.php: ' . $e->getMessage());
    out(['status' => 'error', 'message' => 'Error en la base de datos. ¿Corriste migracion_socio.sql?'], 500);
}