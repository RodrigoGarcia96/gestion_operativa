<?php
include("../../includes/auth.php");
requireRole(["supervisor"]);
include("../../config/connection.php");
include("../../config/reglas.php");

date_default_timezone_set("America/Mexico_City");
$hoy = date("Y-m-d");

// ===== Funciones auxiliares =====

// Minutos de retraso respecto a la hora del turno (0 si no hay hora de entrada)
function minutosRetardo($hora, $horaTurno) {
    if (!$hora) return 0;
    $segundos = strtotime($hora) - strtotime($horaTurno);
    return max(0, (int) ceil($segundos / 60));
}

// 75 -> "1 h 15 min"
function formatoTiempo($min) {
    if ($min < 60) return $min . " min";
    return floor($min / 60) . " h " . ($min % 60) . " min";
}

function fechaValida($f) {
    $d = DateTime::createFromFormat("Y-m-d", $f);
    return $d && $d->format("Y-m-d") === $f;
}

// Query string actual, para conservar los filtros después de cada acción
$qs = $_SERVER["QUERY_STRING"] ?? "";
$destino = "faltas_retardos.php" . ($qs !== "" ? "?" . $qs : "");

// ===== Acción: registrar una falta (de hoy) =====
if (!empty($_POST["btn_falta"])) {
    $kronos = trim($_POST["kronos"] ?? "");
    $flash  = null;

    if ($kronos === "" || !ctype_digit($kronos)) {
        $flash = ["error", "Captura un número de Kronos válido."];
    } else {
        $stmt = $connection->prepare(
            "SELECT nombre, apellido_p FROM empleado WHERE id_kronos = ?"
        );
        $stmt->bind_param("i", $kronos);
        $stmt->execute();
        $empleado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$empleado) {
            $flash = ["error", "El número de Kronos $kronos no existe."];
        } else {
            $nombre = $empleado["nombre"] . " " . $empleado["apellido_p"];

            $stmt = $connection->prepare(
                "SELECT id_asistencia FROM asistencia
                 WHERE id_kronos = ? AND fecha_asistencia = ?"
            );
            $stmt->bind_param("is", $kronos, $hoy);
            $stmt->execute();
            $existe = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existe) {
                $flash = ["aviso", "$nombre ya tiene un registro de asistencia hoy."];
            } else {
                $stmt = $connection->prepare(
                    "INSERT INTO asistencia (id_kronos, fecha_asistencia, hora_entrada, es_falta, es_retardo)
                     VALUES (?, ?, NULL, 1, 0)"
                );
                $stmt->bind_param("is", $kronos, $hoy);
                $stmt->execute();
                $stmt->close();
                $flash = ["ok", "Falta registrada para $nombre."];
            }
        }
    }

    $_SESSION["flash"] = $flash;
    header("Location: " . $destino);
    exit;
}

// ===== Acción: registrar reposición de tiempo =====
if (!empty($_POST["btn_reposicion"])) {
    $idAsistencia = (int) ($_POST["id_asistencia"] ?? 0);
    $minutos      = (int) ($_POST["minutos"] ?? 0);
    $flash        = null;

    $stmt = $connection->prepare(
        "SELECT hora_entrada, es_retardo, minutos_repuestos
         FROM asistencia WHERE id_asistencia = ?"
    );
    $stmt->bind_param("i", $idAsistencia);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila || !$fila["es_retardo"]) {
        $flash = ["error", "El registro no corresponde a un retardo."];
    } elseif ($minutos <= 0) {
        $flash = ["error", "Captura los minutos repuestos (mayor a 0)."];
    } else {
        $pendiente = minutosRetardo($fila["hora_entrada"], $HORA_TURNO) - (int) $fila["minutos_repuestos"];

        if ($minutos > $pendiente) {
            $flash = ["error", "Los minutos exceden lo pendiente por reponer ($pendiente min)."];
        } else {
            $stmt = $connection->prepare(
                "UPDATE asistencia SET minutos_repuestos = minutos_repuestos + ?
                 WHERE id_asistencia = ?"
            );
            $stmt->bind_param("ii", $minutos, $idAsistencia);
            $stmt->execute();
            $stmt->close();
            $flash = ["ok", "Reposición registrada: $minutos min."];
        }
    }

    $_SESSION["flash"] = $flash;
    header("Location: " . $destino);
    exit;
}

// ===== Mensaje pendiente =====
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);

