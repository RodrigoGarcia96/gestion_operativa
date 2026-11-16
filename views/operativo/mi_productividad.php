<?php
// ===== Depuración: muestra los errores en pantalla. Bórralo cuando todo funcione =====
ini_set("display_errors", 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include_once("../../includes/auth.php");
requireRole(["operativo"]);
include_once("../../config/connection.php");

date_default_timezone_set("America/Mexico_City");

// El operativo SOLO ve sus propios datos: el Kronos sale de la sesión, nunca de la URL
$miKronos = (int) $_SESSION["id_kronos"];

// ===== Mes a consultar (por defecto, el mes actual) =====
$mesActual = date("Y-m");
$mes = $_GET["mes"] ?? $mesActual;
$d = DateTime::createFromFormat("Y-m", $mes);
if (!$d || $d->format("Y-m") !== $mes || $mes > $mesActual) {
    $mes = $mesActual;
}
$desde = $mes . "-01";
$hasta = date("Y-m-t", strtotime($desde));

// Mes anterior, para comparar
$mesAnterior = date("Y-m", strtotime($desde . " -1 month"));
$desdeAnt    = $mesAnterior . "-01";
$hastaAnt    = date("Y-m-t", strtotime($desdeAnt));

// ===== Mis registros de productividad del mes =====
$stmt = $connection->prepare(
    "SELECT fecha_registro, puntos_productividad, comparacion_periodo
     FROM productividad
     WHERE id_kronos = ? AND fecha_registro BETWEEN ? AND ?
     ORDER BY fecha_registro DESC"
);
$stmt->bind_param("iss", $miKronos, $desde, $hasta);
$stmt->execute();
$registros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== Promedio del mes anterior =====
$stmt = $connection->prepare(
    "SELECT AVG(puntos_productividad) AS promedio
     FROM productividad
     WHERE id_kronos = ? AND fecha_registro BETWEEN ? AND ?"
);
$stmt->bind_param("iss", $miKronos, $desdeAnt, $hastaAnt);
$stmt->execute();
$promAnt = $stmt->get_result()->fetch_assoc()["promedio"];
$stmt->close();

// ===== Cálculos =====
$totalRegistros = count($registros);
$promedio = 0;
$maximo   = 0;

if ($totalRegistros > 0) {
    $puntos   = array_column($registros, "puntos_productividad");
    $promedio = round(array_sum($puntos) / $totalRegistros, 1);
    $maximo   = max($puntos);
}

// Variación contra el mes anterior (solo si hay datos en ambos meses)
$variacion = null;
if ($promAnt !== null && $totalRegistros > 0) {
    $variacion = round($promedio - (float) $promAnt, 1);
}

$titulo = "Mi Productividad";
include_once("../../includes/header.php");
include_once("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Mi Productividad</h1>
    <p>Consulta de tu desempeño individual · No. Kronos: <?php echo $miKronos; ?></p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta acento">
      <span class="etiqueta">Promedio del mes</span>
      <span class="valor"><?php echo $promedio; ?></span>
      <span class="detalle">Puntos de productividad</span>
    </div>
    <div class="tarjeta ok">
      <span class="etiqueta">Puntaje más alto</span>
      <span class="valor"><?php echo $maximo; ?></span>
      <span class="detalle">Mejor día del mes</span>
    </div>
    <div class="tarjeta">
      <span class="etiqueta">Registros del mes</span>
      <span class="valor"><?php echo $totalRegistros; ?></span>
      <span class="detalle">Días con captura</span>
    </div>
    <div class="tarjeta <?php echo ($variacion !== null && $variacion < 0) ? 'alerta' : 'ok'; ?>">
      <span class="etiqueta">Vs. mes anterior</span>
      <span class="valor">
        <?php echo $variacion !== null ? (($variacion > 0 ? "+" : "") . $variacion) : "—"; ?>
      </span>
      <span class="detalle">
        <?php echo $variacion !== null
          ? "Promedio anterior: " . round((float) $promAnt, 1)
          : "Sin datos para comparar"; ?>
      </span>
    </div>
  </section>

  <section class="panel">
    <h2>Mes a consultar</h2>
    <p class="descripcion">Elige el mes para ver tus resultados.</p>

    <form class="form-rapido" method="get" action="mi_productividad.php">
      <div class="campo">
        <label for="mes">Mes</label>
        <input type="month" id="mes" name="mes" max="<?php echo $mesActual; ?>"
               value="<?php echo htmlspecialchars($mes); ?>">
      </div>
      <button type="submit" class="btn-primario">Consultar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Mis resultados</h2>
    <p class="descripcion">Puntos de productividad registrados por día.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Puntos</th>
            <th>Periodo</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($registros)): ?>
            <tr><td colspan="3">No tienes registros de productividad en este mes.</td></tr>
          <?php else: ?>
            <?php foreach ($registros as $r): ?>
              <?php $ancho = $maximo > 0 ? round(($r["puntos_productividad"] / $maximo) * 100) : 0; ?>
              <tr>
                <td><?php echo date("d/m/Y", strtotime($r["fecha_registro"])); ?></td>
                <td>
                  <div class="celda-progreso">
                    <strong><?php echo (int) $r["puntos_productividad"]; ?></strong>
                    <div class="barra"><span style="width: <?php echo $ancho; ?>%"></span></div>
                  </div>
                </td>
                <td><span class="badge pendiente"><?php echo htmlspecialchars($r["comparacion_periodo"]); ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include_once("../../includes/footer.php"); ?>