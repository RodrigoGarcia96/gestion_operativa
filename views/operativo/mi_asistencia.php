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
$desde = $mes . "-01";
$hasta = date("Y-m-t", strtotime($desde));

// ===== Datos del colaborador =====
$stmt = $connection->prepare(
    "SELECT nombre, apellido_p, apellido_m, area FROM empleado WHERE id_kronos = ?"
);
$stmt->bind_param("i", $miKronos);
$stmt->execute();
$yo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$nombreCompleto = $yo ? trim($yo["nombre"] . " " . $yo["apellido_p"] . " " . $yo["apellido_m"]) : "";

// ===== Mis registros de asistencia del mes =====
$stmt = $connection->prepare(
    "SELECT fecha_asistencia, hora_entrada, es_falta, es_retardo, minutos_repuestos
     FROM asistencia
     WHERE id_kronos = ? AND fecha_asistencia BETWEEN ? AND ?
     ORDER BY fecha_asistencia DESC"
);
$stmt->bind_param("iss", $miKronos, $desde, $hasta);
$stmt->execute();
$registros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== Cálculos =====
$puntuales = 0;
$retardos  = 0;
$faltas    = 0;
$totalPorReponer = 0;

foreach ($registros as $i => $r) {
    if ($r["es_falta"]) {
        $estatus = ["falta", "Falta"];
        $faltas++;
    } elseif ($r["es_retardo"]) {
        $estatus = ["retardo", "Retardo"];
        $retardos++;
    } else {
        $estatus = ["puntual", "Puntual"];
        $puntuales++;
    }

    $pendiente = 0;
    if ($r["es_retardo"]) {
        $pendiente = max(0, minutosRetardo($r["hora_entrada"], $HORA_TURNO) - (int) $r["minutos_repuestos"]);
        $totalPorReponer += $pendiente;
    }

    $registros[$i]["estatus"]   = $estatus;
    $registros[$i]["pendiente"] = $pendiente;
}

$entradas = $puntuales + $retardos;

$titulo = "Mi Asistencia y Entradas";
include_once("../../includes/header.php");
include_once("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Mi Asistencia y Entradas</h1>
    <p>
      Colaborador: <?php echo htmlspecialchars($nombreCompleto); ?>
      · No. Kronos: <?php echo $miKronos; ?>
      <?php if ($yo && !empty($yo["area"])): ?>
        · Área: <?php echo htmlspecialchars($yo["area"]); ?>
      <?php endif; ?>
    </p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta ok">
      <span class="etiqueta">Entradas</span>
      <span class="valor"><?php echo $entradas; ?></span>
      <span class="detalle"><?php echo $puntuales; ?> puntuales</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos</span>
      <span class="valor"><?php echo $retardos; ?></span>
      <span class="detalle">En el mes</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Faltas</span>
      <span class="valor"><?php echo $faltas; ?></span>
      <span class="detalle">En el mes</span>
    </div>
    <div class="tarjeta acento">
      <span class="etiqueta">Tiempo por reponer</span>
      <span class="valor"><?php echo formatoTiempo($totalPorReponer); ?></span>
      <span class="detalle">Saldo pendiente del mes</span>
    </div>
  </section>

  <section class="panel">
    <h2>Mes a consultar</h2>
    <p class="descripcion">Elige el mes para ver tus registros.</p>

    <form class="form-rapido" method="get" action="mi_asistencia.php">
      <div class="campo">
        <label for="mes">Mes</label>
        <input type="month" id="mes" name="mes" max="<?php echo $mesActual; ?>"
               value="<?php echo htmlspecialchars($mes); ?>">
      </div>
      <button type="submit" class="btn-primario">Consultar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Mis registros</h2>
    <p class="descripcion">Entradas, estatus de asistencia y tiempo por reponer.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora de entrada</th>
            <th>Estatus de asistencia</th>
            <th>Tiempo por reponer</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($registros)): ?>
            <tr><td colspan="4">No tienes registros de asistencia en este mes.</td></tr>
          <?php else: ?>
            <?php foreach ($registros as $r): ?>
              <tr>
                <td><?php echo date("d/m/Y", strtotime($r["fecha_asistencia"])); ?></td>
                <td><?php echo $r["hora_entrada"] ? date("H:i", strtotime($r["hora_entrada"])) : "--:--"; ?></td>
                <td><span class="badge <?php echo $r["estatus"][0]; ?>"><?php echo $r["estatus"][1]; ?></span></td>
                <td><?php echo formatoTiempo($r["pendiente"]); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include_once("../../includes/footer.php"); ?>