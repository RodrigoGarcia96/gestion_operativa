<?php
include("../../includes/auth.php");
requireRole(["supervisor"]);
include("../../config/connection.php");

date_default_timezone_set("America/Mexico_City");
$hoy = date("Y-m-d");

// ===== Registrar o corregir la productividad de hoy =====
if (!empty($_POST["btn_guardar"])) {
    $kronos = trim($_POST["kronos"] ?? "");
    $puntos = trim($_POST["puntos"] ?? "");
    $flash  = null;

    if ($kronos === "" || !ctype_digit($kronos)) {
        $flash = ["error", "Captura un número de Kronos válido."];
    } elseif ($puntos === "" || !ctype_digit($puntos)) {
        $flash = ["error", "Los puntos deben ser un número entero (0 o mayor)."];
    } else {
        $puntos = (int) $puntos;

        // 1. El empleado debe existir
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

            // 2. ¿Ya tiene captura hoy? Si existe se corrige, si no se crea
            $stmt = $connection->prepare(
                "SELECT id_productividad FROM productividad
                 WHERE id_kronos = ? AND fecha_registro = ?"
            );
            $stmt->bind_param("is", $kronos, $hoy);
            $stmt->execute();
            $existente = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existente) {
                $stmt = $connection->prepare(
                    "UPDATE productividad SET puntos_productividad = ?
                     WHERE id_productividad = ?"
                );
                $stmt->bind_param("ii", $puntos, $existente["id_productividad"]);
                $stmt->execute();
                $stmt->close();
                $flash = ["ok", "Productividad de $nombre actualizada: $puntos puntos."];
            } else {
                $periodo = "Diario";
                $stmt = $connection->prepare(
                    "INSERT INTO productividad (id_kronos, puntos_productividad, comparacion_periodo, fecha_registro)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("iiss", $kronos, $puntos, $periodo, $hoy);
                $stmt->execute();
                $stmt->close();
                $flash = ["ok", "Productividad registrada para $nombre: $puntos puntos."];
            }
        }
    }

    // Post/Redirect/Get: evita reenviar el formulario al recargar
    $_SESSION["flash"] = $flash;
    header("Location: productividad.php");
    exit;
}

// ===== Mensaje pendiente =====
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);

// ===== Capturas de hoy =====
$stmt = $connection->prepare(
    "SELECT p.id_kronos, e.nombre, e.apellido_p, e.apellido_m,
            p.puntos_productividad, p.comparacion_periodo
     FROM productividad p
     INNER JOIN empleado e ON e.id_kronos = p.id_kronos
     WHERE p.fecha_registro = ?
     ORDER BY p.puntos_productividad DESC"
);
$stmt->bind_param("s", $hoy);
$stmt->execute();
$capturas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalCapturas = count($capturas);
$promedio = 0;
$maximo   = 0;
if ($totalCapturas > 0) {
    $puntosHoy = array_column($capturas, "puntos_productividad");
    $promedio  = round(array_sum($puntosHoy) / $totalCapturas, 1);
    $maximo    = max($puntosHoy);
}

// ===== Operativos sin captura hoy =====
$stmt = $connection->prepare(
    "SELECT COUNT(*) AS total FROM empleado e
     WHERE e.rol = 'operativo'
       AND NOT EXISTS (
         SELECT 1 FROM productividad p
         WHERE p.id_kronos = e.id_kronos AND p.fecha_registro = ?
       )"
);
$stmt->bind_param("s", $hoy);
$stmt->execute();
$pendientes = (int) $stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

// ===== Seguimiento de los últimos 7 días =====
$desde = date("Y-m-d", strtotime("-6 days"));
$stmt = $connection->prepare(
    "SELECT fecha_registro, COUNT(*) AS capturas,
            AVG(puntos_productividad) AS promedio,
            SUM(puntos_productividad) AS total
     FROM productividad
     WHERE fecha_registro BETWEEN ? AND ?
     GROUP BY fecha_registro
     ORDER BY fecha_registro DESC"
);
$stmt->bind_param("ss", $desde, $hoy);
$stmt->execute();
$semana = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$maxPromedio = 0;
foreach ($semana as $dia) {
    $maxPromedio = max($maxPromedio, (float) $dia["promedio"]);
}

