<?php
// front/entrenador.php — endpoints del panel de entrenador
session_start();
header('Content-Type: application/json; charset=utf-8');

function out($d, $c = 200) { http_response_code($c); echo json_encode($d); exit; }

try {
    $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASSWORD'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    out(['status' => 'error', 'message' => 'Error de conexión a la BD'], 500);
}

if (empty($_SESSION['id']) || ($_SESSION['rol'] ?? '') !== 'entrenador')
    out(['status' => 'error', 'code' => 'no_auth', 'message' => 'Sesión no válida'], 401);

$uid = (int)$_SESSION['id'];
$accion = $_GET['accion'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?? [];
$NIVEL = ['Principiante' => 1, 'Intermedio' => 2, 'Avanzado' => 3];

// ---- GIFs propios de los ejercicios ----
// Se guardan en front/dist/assets/gifs/ y en la BD (ejercicios.media_ejercicio) queda la ruta web: "dist/assets/gifs/xxx.gif"
const GIF_MAX_BYTES = 3 * 1024 * 1024;   // 3 MB
const GIF_CARPETA = __DIR__ . '/dist/assets/gifs';
const GIF_RUTA_WEB = 'dist/assets/gifs';

// Recibe el GIF como "data URL" (data:image/gif;base64,....), lo valida y lo guarda. Devuelve la ruta web.
function guardarGif($dataUrl) {
    if (!preg_match('#^data:image/gif;base64,(.+)$#s', $dataUrl, $m))
        out(['status' => 'error', 'message' => 'El archivo tiene que ser un GIF'], 400);
    $bin = base64_decode($m[1], true);
    if ($bin === false) out(['status' => 'error', 'message' => 'El GIF llegó roto, probá de nuevo'], 400);
    if (strlen($bin) > GIF_MAX_BYTES) out(['status' => 'error', 'message' => 'El GIF pesa más de 3 MB'], 400);
    // Los GIF de verdad siempre empiezan con "GIF87a" o "GIF89a" (no alcanza con que el nombre termine en .gif)
    if (!in_array(substr($bin, 0, 6), ['GIF87a', 'GIF89a'], true))
        out(['status' => 'error', 'message' => 'Ese archivo no es un GIF válido'], 400);
    if (!is_dir(GIF_CARPETA) && !@mkdir(GIF_CARPETA, 0777, true))
        out(['status' => 'error', 'message' => 'No se pudo crear la carpeta de GIFs en el servidor'], 500);
    // La carpeta existe pero Apache (usuario www-data) no puede escribir en ella: es un tema de permisos del servidor
    if (!is_writable(GIF_CARPETA))
        out(['status' => 'error', 'message' => 'El servidor no tiene permiso para guardar GIFs'], 500);
    $nombre = 'ej_' . bin2hex(random_bytes(8)) . '.gif';   // nombre al azar: no se pisan ni se pueden adivinar
    if (@file_put_contents(GIF_CARPETA . '/' . $nombre, $bin) === false)
        out(['status' => 'error', 'message' => 'No se pudo guardar el GIF en el servidor'], 500);
    return GIF_RUTA_WEB . '/' . $nombre;
}

// Borra el archivo de un GIF propio (solo si está dentro de la carpeta de GIFs)
function borrarGif($ruta) {
    if ($ruta && preg_match('#^' . GIF_RUTA_WEB . '/ej_[0-9a-f]{16}\.gif$#', $ruta)) @unlink(__DIR__ . '/' . $ruta);
}

try {
    $pdo->prepare("UPDATE user SET ultima_actividad = NOW() WHERE id = ?")->execute([$uid]);
    $q = function ($sql, $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); };

    switch ($accion) {

    case 'todo':
        $yo = $q("SELECT nombre, apellido, especialidad FROM user WHERE id = ?", [$uid])[0];

        $ejercicios = $q("SELECT e.id, e.nombre, e.grupo_muscular, e.tipo_ejercicio, e.dificultad, e.info_ejercicio AS info, e.media_ejercicio AS gif,
                            (SELECT COUNT(*) FROM rutina_ejercicio re WHERE re.id_ejercicio = e.id) AS usado
                          FROM ejercicios e ORDER BY e.nombre");

        $rutinas = $q("SELECT id, nombre, dificultad, grupo_muscular FROM rutinas ORDER BY id DESC");
        $ej = $q("SELECT re.id_rutina, e.nombre, e.media_ejercicio AS gif, COALESCE(re.series, e.series) AS series, COALESCE(re.repeticiones, e.repeticiones) AS reps
                  FROM rutina_ejercicio re JOIN ejercicios e ON e.id = re.id_ejercicio ORDER BY re.id_rutina, re.orden");
        foreach ($rutinas as &$r) {
            $r['ej'] = [];
            foreach ($ej as $x) if ($x['id_rutina'] == $r['id']) $r['ej'][] = ['n' => $x['nombre'], 's' => $x['series'] . ' x ' . $x['reps'], 'g' => $x['gif']];
        }
        unset($r);

        $alumnos = $q("SELECT u.id, u.nombre, u.apellido, u.objetivo, p.nombre AS plan, (u.id_entrenador = ?) AS mio
                       FROM user u LEFT JOIN planes p ON p.id = u.id_plan
                       WHERE LOWER(u.rol) = 'socio' AND u.estado = 'Activo' ORDER BY (u.id_entrenador = ?) DESC, u.nombre", [$uid, $uid]);
        $asig = $q("SELECT a.id_user, r.id, r.nombre FROM rutina_asignada a JOIN rutinas r ON r.id = a.id_rutina ORDER BY a.fecha");
        foreach ($alumnos as &$a) {
            $a['mio'] = (bool)$a['mio'];
            $a['rutinas'] = [];
            foreach ($asig as $x) if ($x['id_user'] == $a['id']) $a['rutinas'][] = ['id' => (int)$x['id'], 'nombre' => $x['nombre']];
        }
        unset($a);

        out(['status' => 'success', 'data' => ['yo' => $yo, 'ejercicios' => $ejercicios, 'rutinas' => $rutinas, 'alumnos' => $alumnos]]);

    case 'crear_ejercicio':
        $n = trim($in['nombre'] ?? ''); $g = trim($in['grupo_muscular'] ?? ''); $t = trim($in['tipo_ejercicio'] ?? '');
        $d = $in['dificultad'] ?? '';
        if ($n === '' || $g === '' || $t === '' || !isset($NIVEL[$d])) out(['status' => 'error', 'message' => 'Completá nombre, grupo, tipo y dificultad'], 400);
        // GIF opcional: se valida y se guarda antes de tocar la BD
        $gif = !empty($in['gif']) ? guardarGif($in['gif']) : null;
        // repeticiones y series de acá son valores por defecto: los reales se eligen al armar cada rutina
        try {
            $pdo->prepare("INSERT INTO ejercicios (nombre, repeticiones, series, dificultad, grupo_muscular, tipo_ejercicio, info_ejercicio, media_ejercicio) VALUES (?, 10, 3, ?, ?, ?, ?, ?)")
                ->execute([$n, $d, $g, $t, trim($in['info'] ?? '') ?: null, $gif]);
        } catch (PDOException $e) {
            borrarGif($gif);   // si falló la BD, no dejamos el archivo huérfano
            throw $e;
        }
        out(['status' => 'success']);

    case 'borrar_ejercicio':
        $id = (int)($in['id'] ?? 0);
        $s = $pdo->prepare("SELECT media_ejercicio FROM ejercicios WHERE id = ?"); $s->execute([$id]);
        $gif = $s->fetchColumn();   // false = no existe ese ejercicio; null = existe pero sin GIF propio
        if ($gif === false) out(['status' => 'error', 'message' => 'Ese ejercicio ya no existe'], 404);
        // Si el ejercicio está en alguna rutina, primero se saca de esas rutinas y después se borra.
        // Va en una transacción: o se hacen los dos pasos, o no se hace ninguno.
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM rutina_ejercicio WHERE id_ejercicio = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM ejercicios WHERE id = ?")->execute([$id]);
        $pdo->commit();
        borrarGif($gif);
        out(['status' => 'success']);

    case 'crear_rutina':
        $nombre = trim($in['nombre'] ?? ''); $items = $in['items'] ?? [];
        if ($nombre === '') out(['status' => 'error', 'message' => 'Ponele un nombre a la rutina'], 400);
        if (!is_array($items) || !count($items)) out(['status' => 'error', 'message' => 'Agregá al menos un ejercicio'], 400);
        $vistos = []; $niv = []; $grupos = [];
        foreach ($items as $i) {
            $eid = (int)($i['ejercicio_id'] ?? 0); $se = (int)($i['series'] ?? 0); $re = (int)($i['repeticiones'] ?? 0);
            if ($eid < 1 || $se < 1 || $re < 1) out(['status' => 'error', 'message' => 'Elegí ejercicio, series y repeticiones en cada slot'], 400);
            if (isset($vistos[$eid])) out(['status' => 'error', 'message' => 'Repetiste un ejercicio en la rutina'], 400);
            $vistos[$eid] = true;
        }
        $in_ids = implode(',', array_map('intval', array_keys($vistos)));
        $datos = $q("SELECT dificultad, grupo_muscular FROM ejercicios WHERE id IN ($in_ids)");
        if (count($datos) !== count($vistos)) out(['status' => 'error', 'message' => 'Algún ejercicio ya no existe'], 404);
        foreach ($datos as $d) { $niv[] = $NIVEL[$d['dificultad']] ?? 2; $grupos[$d['grupo_muscular']] = true; }
        // Todo lo demás se calcula solo
        $dif = ['Principiante', 'Intermedio', 'Avanzado'][max(1, min(3, (int)round(array_sum($niv) / count($niv)))) - 1];
        $g = array_keys($grupos);
        $grupo = count($g) === 1 ? $g[0] : (count($g) === 2 ? $g[0] . ' y ' . $g[1] : 'Cuerpo completo');

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO rutinas (nombre, descripcion, dificultad, grupo_muscular) VALUES (?, ?, ?, ?)")
            ->execute([$nombre, count($items) . ' ejercicios', $dif, $grupo]);
        $rid = $pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO rutina_ejercicio (id_rutina, id_ejercicio, series, repeticiones, orden, tiempo_descanso) VALUES (?, ?, ?, ?, ?, 60)");
        foreach ($items as $k => $i) $ins->execute([$rid, (int)$i['ejercicio_id'], (int)$i['series'], (int)$i['repeticiones'], $k + 1]);
        $pdo->commit();
        out(['status' => 'success']);

    case 'borrar_rutina':
        $id = (int)($in['id'] ?? 0);
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM rutina_asignada WHERE id_rutina = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM rutina_ejercicio WHERE id_rutina = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM rutinas WHERE id = ?")->execute([$id]);
        $pdo->commit();
        out(['status' => 'success']);

    case 'asignar_rutina':
        $s = $pdo->prepare("SELECT COUNT(*) FROM user WHERE id = ? AND LOWER(rol) = 'socio' AND estado = 'Activo'"); $s->execute([(int)($in['user_id'] ?? 0)]);
        $r = $pdo->prepare("SELECT COUNT(*) FROM rutinas WHERE id = ?"); $r->execute([(int)($in['rutina_id'] ?? 0)]);
        if (!$s->fetchColumn() || !$r->fetchColumn()) out(['status' => 'error', 'message' => 'Socio o rutina inexistente'], 404);
        $pdo->prepare("INSERT IGNORE INTO rutina_asignada (id_user, id_rutina, id_entrenador) VALUES (?, ?, ?)")
            ->execute([(int)$in['user_id'], (int)$in['rutina_id'], $uid]);
        out(['status' => 'success']);

    case 'quitar_asignacion':
        $pdo->prepare("DELETE FROM rutina_asignada WHERE id_user = ? AND id_rutina = ?")->execute([(int)($in['user_id'] ?? 0), (int)($in['rutina_id'] ?? 0)]);
        out(['status' => 'success']);

    default:
        out(['status' => 'error', 'message' => 'Acción desconocida'], 400);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('entrenador.php: ' . $e->getMessage());
    out(['status' => 'error', 'message' => 'Error en la base de datos'], 500);
}