// ===== Filtros (GET) =====
$desde = fechaValida($_GET["desde"] ?? "") ? $_GET["desde"] : date("Y-m-01");
$hasta = fechaValida($_GET["hasta"] ?? "") ? $_GET["hasta"] : $hoy;
$filtroKronos = trim($_GET["kronos"] ?? "");
if (!ctype_digit($filtroKronos)) $filtroKronos = "";

// ===== Consulta: faltas y retardos del periodo =====
$sql = "SELECT a.id_asistencia, a.id_kronos, e.nombre, e.apellido_p, e.apellido_m,
               a.fecha_asistencia, a.hora_entrada, a.es_falta, a.es_retardo, a.minutos_repuestos
        FROM asistencia a
        INNER JOIN empleado e ON e.id_kronos = a.id_kronos
        WHERE a.fecha_asistencia BETWEEN ? AND ?
          AND (a.es_falta = 1 OR a.es_retardo = 1)";
$tipos  = "ss";
$params = [$desde, $hasta];

if ($filtroKronos !== "") {
    $sql    .= " AND a.id_kronos = ?";
    $tipos  .= "i";
    $params[] = (int) $filtroKronos;
}

$sql .= " ORDER BY a.fecha_asistencia DESC, a.hora_entrada DESC";

$stmt = $connection->prepare($sql);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$registros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== Cálculos: detalle, resumen por empleado y totales =====
$totalRetardos = 0;
$totalFaltas   = 0;
$totalPendiente = 0;
$resumen = [];

foreach ($registros as $i => $r) {
    $minRetardo = $r["es_retardo"] ? minutosRetardo($r["hora_entrada"], $HORA_TURNO) : 0;
    $repuestos  = $r["es_retardo"] ? (int) $r["minutos_repuestos"] : 0;
    $pendiente  = max(0, $minRetardo - $repuestos);

    $registros[$i]["min_retardo"] = $minRetardo;
    $registros[$i]["pendiente"]   = $pendiente;

    $k = $r["id_kronos"];
    if (!isset($resumen[$k])) {
        $resumen[$k] = [
            "nombre"    => $r["nombre"] . " " . $r["apellido_p"] . " " . $r["apellido_m"],
            "retardos"  => 0,
            "faltas"    => 0,
            "minutos"   => 0,
            "repuestos" => 0,
            "pendiente" => 0,
        ];
    }

    if ($r["es_falta"]) {
        $totalFaltas++;
        $resumen[$k]["faltas"]++;
    }
    if ($r["es_retardo"]) {
        $totalRetardos++;
        $resumen[$k]["retardos"]++;
        $resumen[$k]["minutos"]   += $minRetardo;
        $resumen[$k]["repuestos"] += $repuestos;
        $resumen[$k]["pendiente"] += $pendiente;
        $totalPendiente += $pendiente;
    }
}

// Los empleados con más tiempo por reponer aparecen primero
uasort($resumen, function ($a, $b) {
    return $b["pendiente"] <=> $a["pendiente"];
});

