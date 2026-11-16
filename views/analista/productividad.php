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

// Promedio de puntos por empleado en un rango: [id_kronos => ["prom" => x, "n" => y]]
function promedios($cx, $desde, $hasta) {
    $stmt = $cx->prepare(
        "SELECT id_kronos, AVG(puntos_productividad) AS prom, COUNT(*) AS n
         FROM productividad
         WHERE fecha_registro BETWEEN ? AND ?
         GROUP BY id_kronos"
    );
    $stmt->bind_param("ss", $desde, $hasta);
    $stmt->execute();
    $mapa = [];
    foreach ($stmt->get_result() as $r) {
        $mapa[$r["id_kronos"]] = ["prom" => (float) $r["prom"], "n" => (int) $r["n"]];
    }
    $stmt->close();
    return $mapa;
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

// ===== Empleados (filtrados por área si se eligió) =====
if ($filtroArea !== "") {
    $stmt = $connection->prepare(
        "SELECT id_kronos, nombre, apellido_p, apellido_m, area
         FROM empleado WHERE rol = 'operativo' AND area = ?
         ORDER BY nombre, apellido_p"
    );
    $stmt->bind_param("s", $filtroArea);
    $stmt->execute();
    $empleados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $empleados = $connection->query(
        "SELECT id_kronos, nombre, apellido_p, apellido_m, area
         FROM empleado WHERE rol = 'operativo'
         ORDER BY nombre, apellido_p"
    )->fetch_all(MYSQLI_ASSOC);
}

// ===== Productividad del periodo y del periodo anterior =====
$actual   = promedios($connection, $desde, $hasta);
$anterior = promedios($connection, $desdeAnt, $hastaAnt);

// ===== Tiempo de retardo por reponer en el periodo =====
$stmt = $connection->prepare(
    "SELECT id_kronos, hora_entrada, minutos_repuestos
     FROM asistencia
     WHERE es_retardo = 1 AND fecha_asistencia BETWEEN ? AND ?"
);
$stmt->bind_param("ss", $desde, $hasta);
$stmt->execute();
$pendientes = [];
foreach ($stmt->get_result() as $r) {
    $k = $r["id_kronos"];
    $falta = minutosRetardo($r["hora_entrada"], $HORA_TURNO) - (int) $r["minutos_repuestos"];
    $pendientes[$k] = ($pendientes[$k] ?? 0) + max(0, $falta);
}
$stmt->close();

// ===== Armar filas y totales =====
$filas = [];
$sumaActual = 0;  $nActual = 0;
$sumaAnt    = 0;  $nAnt    = 0;
$totalPendiente = 0;
$mejor = null;

foreach ($empleados as $e) {
    $k   = $e["id_kronos"];
    $cur = $actual[$k]["prom"]   ?? null;
    $ant = $anterior[$k]["prom"] ?? null;
    $pen = $pendientes[$k] ?? 0;

    $e["resultado"]  = $cur;
    $e["anterior"]   = $ant;
    $e["capturas"]   = $actual[$k]["n"] ?? 0;
    $e["variacion"]  = ($cur !== null && $ant !== null) ? round($cur - $ant, 1) : null;
    $e["pendiente"]  = $pen;
    $filas[] = $e;

    if ($cur !== null) {
        $sumaActual += $cur;
        $nActual++;
        if ($mejor === null || $cur > $mejor["resultado"]) $mejor = $e;
    }
    if ($ant !== null) {
        $sumaAnt += $ant;
        $nAnt++;
    }
    $totalPendiente += $pen;
}

$promedioArea   = $nActual > 0 ? round($sumaActual / $nActual, 1) : 0;
$promedioAntArea = $nAnt > 0 ? round($sumaAnt / $nAnt, 1) : null;
$variacionArea  = ($nActual > 0 && $promedioAntArea !== null) ? round($promedioArea - $promedioAntArea, 1) : null;

// Mayor resultado primero; los que no tienen captura al final
usort($filas, function ($a, $b) {
    if ($a["resultado"] === null && $b["resultado"] === null) return 0;
    if ($a["resultado"] === null) return 1;
    if ($b["resultado"] === null) return -1;
    return $b["resultado"] <=> $a["resultado"];
});

$maxResultado = $nActual > 0 ? max(array_filter(array_column($filas, "resultado"), fn($v) => $v !== null)) : 0;

$etiquetaPeriodo = ["diario" => "Diario", "mensual" => "Mensual", "trimestral" => "Trimestral"][$periodo];

$titulo = "Análisis de Productividad";
include_once("../../includes/header.php");
include_once("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Análisis de Productividad</h1>
    <p>
      Análisis comparativo <?php echo strtolower($etiquetaPeriodo); ?> ·
      <?php echo date("d/m/Y", strtotime($desde)); ?>
      <?php if ($desde !== $hasta): ?> al <?php echo date("d/m/Y", strtotime($hasta)); ?><?php endif; ?>
    </p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta acento">
      <span class="etiqueta">Promedio de puntos</span>
      <span class="valor"><?php echo $promedioArea; ?></span>
      <span class="detalle"><?php echo $nActual; ?> de <?php echo count($empleados); ?> con captura</span>
    </div>
    <div class="tarjeta <?php echo ($variacionArea !== null && $variacionArea < 0) ? 'alerta' : 'ok'; ?>">
      <span class="etiqueta">Vs. periodo anterior</span>
      <span class="valor">
        <?php echo $variacionArea !== null ? (($variacionArea > 0 ? "+" : "") . $variacionArea) : "—"; ?>
      </span>
      <span class="detalle">
        <?php echo $promedioAntArea !== null ? "Periodo anterior: " . $promedioAntArea : "Sin datos para comparar"; ?>
      </span>
    </div>
    <div class="tarjeta ok">
      <span class="etiqueta">Mejor desempeño</span>
      <span class="valor"><?php echo $mejor ? round($mejor["resultado"], 1) : "—"; ?></span>
      <span class="detalle"><?php echo $mejor ? htmlspecialchars($mejor["nombre"] . " " . $mejor["apellido_p"]) : "Sin capturas"; ?></span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Tiempo por reponer</span>
      <span class="valor"><?php echo formatoTiempo($totalPendiente); ?></span>
      <span class="detalle">Retardos del periodo</span>
    </div>
  </section>

  <section class="panel">
    <h2>Filtros</h2>
    <p class="descripcion">Elige la periodicidad, una fecha dentro del periodo y, si quieres, un área.</p>

    <form class="form-rapido" method="get" action="productividad.php">
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
    <h2>Análisis comparativo de productividad</h2>
    <p class="descripcion">
      Resultado del periodo contra el periodo anterior
      (<?php echo date("d/m/Y", strtotime($desdeAnt)); ?>
      <?php if ($desdeAnt !== $hastaAnt): ?> al <?php echo date("d/m/Y", strtotime($hastaAnt)); ?><?php endif; ?>).
    </p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Kronos</th>
            <th>Personal</th>
            <th>Resultado del periodo</th>
            <th>Periodo anterior</th>
            <th>Variación</th>
            <th>Capturas</th>
            <th>Tiempo de retardo acum.</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filas)): ?>
            <tr><td colspan="7">No hay empleados para mostrar.</td></tr>
          <?php else: ?>
            <?php foreach ($filas as $f): ?>
              <tr>
                <td><?php echo htmlspecialchars($f["id_kronos"]); ?></td>
                <td>
                  <?php echo htmlspecialchars($f["nombre"] . " " . $f["apellido_p"] . " " . $f["apellido_m"]); ?>
                  <?php if (!empty($f["area"])): ?>
                    <br><small style="color: var(--texto-suave);"><?php echo htmlspecialchars($f["area"]); ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($f["resultado"] !== null): ?>
                    <?php $ancho = $maxResultado > 0 ? round(($f["resultado"] / $maxResultado) * 100) : 0; ?>
                    <div class="celda-progreso">
                      <strong><?php echo round($f["resultado"], 1); ?></strong>
                      <div class="barra"><span style="width: <?php echo $ancho; ?>%"></span></div>
                    </div>
                  <?php else: ?>
                    <span class="badge pendiente">Sin captura</span>
                  <?php endif; ?>
                </td>
                <td><?php echo $f["anterior"] !== null ? round($f["anterior"], 1) : "—"; ?></td>
                <td>
                  <?php if ($f["variacion"] === null): ?>
                    —
                  <?php elseif ($f["variacion"] > 0): ?>
                    <span class="badge puntual">▲ +<?php echo $f["variacion"]; ?></span>
                  <?php elseif ($f["variacion"] < 0): ?>
                    <span class="badge falta">▼ <?php echo $f["variacion"]; ?></span>
                  <?php else: ?>
                    <span class="badge pendiente">= 0</span>
                  <?php endif; ?>
                </td>
                <td><?php echo (int) $f["capturas"]; ?></td>
                <td><?php echo $f["pendiente"] > 0 ? formatoTiempo($f["pendiente"]) : "0 min"; ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include_once("../../includes/footer.php"); ?>