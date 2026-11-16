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

// Rango [inicio, fin] según periodicidad y fecha de referencia
function rangoPeriodo($periodo, $fecha) {
    $f = new DateTime($fecha);

    if ($periodo === "diario") {
        return [$f->format("Y-m-d"), $f->format("Y-m-d")];
    }
    if ($periodo === "mensual") {
        return [$f->format("Y-m-01"), $f->format("Y-m-t")];
    }
    // trimestral
    $mesIni = (int) (floor(((int) $f->format("n") - 1) / 3) * 3 + 1);
    $d = new DateTime($f->format("Y") . "-" . sprintf("%02d", $mesIni) . "-01");
    $h = new DateTime((clone $d)->modify("+2 months")->format("Y-m-t"));
    return [$d->format("Y-m-d"), $h->format("Y-m-d")];
}

// Da formato a un valor para mostrarlo en pantalla
function celda($valor, $formato) {
    if ($valor === null) return "—";
    switch ($formato) {
        case "pct":    return $valor . "%";
        case "tiempo": return $valor > 0 ? formatoTiempo($valor) : "0 min";
        default:       return $valor;
    }
}

// ===== Catálogo de columnas y tipos de reporte =====
$catalogo = [
    "id"          => ["No. Kronos",          "texto"],
    "nombre"      => ["Empleado",            "texto"],
    "area"        => ["Área",                "texto"],
    "dias"        => ["Días registrados",    "num"],
    "puntuales"   => ["Puntuales",           "num"],
    "retardos"    => ["Retardos",            "num"],
    "faltas"      => ["Faltas",              "num"],
    "pct"         => ["% Asistencia",        "pct"],
    "min_retardo" => ["Tiempo de retardo",   "tiempo"],
    "repuesto"    => ["Repuesto",            "tiempo"],
    "pendiente"   => ["Por reponer",         "tiempo"],
    "capturas"    => ["Capturas",            "num"],
    "prom"        => ["Promedio de puntos",  "num"],
    "total_pts"   => ["Total de puntos",     "num"],
    "max_pts"     => ["Puntaje máximo",      "num"],
];

$tiposReporte = [
    "consolidado"   => ["Consolidado general",       ["id", "nombre", "area", "puntuales", "retardos", "faltas", "pct", "pendiente", "prom"]],
    "asistencia"    => ["Asistencia y puntualidad",  ["id", "nombre", "area", "dias", "puntuales", "retardos", "faltas", "pct"]],
    "retardos"      => ["Retardos y reposición",     ["id", "nombre", "area", "retardos", "min_retardo", "repuesto", "pendiente"]],
    "productividad" => ["Productividad",             ["id", "nombre", "area", "capturas", "prom", "total_pts", "max_pts"]],
];

// ===== Filtros (GET) =====
$tipo = $_GET["tipo"] ?? "consolidado";
if (!isset($tiposReporte[$tipo])) $tipo = "consolidado";

$periodo = $_GET["periodo"] ?? "mensual";
if (!in_array($periodo, ["diario", "mensual", "trimestral", "personalizado"], true)) {
    $periodo = "mensual";
}

$fecha = fechaValida($_GET["fecha"] ?? "") ? $_GET["fecha"] : $hoy;
if ($fecha > $hoy) $fecha = $hoy;

$desdeInput = fechaValida($_GET["desde"] ?? "") ? $_GET["desde"] : date("Y-m-01");
$hastaInput = fechaValida($_GET["hasta"] ?? "") ? $_GET["hasta"] : $hoy;

$filtroArea = trim($_GET["area"] ?? "");

if ($periodo === "personalizado") {
    $desde = $desdeInput;
    $hasta = $hastaInput;
    if ($hasta > $hoy)   $hasta = $hoy;
    if ($desde > $hasta) $desde = $hasta;
} else {
    [$desde, $hasta] = rangoPeriodo($periodo, $fecha);
}

// ===== Áreas para el filtro =====
$areas = $connection->query(
    "SELECT DISTINCT area FROM empleado
     WHERE rol = 'operativo' AND area IS NOT NULL AND area <> ''
     ORDER BY area"
)->fetch_all(MYSQLI_ASSOC);

// ===== Empleados =====
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

// ===== Asistencia del periodo, agrupada por empleado =====
$stmt = $connection->prepare(
    "SELECT id_kronos, hora_entrada, es_falta, es_retardo, minutos_repuestos
     FROM asistencia
     WHERE fecha_asistencia BETWEEN ? AND ?"
);
$stmt->bind_param("ss", $desde, $hasta);
$stmt->execute();

