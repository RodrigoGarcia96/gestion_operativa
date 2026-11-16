<?php
// ===== Depuración: muestra los errores en pantalla. Bórralo cuando todo funcione =====
ini_set("display_errors", 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include_once("../../includes/auth.php");
requireRole(["analista"]);
include_once("../../config/connection.php");
include_once("../../config/reglas.php");

date_default_timezone_set("America/Mexico_City");
$hoy = date("Y-m-d");

// ===== Funciones auxiliares =====
function fechaValida($f) {
    $d = DateTime::createFromFormat("Y-m-d", $f);
    return $d && $d->format("Y-m-d") === $f;
}

function minutosRetardo($hora, $horaTurno) {
    if (!$hora) return 0;
    return max(0, (int) ceil((strtotime($hora) - strtotime($horaTurno)) / 60));
}

function formatoTiempo($min) {
    if ($min < 60) return $min . " min";
    return floor($min / 60) . " h " . ($min % 60) . " min";
}

// Devuelve [inicio, fin, inicio_anterior, fin_anterior] según el tipo de periodo
function rangoPeriodo($periodo, $fecha) {
    $f = new DateTime($fecha);

    if ($periodo === "diario") {
        $d    = clone $f;
        $h    = clone $f;
        $dAnt = (clone $f)->modify("-1 day");
        $hAnt = clone $dAnt;
    } elseif ($periodo === "mensual") {
        $d    = new DateTime($f->format("Y-m-01"));
        $h    = new DateTime($f->format("Y-m-t"));
        $dAnt = (clone $d)->modify("-1 month");
        $hAnt = new DateTime($dAnt->format("Y-m-t"));
    } else { // trimestral
        $mesIni = (int) (floor(((int) $f->format("n") - 1) / 3) * 3 + 1);
        $d    = new DateTime($f->format("Y") . "-" . sprintf("%02d", $mesIni) . "-01");
        $h    = new DateTime((clone $d)->modify("+2 months")->format("Y-m-t"));
        $dAnt = (clone $d)->modify("-3 months");
        $hAnt = new DateTime((clone $dAnt)->modify("+2 months")->format("Y-m-t"));
    }

    return [$d->format("Y-m-d"), $h->format("Y-m-d"), $dAnt->format("Y-m-d"), $hAnt->format("Y-m-d")];
}

// Cuenta los retardos de un rango (opcionalmente de un área)
function contarRetardos($cx, $desde, $hasta, $area) {
    $sql = "SELECT COUNT(*) AS total
            FROM asistencia a
            INNER JOIN empleado e ON e.id_kronos = a.id_kronos
            WHERE a.es_retardo = 1 AND e.rol = 'operativo'
              AND a.fecha_asistencia BETWEEN ? AND ?";
    $tipos  = "ss";
    $params = [$desde, $hasta];

    if ($area !== "") {
        $sql    .= " AND e.area = ?";
        $tipos  .= "s";
        $params[] = $area;
    }

    $stmt = $cx->prepare($sql);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()["total"];
    $stmt->close();
    return $total;
}

// ===== Filtros (GET) =====
$periodo = $_GET["periodo"] ?? "mensual";
if (!in_array($periodo, ["diario", "mensual", "trimestral"], true)) {
    $periodo = "mensual";
}

$fecha = fechaValida($_GET["fecha"] ?? "") ? $_GET["fecha"] : $hoy;
if ($fecha > $hoy) $fecha = $hoy;

$filtroArea = trim($_GET["area"] ?? "");

[$desde, $hasta, $desdeAnt, $hastaAnt] = rangoPeriodo($periodo, $fecha);

// ===== Áreas para el filtro =====
$areas = $connection->query(
    "SELECT DISTINCT area FROM empleado
     WHERE rol = 'operativo' AND area IS NOT NULL AND area <> ''
     ORDER BY area"
)->fetch_all(MYSQLI_ASSOC);

// ===== Retardos del periodo =====
$sql = "SELECT a.id_asistencia, a.id_kronos, e.nombre, e.apellido_p, e.apellido_m, e.area,
               a.fecha_asistencia, a.hora_entrada, a.minutos_repuestos
        FROM asistencia a
        INNER JOIN empleado e ON e.id_kronos = a.id_kronos
        WHERE a.es_retardo = 1 AND e.rol = 'operativo'
          AND a.fecha_asistencia BETWEEN ? AND ?";
$tipos  = "ss";
$params = [$desde, $hasta];

if ($filtroArea !== "") {
    $sql    .= " AND e.area = ?";
    $tipos  .= "s";
    $params[] = $filtroArea;
}
$sql .= " ORDER BY a.fecha_asistencia DESC, a.hora_entrada DESC";

$stmt = $connection->prepare($sql);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$retardosPeriodo = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalAnterior = contarRetardos($connection, $desdeAnt, $hastaAnt, $filtroArea);

// ===== Cálculos: detalle, ranking por empleado y totales =====
$detalle = [];
$ranking = [];
$totRetardos = 0;
$totMinutos  = 0;
$totRepuesto = 0;
$totPendiente = 0;

foreach ($retardosPeriodo as $r) {
    $min = minutosRetardo($r["hora_entrada"], $HORA_TURNO);
    $rep = min($min, (int) $r["minutos_repuestos"]);
    $pen = $min - $rep;

    $r["min_retardo"] = $min;
    $r["repuesto"]    = $rep;
    $r["pendiente"]   = $pen;
    $detalle[] = $r;

    $k = $r["id_kronos"];
    if (!isset($ranking[$k])) {
        $ranking[$k] = [
            "id"        => $k,
            "nombre"    => trim($r["nombre"] . " " . $r["apellido_p"] . " " . $r["apellido_m"]),
            "area"      => $r["area"],
            "retardos"  => 0,
            "minutos"   => 0,
            "repuesto"  => 0,
            "pendiente" => 0,
        ];
    }
    $ranking[$k]["retardos"]++;
    $ranking[$k]["minutos"]   += $min;
    $ranking[$k]["repuesto"]  += $rep;
    $ranking[$k]["pendiente"] += $pen;

    $totRetardos++;
    $totMinutos   += $min;
    $totRepuesto  += $rep;
    $totPendiente += $pen;
}

// Quien más debe, primero; en empate, quien tiene más retardos
usort($ranking, function ($a, $b) {
    return [$b["pendiente"], $b["retardos"]] <=> [$a["pendiente"], $a["retardos"]];
});

$variacion = $totRetardos - $totalAnterior;
$limiteDetalle = 300;

$etiquetaPeriodo = ["diario" => "Diario", "mensual" => "Mensual", "trimestral" => "Trimestral"][$periodo];

$titulo = "Acumulado de Retardos";
include_once("../../includes/header.php");
include_once("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Acumulado de Retardos</h1>
    <p>
      Consulta y análisis <?php echo strtolower($etiquetaPeriodo); ?> ·
      <?php echo date("d/m/Y", strtotime($desde)); ?>
      <?php if ($desde !== $hasta): ?> al <?php echo date("d/m/Y", strtotime($hasta)); ?><?php endif; ?>
      <?php if ($filtroArea !== ""): ?> · Área: <?php echo htmlspecialchars($filtroArea); ?><?php endif; ?>
    </p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos del periodo</span>
      <span class="valor"><?php echo $totRetardos; ?></span>
      <span class="detalle">
        Periodo anterior: <?php echo $totalAnterior; ?>
        (<?php echo ($variacion > 0 ? "+" : "") . $variacion; ?>)
      </span>
    </div>
    <div class="tarjeta">
      <span class="etiqueta">Tiempo de retardo</span>
      <span class="valor"><?php echo formatoTiempo($totMinutos); ?></span>
      <span class="detalle">Suma de todos los retardos</span>
    </div>
    <div class="tarjeta ok">
      <span class="etiqueta">Tiempo repuesto</span>
      <span class="valor"><?php echo formatoTiempo($totRepuesto); ?></span>
      <span class="detalle">Ya compensado</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Por reponer</span>
      <span class="valor"><?php echo formatoTiempo($totPendiente); ?></span>
      <span class="detalle"><?php echo count($ranking); ?> empleado(s) con retardos</span>
    </div>
  </section>

  <section class="panel">
    <h2>Filtros</h2>
    <p class="descripcion">Elige la periodicidad, una fecha dentro del periodo y, si quieres, un área.</p>

    <form class="form-rapido" method="get" action="retardos.php">
      <div class="campo">
        <label for="periodo">Periodo</label>
        <select id="periodo" name="periodo">
          <option value="diario"     <?php echo $periodo === "diario"     ? "selected" : ""; ?>>Diario</option>
          <option value="mensual"    <?php echo $periodo === "mensual"    ? "selected" : ""; ?>>Mensual</option>
          <option value="trimestral" <?php echo $periodo === "trimestral" ? "selected" : ""; ?>>Trimestral</option>
        </select>
      </div>
      <div class="campo">
        <label for="fecha">Fecha de referencia</label>
        <input type="date" id="fecha" name="fecha" max="<?php echo $hoy; ?>"
               value="<?php echo htmlspecialchars($fecha); ?>">
      </div>
      <div class="campo">
        <label for="area">Área</label>
        <select id="area" name="area">
          <option value="">Todas las áreas</option>
          <?php foreach ($areas as $a): ?>
            <option value="<?php echo htmlspecialchars($a["area"]); ?>"
              <?php echo $filtroArea === $a["area"] ? "selected" : ""; ?>>
              <?php echo htmlspecialchars($a["area"]); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn-primario">Consultar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Acumulado por empleado</h2>
    <p class="descripcion">Ordenado de mayor a menor tiempo pendiente por reponer.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Kronos</th>
            <th>Empleado</th>
            <th>Área</th>
            <th>Retardos</th>
            <th>Tiempo de retardo</th>
            <th>Repuesto</th>
            <th>Por reponer</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($ranking)): ?>
            <tr><td colspan="7">No hay retardos en este periodo.</td></tr>
          <?php else: ?>
            <?php foreach ($ranking as $e): ?>
              <?php $ancho = $e["minutos"] > 0 ? round(($e["repuesto"] / $e["minutos"]) * 100) : 0; ?>
              <tr>
                <td><?php echo htmlspecialchars($e["id"]); ?></td>
                <td><?php echo htmlspecialchars($e["nombre"]); ?></td>
                <td><?php echo htmlspecialchars((string) $e["area"]); ?></td>
                <td><?php echo $e["retardos"]; ?></td>
                <td><?php echo formatoTiempo($e["minutos"]); ?></td>
                <td>
                  <div class="celda-progreso">
                    <strong><?php echo formatoTiempo($e["repuesto"]); ?></strong>
                    <div class="barra"><span style="width: <?php echo $ancho; ?>%"></span></div>
                  </div>
                </td>
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
    <h2>Detalle de retardos</h2>
    <p class="descripcion">
      <?php if (count($detalle) > $limiteDetalle): ?>
        Mostrando los <?php echo $limiteDetalle; ?> más recientes de <?php echo count($detalle); ?>. Acota el periodo para ver el resto.
      <?php else: ?>
        <?php echo count($detalle); ?> retardo(s) en el periodo.
      <?php endif; ?>
    </p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>No. Kronos</th>
            <th>Empleado</th>
            <th>Hora de entrada</th>
            <th>Retardo</th>
            <th>Repuesto</th>
            <th>Por reponer</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($detalle)): ?>
            <tr><td colspan="8">No hay retardos en este periodo.</td></tr>
          <?php else: ?>
            <?php foreach (array_slice($detalle, 0, $limiteDetalle) as $r): ?>
              <tr>
                <td><?php echo date("d/m/Y", strtotime($r["fecha_asistencia"])); ?></td>
                <td><?php echo htmlspecialchars($r["id_kronos"]); ?></td>
                <td><?php echo htmlspecialchars($r["nombre"] . " " . $r["apellido_p"] . " " . $r["apellido_m"]); ?></td>
                <td><?php echo $r["hora_entrada"] ? date("H:i", strtotime($r["hora_entrada"])) : "--:--"; ?></td>
                <td><?php echo formatoTiempo($r["min_retardo"]); ?></td>
                <td><?php echo formatoTiempo($r["repuesto"]); ?></td>
                <td><?php echo formatoTiempo($r["pendiente"]); ?></td>
                <td>
                  <?php if ($r["pendiente"] > 0): ?>
                    <span class="badge retardo">Pendiente</span>
                  <?php else: ?>
                    <span class="badge puntual">Repuesto</span>
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

<?php include_once("../../includes/footer.php"); ?>