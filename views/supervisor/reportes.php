<?php
// ===== Depuración: muestra los errores en pantalla. Bórralo cuando todo funcione =====
ini_set("display_errors", 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include_once("../../includes/auth.php");
requireRole(["supervisor"]);
include_once("../../config/connection.php");
include_once("../../config/reglas.php");

date_default_timezone_set("America/Mexico_City");
$hoy = date("Y-m-d");

// ===== Funciones auxiliares =====
function minutosRetardo($hora, $horaTurno) {
    if (!$hora) return 0;
    return max(0, (int) ceil((strtotime($hora) - strtotime($horaTurno)) / 60));
}

function formatoTiempo($min) {
    if ($min < 60) return $min . " min";
    return floor($min / 60) . " h " . ($min % 60) . " min";
}

function fechaValida($f) {
    $d = DateTime::createFromFormat("Y-m-d", $f);
    return $d && $d->format("Y-m-d") === $f;
}

// ===== Fecha del reporte (por defecto hoy; no se permiten fechas futuras) =====
$fecha = fechaValida($_GET["fecha"] ?? "") ? $_GET["fecha"] : $hoy;
if ($fecha > $hoy) $fecha = $hoy;

// ===== Consulta: plantilla operativa con su asistencia y productividad del día =====
$stmt = $connection->prepare(
    "SELECT e.id_kronos, e.nombre, e.apellido_p, e.apellido_m, e.area,
            a.id_asistencia, a.hora_entrada, a.es_falta, a.es_retardo, a.minutos_repuestos,
            p.puntos_productividad
     FROM empleado e
     LEFT JOIN asistencia a
            ON a.id_kronos = e.id_kronos AND a.fecha_asistencia = ?
     LEFT JOIN productividad p
            ON p.id_kronos = e.id_kronos AND p.fecha_registro = ?
     WHERE e.rol = 'operativo'
     ORDER BY e.nombre, e.apellido_p"
);
$stmt->bind_param("ss", $fecha, $fecha);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== Cálculos por fila y totales =====
$plantilla   = count($filas);
$puntuales   = 0;
$retardos    = 0;
$faltas      = 0;
$sinRegistro = 0;
$sumaPuntos  = 0;
$conPuntos   = 0;

foreach ($filas as $i => $f) {
    if ($f["id_asistencia"] === null) {
        $estatus = ["pendiente", "Sin registro"];
        $sinRegistro++;
    } elseif ($f["es_falta"]) {
        $estatus = ["falta", "Falta"];
        $faltas++;
    } elseif ($f["es_retardo"]) {
        $estatus = ["retardo", "Retardo"];
        $retardos++;
    } else {
        $estatus = ["puntual", "Puntual"];
        $puntuales++;
    }

    $minRetardo = $f["es_retardo"] ? minutosRetardo($f["hora_entrada"], $HORA_TURNO) : 0;
    $pendiente  = $f["es_retardo"] ? max(0, $minRetardo - (int) $f["minutos_repuestos"]) : 0;

    $filas[$i]["estatus"]     = $estatus;
    $filas[$i]["min_retardo"] = $minRetardo;
    $filas[$i]["pendiente"]   = $pendiente;

    if ($f["puntos_productividad"] !== null) {
        $sumaPuntos += (int) $f["puntos_productividad"];
        $conPuntos++;
    }
}

$presentes      = $puntuales + $retardos;
$asistenciaPct  = $plantilla > 0 ? round(($presentes / $plantilla) * 100) : 0;
$promedioPuntos = $conPuntos > 0 ? round($sumaPuntos / $conPuntos, 1) : 0;

// ===== Exportar a CSV (antes de imprimir cualquier HTML) =====
if (($_GET["exportar"] ?? "") === "csv") {
    header("Content-Type: text/csv; charset=UTF-8");
    header('Content-Disposition: attachment; filename="reporte_operativo_' . $fecha . '.csv"');

    $salida = fopen("php://output", "w");
    fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos
    fputcsv($salida, ["No. Kronos", "Empleado", "Area", "Hora de entrada",
                      "Estatus", "Minutos de retardo", "Minutos por reponer", "Puntos de productividad"]);

    foreach ($filas as $f) {
        fputcsv($salida, [
            $f["id_kronos"],
            $f["nombre"] . " " . $f["apellido_p"] . " " . $f["apellido_m"],
            $f["area"],
            $f["hora_entrada"] ? date("H:i", strtotime($f["hora_entrada"])) : "",
            $f["estatus"][1],
            $f["min_retardo"],
            $f["pendiente"],
            $f["puntos_productividad"] ?? "",
        ]);
    }
    fclose($salida);
    exit;
}

$titulo = "Reportes Operativos del Día";
include_once("../../includes/header.php");
include_once("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Reportes Operativos del Día</h1>
    <p>Consolidado de asistencia, puntualidad y desempeño · <?php echo date("d/m/Y", strtotime($fecha)); ?></p>
  </div>

  <section class="panel no-imprimir">
    <h2>Fecha del reporte</h2>
    <p class="descripcion">Consulta el día de hoy o uno anterior.</p>

    <form class="form-rapido" method="get" action="reportes.php">
      <div class="campo">
        <label for="fecha">Fecha</label>
        <input type="date" id="fecha" name="fecha" max="<?php echo $hoy; ?>"
               value="<?php echo htmlspecialchars($fecha); ?>">
      </div>
      <button type="submit" class="btn-primario">Consultar</button>
      <a class="btn-secundario" href="reportes.php?fecha=<?php echo urlencode($fecha); ?>&exportar=csv">Exportar CSV</a>
      <button type="button" class="btn-secundario" onclick="window.print()">Imprimir</button>
    </form>
  </section>

  <section class="tarjetas">
    <div class="tarjeta">
      <span class="etiqueta">Plantilla operativa</span>
      <span class="valor"><?php echo $plantilla; ?></span>
      <span class="detalle">Empleados con rol operativo</span>
    </div>
    <div class="tarjeta ok">
      <span class="etiqueta">Asistencia</span>
      <span class="valor"><?php echo $asistenciaPct; ?>%</span>
      <span class="detalle"><?php echo $presentes; ?> presentes · <?php echo $puntuales; ?> puntuales</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos</span>
      <span class="valor"><?php echo $retardos; ?></span>
      <span class="detalle">Entradas con retardo</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Faltas</span>
      <span class="valor"><?php echo $faltas; ?></span>
      <span class="detalle">Sin registro: <?php echo $sinRegistro; ?></span>
    </div>
    <div class="tarjeta acento">
      <span class="etiqueta">Productividad</span>
      <span class="valor"><?php echo $promedioPuntos; ?></span>
      <span class="detalle">Promedio de puntos (<?php echo $conPuntos; ?> capturas)</span>
    </div>
  </section>

  <section class="panel">
    <h2>Detalle por empleado</h2>
    <p class="descripcion">Asistencia, tiempo por reponer y productividad del día.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Kronos</th>
            <th>Empleado</th>
            <th>Área</th>
            <th>Hora de entrada</th>
            <th>Estatus</th>
            <th>Por reponer</th>
            <th>Puntos</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filas)): ?>
            <tr><td colspan="7">No hay empleados operativos registrados.</td></tr>
          <?php else: ?>
            <?php foreach ($filas as $f): ?>
              <tr>
                <td><?php echo htmlspecialchars($f["id_kronos"]); ?></td>
                <td><?php echo htmlspecialchars($f["nombre"] . " " . $f["apellido_p"] . " " . $f["apellido_m"]); ?></td>
                <td><?php echo htmlspecialchars($f["area"]); ?></td>
                <td><?php echo $f["hora_entrada"] ? date("H:i", strtotime($f["hora_entrada"])) : "--:--"; ?></td>
                <td><span class="badge <?php echo $f["estatus"][0]; ?>"><?php echo $f["estatus"][1]; ?></span></td>
                <td><?php echo $f["pendiente"] > 0 ? formatoTiempo($f["pendiente"]) : "—"; ?></td>
                <td><?php echo $f["puntos_productividad"] !== null ? (int) $f["puntos_productividad"] : "—"; ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include_once("../../includes/footer.php"); ?>