$asis = [];
foreach ($stmt->get_result() as $r) {
    $k = $r["id_kronos"];
    if (!isset($asis[$k])) {
        $asis[$k] = ["dias" => 0, "puntuales" => 0, "retardos" => 0, "faltas" => 0,
                     "min_retardo" => 0, "repuesto" => 0, "pendiente" => 0];
    }
    $asis[$k]["dias"]++;

    if ($r["es_falta"]) {
        $asis[$k]["faltas"]++;
    } elseif ($r["es_retardo"]) {
        $asis[$k]["retardos"]++;
        $min = minutosRetardo($r["hora_entrada"], $HORA_TURNO);
        $rep = min($min, (int) $r["minutos_repuestos"]);
        $asis[$k]["min_retardo"] += $min;
        $asis[$k]["repuesto"]    += $rep;
        $asis[$k]["pendiente"]   += $min - $rep;
    } else {
        $asis[$k]["puntuales"]++;
    }
}
$stmt->close();

// ===== Productividad del periodo, agrupada por empleado =====
$stmt = $connection->prepare(
    "SELECT id_kronos, COUNT(*) AS capturas, AVG(puntos_productividad) AS prom,
            SUM(puntos_productividad) AS total_pts, MAX(puntos_productividad) AS max_pts
     FROM productividad
     WHERE fecha_registro BETWEEN ? AND ?
     GROUP BY id_kronos"
);
$stmt->bind_param("ss", $desde, $hasta);
$stmt->execute();

$prod = [];
foreach ($stmt->get_result() as $r) {
    $prod[$r["id_kronos"]] = $r;
}
$stmt->close();

// ===== Armar filas del reporte =====
$filas = [];
$tot = ["dias" => 0, "puntuales" => 0, "retardos" => 0, "faltas" => 0, "pendiente" => 0];
$sumaProm = 0;
$nProm    = 0;

foreach ($empleados as $e) {
    $k = $e["id_kronos"];
    $a = $asis[$k] ?? ["dias" => 0, "puntuales" => 0, "retardos" => 0, "faltas" => 0,
                       "min_retardo" => 0, "repuesto" => 0, "pendiente" => 0];
    $p = $prod[$k] ?? null;

    $fila = [
        "id"          => $k,
        "nombre"      => trim($e["nombre"] . " " . $e["apellido_p"] . " " . $e["apellido_m"]),
        "area"        => $e["area"],
        "dias"        => $a["dias"],
        "puntuales"   => $a["puntuales"],
        "retardos"    => $a["retardos"],
        "faltas"      => $a["faltas"],
        "pct"         => $a["dias"] > 0 ? round((($a["puntuales"] + $a["retardos"]) / $a["dias"]) * 100) : null,
        "min_retardo" => $a["min_retardo"],
        "repuesto"    => $a["repuesto"],
        "pendiente"   => $a["pendiente"],
        "capturas"    => $p ? (int) $p["capturas"] : 0,
        "prom"        => $p ? round((float) $p["prom"], 1) : null,
        "total_pts"   => $p ? (int) $p["total_pts"] : null,
        "max_pts"     => $p ? (int) $p["max_pts"] : null,
    ];
    $filas[] = $fila;

    foreach (["dias", "puntuales", "retardos", "faltas", "pendiente"] as $campo) {
        $tot[$campo] += $a[$campo];
    }
    if ($p) {
        $sumaProm += (float) $p["prom"];
        $nProm++;
    }
}

$asistenciaGlobal = $tot["dias"] > 0 ? round((($tot["puntuales"] + $tot["retardos"]) / $tot["dias"]) * 100) : 0;
$promedioGlobal   = $nProm > 0 ? round($sumaProm / $nProm, 1) : 0;

$nombreTipo = $tiposReporte[$tipo][0];
$columnas   = $tiposReporte[$tipo][1];

// ===== Exportar a CSV (antes de imprimir cualquier HTML) =====
if (($_GET["exportar"] ?? "") === "csv") {
    header("Content-Type: text/csv; charset=UTF-8");
    header('Content-Disposition: attachment; filename="reporte_' . $tipo . '_' . $desde . '_a_' . $hasta . '.csv"');

    $salida = fopen("php://output", "w");
    fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos

    $encabezados = [];
    foreach ($columnas as $c) {
        $titulo = $catalogo[$c][0];
        if ($catalogo[$c][1] === "tiempo") $titulo .= " (min)";
        if ($catalogo[$c][1] === "pct")    $titulo .= " (%)";
        $encabezados[] = $titulo;
    }
    fputcsv($salida, $encabezados);

    foreach ($filas as $fila) {
        $linea = [];
        foreach ($columnas as $c) {
            $linea[] = $fila[$c] ?? "";
        }
        fputcsv($salida, $linea);
    }
    fclose($salida);
    exit;
}