$titulo = "Registro de Productividad";
include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Registro de Productividad</h1>
    <p>Captura y seguimiento diario · <?php echo date("d/m/Y"); ?></p>
  </div>

  <?php if ($flash): ?>
    <div class="mensaje <?php echo htmlspecialchars($flash[0]); ?>">
      <?php echo htmlspecialchars($flash[1]); ?>
    </div>
  <?php endif; ?>

  <section class="tarjetas">
    <div class="tarjeta ok">
      <span class="etiqueta">Capturados hoy</span>
      <span class="valor"><?php echo $totalCapturas; ?></span>
      <span class="detalle">Registros del día</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Pendientes</span>
      <span class="valor"><?php echo $pendientes; ?></span>
      <span class="detalle">Operativos sin captura</span>
    </div>
    <div class="tarjeta acento">
      <span class="etiqueta">Promedio de puntos</span>
      <span class="valor"><?php echo $promedio; ?></span>
      <span class="detalle">Del día</span>
    </div>
    <div class="tarjeta">
      <span class="etiqueta">Puntaje más alto</span>
      <span class="valor"><?php echo $maximo; ?></span>
      <span class="detalle">Del día</span>
    </div>
  </section>

  <section class="panel">
    <h2>Registrar productividad</h2>
    <p class="descripcion">Captura los puntos del día. Si el empleado ya tiene registro hoy, se corrige.</p>

    <form class="form-rapido" method="post" action="productividad.php" autocomplete="off">
      <div class="campo">
        <label for="kronos">No. Kronos</label>
        <input type="text" id="kronos" name="kronos" inputmode="numeric"
               placeholder="Número de Kronos" autofocus required>
      </div>
      <div class="campo">
        <label for="puntos">Puntos de productividad</label>
        <input type="number" id="puntos" name="puntos" min="0" step="1" placeholder="0" required>
      </div>
      <button type="submit" class="btn-primario" name="btn_guardar" value="1">Guardar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Capturas de hoy</h2>
    <p class="descripcion"><?php echo $totalCapturas; ?> registro(s) de productividad hoy.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Kronos</th>
            <th>Empleado</th>
            <th>Puntos</th>
            <th>Periodo</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($capturas)): ?>
            <tr><td colspan="4">Aún no hay capturas de productividad hoy.</td></tr>
          <?php else: ?>
            <?php foreach ($capturas as $c): ?>
              <tr>
                <td><?php echo htmlspecialchars($c["id_kronos"]); ?></td>
                <td><?php echo htmlspecialchars($c["nombre"] . " " . $c["apellido_p"] . " " . $c["apellido_m"]); ?></td>
                <td><strong><?php echo (int) $c["puntos_productividad"]; ?></strong></td>
                <td><span class="badge pendiente"><?php echo htmlspecialchars($c["comparacion_periodo"]); ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <h2>Seguimiento de los últimos 7 días</h2>
    <p class="descripcion">Promedio de puntos por día.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Capturas</th>
            <th>Total de puntos</th>
            <th>Promedio</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($semana)): ?>
            <tr><td colspan="4">Sin registros en los últimos 7 días.</td></tr>
          <?php else: ?>
            <?php foreach ($semana as $dia): ?>
              <?php
                $prom  = round((float) $dia["promedio"], 1);
                $ancho = $maxPromedio > 0 ? round(($dia["promedio"] / $maxPromedio) * 100) : 0;
              ?>
              <tr>
                <td><?php echo date("d/m/Y", strtotime($dia["fecha_registro"])); ?></td>
                <td><?php echo (int) $dia["capturas"]; ?></td>
                <td><?php echo (int) $dia["total"]; ?></td>
                <td>
                  <div class="celda-progreso">
                    <strong><?php echo $prom; ?></strong>
                    <div class="barra"><span style="width: <?php echo $ancho; ?>%"></span></div>
                  </div>
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