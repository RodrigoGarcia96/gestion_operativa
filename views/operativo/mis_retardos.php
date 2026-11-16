<?php
// ===== Depuración: muestra los errores en pantalla. Bórralo cuando todo funcione =====
ini_set("display_errors", 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include_once("../../includes/auth.php");
requireRole(["operativo"]);
include_once("../../config/connection.php");
include_once("../../config/reglas.php");

date_default_timezone_set("America/Mexico_City");

// El operativo SOLO ve sus propios datos: el Kronos sale de la sesión, nunca de la URL
$miKronos = (int) $_SESSION["id_kronos"];

// ===== Funciones auxiliares =====
function minutosRetardo($hora, $horaTurno) {
    if (!$hora) return 0;
    return max(0, (int) ceil((strtotime($hora) - strtotime($horaTurno)) / 60));
}

function formatoTiempo($min) {
    if ($min < 60) return $min . " min";
    return floor($min / 60) . " h " . ($min % 60) . " min";
}

// ===== Mes a consultar (por defecto, el mes actual) =====
$mesActual = date("Y-m");
$mes = $_GET["mes"] ?? $mesActual;
$d = DateTime::createFromFormat("Y-m", $mes);
if (!$d || $d->format("Y-m") !== $mes || $mes > $mesActual) {
    $mes = $mesActual;
}

// ===== Todos mis retardos (para calcular el saldo histórico) =====
$stmt = $connection->prepare(
    "SELECT fecha_asistencia, hora_entrada, minutos_repuestos
     FROM asistencia
     WHERE id_kronos = ? AND es_retardo = 1
     ORDER BY fecha_asistencia DESC"
);
$stmt->bind_param("i", $miKronos);
$stmt->execute();
$todos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== Cálculos =====
$saldoHistorico = 0;     // por reponer de todos los meses
$retardosMes    = 0;
$minutosMes     = 0;
$repuestosMes   = 0;
$pendienteMes   = 0;
$delMes         = [];

foreach ($todos as $r) {
    $minRetardo = minutosRetardo($r["hora_entrada"], $HORA_TURNO);
    $repuestos  = min($minRetardo, (int) $r["minutos_repuestos"]);
    $pendiente  = max(0, $minRetardo - $repuestos);

    $saldoHistorico += $pendiente;

    if (substr($r["fecha_asistencia"], 0, 7) === $mes) {
        $retardosMes++;
        $minutosMes   += $minRetardo;
        $repuestosMes += $repuestos;
        $pendienteMes += $pendiente;

        $r["min_retardo"] = $minRetardo;
        $r["repuestos"]   = $repuestos;
        $r["pendiente"]   = $pendiente;
        $delMes[] = $r;
    }
}

$titulo = "Mis Retardos y Tiempo Acumulado";
include_once("../../includes/header.php");
include_once("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Mis Retardos y Tiempo Acumulado</h1>
    <p>Consulta de tus retardos y del tiempo que tienes por reponer · No. Kronos: <?php echo $miKronos; ?></p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta peligro">
      <span class="etiqueta">Saldo total por reponer</span>
      <span class="valor"><?php echo formatoTiempo($saldoHistorico); ?></span>
      <span class="detalle">Acumulado de todos los meses</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos del mes</span>
      <span class="valor"><?php echo $retardosMes; ?></span>
      <span class="detalle"><?php echo formatoTiempo($minutosMes); ?> en total</span>
    </div>
    <div class="tarjeta ok">
      <span class="etiqueta">Repuesto en el mes</span>
      <span class="valor"><?php echo formatoTiempo($repuestosMes); ?></span>
      <span class="detalle">Tiempo ya compensado</span>
    </div>
    <div class="tarjeta acento">
      <span class="etiqueta">Por reponer del mes</span>
      <span class="valor"><?php echo formatoTiempo($pendienteMes); ?></span>
      <span class="detalle">Pendiente de este mes</span>
    </div>
  </section>

  <section class="panel">
    <h2>Mes a consultar</h2>
    <p class="descripcion">El saldo total siempre incluye todos los meses; la tabla muestra el mes elegido.</p>

    <form class="form-rapido" method="get" action="mis_retardos.php">
      <div class="campo">
        <label for="mes">Mes</label>
        <input type="month" id="mes" name="mes" max="<?php echo $mesActual; ?>"
               value="<?php echo htmlspecialchars($mes); ?>">
      </div>
      <button type="submit" class="btn-primario">Consultar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Detalle de mis retardos</h2>
    <p class="descripcion">Tiempo de retardo, reposición y saldo pendiente por cada día.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora de entrada</th>
            <th>Retardo</th>
            <th>Repuesto</th>
            <th>Por reponer</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($delMes)): ?>
            <tr><td colspan="6">No tienes retardos en este mes.</td></tr>
          <?php else: ?>
            <?php foreach ($delMes as $r): ?>
              <?php $ancho = $r["min_retardo"] > 0 ? round(($r["repuestos"] / $r["min_retardo"]) * 100) : 0; ?>
              <tr>
                <td><?php echo date("d/m/Y", strtotime($r["fecha_asistencia"])); ?></td>
                <td><?php echo $r["hora_entrada"] ? date("H:i", strtotime($r["hora_entrada"])) : "--:--"; ?></td>
                <td><?php echo formatoTiempo($r["min_retardo"]); ?></td>
                <td>
                  <div class="celda-progreso">
                    <strong><?php echo formatoTiempo($r["repuestos"]); ?></strong>
                    <div class="barra"><span style="width: <?php echo $ancho; ?>%"></span></div>
                  </div>
                </td>
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