// Enlace de exportación con los mismos filtros
$enlaceCsv = "reportes.php?" . http_build_query([
    "tipo" => $tipo, "periodo" => $periodo, "fecha" => $fecha,
    "desde" => $desdeInput, "hasta" => $hastaInput, "area" => $filtroArea,
    "exportar" => "csv",
]);

$titulo = "Generador de Reportes";
include_once("../../includes/header.php");
include_once("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Generador de Reportes</h1>
    <p>
      <?php echo htmlspecialchars($nombreTipo); ?> ·
      <?php echo date("d/m/Y", strtotime($desde)); ?>
      <?php if ($desde !== $hasta): ?> al <?php echo date("d/m/Y", strtotime($hasta)); ?><?php endif; ?>
      <?php if ($filtroArea !== ""): ?> · Área: <?php echo htmlspecialchars($filtroArea); ?><?php endif; ?>
    </p>
  </div>

  <section class="panel no-imprimir">
    <h2>Configurar reporte</h2>
    <p class="descripcion">Elige el tipo, el periodo y el área. "Desde" y "Hasta" solo se usan con el periodo personalizado.</p>

    <form class="form-rapido" method="get" action="reportes.php">
      <div class="campo">
        <label for="tipo">Tipo de reporte</label>
        <select id="tipo" name="tipo">
          <?php foreach ($tiposReporte as $clave => $def): ?>
            <option value="<?php echo $clave; ?>" <?php echo $tipo === $clave ? "selected" : ""; ?>>
              <?php echo htmlspecialchars($def[0]); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="periodo">Periodo</label>
        <select id="periodo" name="periodo">
          <option value="diario"        <?php echo $periodo === "diario"        ? "selected" : ""; ?>>Diario</option>
          <option value="mensual"       <?php echo $periodo === "mensual"       ? "selected" : ""; ?>>Mensual</option>
          <option value="trimestral"    <?php echo $periodo === "trimestral"    ? "selected" : ""; ?>>Trimestral</option>
          <option value="personalizado" <?php echo $periodo === "personalizado" ? "selected" : ""; ?>>Personalizado</option>
        </select>
      </div>
      <div class="campo">
        <label for="fecha">Fecha de referencia</label>
        <input type="date" id="fecha" name="fecha" max="<?php echo $hoy; ?>"
               value="<?php echo htmlspecialchars($fecha); ?>">
      </div>
      <div class="campo">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" max="<?php echo $hoy; ?>"
               value="<?php echo htmlspecialchars($desdeInput); ?>">
      </div>
      <div class="campo">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" max="<?php echo $hoy; ?>"
               value="<?php echo htmlspecialchars($hastaInput); ?>">
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
      <button type="submit" class="btn-primario">Generar reporte</button>
      <a class="btn-secundario" href="<?php echo htmlspecialchars($enlaceCsv); ?>">Exportar CSV</a>
      <button type="button" class="btn-secundario" onclick="window.print()">Imprimir</button>
    </form>
  </section>

  <section class="tarjetas">
    <div class="tarjeta">
      <span class="etiqueta">Empleados</span>
      <span class="valor"><?php echo count($empleados); ?></span>
      <span class="detalle">Incluidos en el reporte</span>
    </div>
    <div class="tarjeta ok">
      <span class="etiqueta">Asistencia</span>
      <span class="valor"><?php echo $asistenciaGlobal; ?>%</span>
      <span class="detalle"><?php echo $tot["puntuales"]; ?> entradas puntuales</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos</span>
      <span class="valor"><?php echo $tot["retardos"]; ?></span>
      <span class="detalle"><?php echo formatoTiempo($tot["pendiente"]); ?> por reponer</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Faltas</span>
      <span class="valor"><?php echo $tot["faltas"]; ?></span>
      <span class="detalle">En el periodo</span>
    </div>
    <div class="tarjeta acento">
      <span class="etiqueta">Productividad</span>
      <span class="valor"><?php echo $promedioGlobal; ?></span>
      <span class="detalle">Promedio de puntos (<?php echo $nProm; ?> empleados)</span>
    </div>
  </section>

  <section class="panel">
    <h2><?php echo htmlspecialchars($nombreTipo); ?></h2>
    <p class="descripcion">Resultado por empleado del periodo seleccionado.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <?php foreach ($columnas as $c): ?>
              <th><?php echo htmlspecialchars($catalogo[$c][0]); ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filas)): ?>
            <tr><td colspan="<?php echo count($columnas); ?>">No hay empleados para mostrar.</td></tr>
          <?php else: ?>
            <?php foreach ($filas as $fila): ?>
              <tr>
                <?php foreach ($columnas as $c): ?>
                  <td><?php echo htmlspecialchars((string) celda($fila[$c], $catalogo[$c][1])); ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include_once("../../includes/footer.php"); ?>