$titulo = "Control de Faltas y Retardos";
include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Control de Faltas y Retardos</h1>
    <p>Tiempo acumulado y seguimiento de reposición · <?php echo date("d/m/Y", strtotime($desde)); ?> al <?php echo date("d/m/Y", strtotime($hasta)); ?></p>
  </div>

  <?php if ($flash): ?>
    <div class="mensaje <?php echo htmlspecialchars($flash[0]); ?>">
      <?php echo htmlspecialchars($flash[1]); ?>
    </div>
  <?php endif; ?>

  <section class="tarjetas">
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos</span>
      <span class="valor"><?php echo $totalRetardos; ?></span>
      <span class="detalle">En el periodo</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Faltas</span>
      <span class="valor"><?php echo $totalFaltas; ?></span>
      <span class="detalle">En el periodo</span>
    </div>
    <div class="tarjeta acento">
      <span class="etiqueta">Tiempo por reponer</span>
      <span class="valor"><?php echo formatoTiempo($totalPendiente); ?></span>
      <span class="detalle">Pendiente acumulado</span>
    </div>
    <div class="tarjeta">
      <span class="etiqueta">Empleados con incidencias</span>
      <span class="valor"><?php echo count($resumen); ?></span>
      <span class="detalle">Con falta o retardo</span>
    </div>
  </section>

  <section class="panel">
    <h2>Filtros</h2>
    <p class="descripcion">Elige el periodo y, si quieres, un número de Kronos.</p>

    <form class="form-rapido" method="get" action="faltas_retardos.php">
      <div class="campo">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" value="<?php echo htmlspecialchars($desde); ?>">
      </div>
      <div class="campo">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>">
      </div>
      <div class="campo">
        <label for="filtro_kronos">No. Kronos (opcional)</label>
        <input type="text" id="filtro_kronos" name="kronos" inputmode="numeric"
               value="<?php echo htmlspecialchars($filtroKronos); ?>" placeholder="Todos">
      </div>
      <button type="submit" class="btn-primario">Filtrar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Registrar falta</h2>
    <p class="descripcion">Marca la falta de hoy para un empleado que no se presentó.</p>

    <form class="form-rapido" method="post" action="<?php echo htmlspecialchars($destino); ?>" autocomplete="off">
      <div class="campo">
        <label for="kronos_falta">No. Kronos</label>
        <input type="text" id="kronos_falta" name="kronos" inputmode="numeric" placeholder="Número de Kronos" required>
      </div>
      <button type="submit" class="btn-primario" name="btn_falta" value="1">Registrar falta</button>
    </form>
  </section>

  <section class="panel">
    <h2>Resumen por empleado</h2>
    <p class="descripcion">Tiempo acumulado y pendiente de reposición en el periodo.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Kronos</th>
            <th>Empleado</th>
            <th>Retardos</th>
            <th>Faltas</th>
            <th>Tiempo de retardo</th>
            <th>Repuesto</th>
            <th>Por reponer</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($resumen)): ?>
            <tr><td colspan="7">No hay faltas ni retardos en este periodo.</td></tr>
          <?php else: ?>
            <?php foreach ($resumen as $kronos => $e): ?>
              <tr>
                <td><?php echo htmlspecialchars($kronos); ?></td>
                <td><?php echo htmlspecialchars($e["nombre"]); ?></td>
                <td><?php echo $e["retardos"]; ?></td>
                <td><?php echo $e["faltas"]; ?></td>
                <td><?php echo formatoTiempo($e["minutos"]); ?></td>
                <td><?php echo formatoTiempo($e["repuestos"]); ?></td>
                <td>
                  <?php if ($e["pendiente"] > 0): ?>
                    <span class="badge retardo"><?php echo formatoTiempo($e["pendiente"]); ?></span>
                  <?php else: ?>
                    <span class="badge puntual">Al corriente</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <h2>Detalle de incidencias</h2>
    <p class="descripcion">Registra aquí el tiempo que cada empleado va reponiendo.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>No. Kronos</th>
            <th>Empleado</th>
            <th>Hora de entrada</th>
            <th>Estatus</th>
            <th>Retardo</th>
            <th>Por reponer</th>
            <th>Reposición</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($registros)): ?>
            <tr><td colspan="8">No hay registros para mostrar.</td></tr>
          <?php else: ?>
            <?php foreach ($registros as $r): ?>
              <tr>
                <td><?php echo date("d/m/Y", strtotime($r["fecha_asistencia"])); ?></td>
                <td><?php echo htmlspecialchars($r["id_kronos"]); ?></td>
                <td><?php echo htmlspecialchars($r["nombre"] . " " . $r["apellido_p"] . " " . $r["apellido_m"]); ?></td>
                <td><?php echo $r["hora_entrada"] ? date("H:i", strtotime($r["hora_entrada"])) : "--:--"; ?></td>
                <td>
                  <?php if ($r["es_falta"]): ?>
                    <span class="badge falta">Falta</span>
                  <?php else: ?>
                    <span class="badge retardo">Retardo</span>
                  <?php endif; ?>
                </td>
                <td><?php echo $r["es_retardo"] ? formatoTiempo($r["min_retardo"]) : "—"; ?></td>
                <td><?php echo $r["es_retardo"] ? formatoTiempo($r["pendiente"]) : "—"; ?></td>
                <td>
                  <?php if ($r["es_retardo"] && $r["pendiente"] > 0): ?>
                    <form class="form-inline" method="post" action="<?php echo htmlspecialchars($destino); ?>">
                      <input type="hidden" name="id_asistencia" value="<?php echo (int) $r["id_asistencia"]; ?>">
                      <input type="number" class="input-mini" name="minutos" min="1"
                             max="<?php echo $r["pendiente"]; ?>" placeholder="min" required>
                      <button type="submit" class="btn-chico" name="btn_reposicion" value="1">Reponer</button>
                    </form>
                  <?php elseif ($r["es_retardo"]): ?>
                    <span class="badge puntual">Repuesto</span>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include("../../includes/footer.php"); ?>