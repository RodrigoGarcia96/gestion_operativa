<?php
include("../../includes/auth.php");
requireRole(["supervisor"]);
include("../../config/connection.php");

date_default_timezone_set("America/Mexico_City");

// ===== Reglas de puntualidad (ajústalas a tu turno) =====
$HORA_TURNO     = "08:00:00";  // hora oficial de entrada
$TOLERANCIA_MIN = 0;           // minutos de tolerancia antes de contar retardo

$hoy = date("Y-m-d");

// ===== Registrar entrada =====
if (!empty($_POST["btn_registrar"])) {
    $kronos = trim($_POST["kronos"] ?? "");
    $flash  = null;

    if ($kronos === "") {
        $flash = ["error", "Captura el número de Kronos."];
    } elseif (!ctype_digit($kronos)) {
        $flash = ["error", "El número de Kronos solo debe contener dígitos."];
    } else {
        // 1. Verificar que el empleado exista
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
            $nombreCompleto = $empleado["nombre"] . " " . $empleado["apellido_p"];

            // 2. Verificar que no tenga entrada registrada hoy
            $stmt = $connection->prepare(
                "SELECT id_asistencia FROM asistencia
                 WHERE id_kronos = ? AND fecha_asistencia = ?"
            );
            $stmt->bind_param("is", $kronos, $hoy);
            $stmt->execute();
            $yaRegistrado = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($yaRegistrado) {
                $flash = ["aviso", "$nombreCompleto ya tiene su entrada registrada hoy."];
            } else {
                // 3. Calcular si llegó con retardo
                $ahora      = time();
                $limite     = strtotime("$hoy $HORA_TURNO") + ($TOLERANCIA_MIN * 60);
                $esRetardo  = ($ahora > $limite) ? 1 : 0;
                $horaEntrada = date("H:i:s", $ahora);

                // 4. Guardar el registro
                $stmt = $connection->prepare(
                    "INSERT INTO asistencia (id_kronos, fecha_asistencia, hora_entrada, es_falta, es_retardo)
                     VALUES (?, ?, ?, 0, ?)"
                );
                $stmt->bind_param("issi", $kronos, $hoy, $horaEntrada, $esRetardo);
                $stmt->execute();
                $stmt->close();

                $estado = $esRetardo ? "con retardo" : "puntual";
                $flash  = ["ok", "Entrada registrada: $nombreCompleto ($estado) a las " . date("H:i", $ahora) . "."];
            }
        }
    }

    // Post/Redirect/Get: evita que al recargar se reenvíe el formulario
    $_SESSION["flash"] = $flash;
    header("Location: pase_lista.php");
    exit;
}

// ===== Mensaje pendiente =====
$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);

// ===== Registros del día =====
$stmt = $connection->prepare(
    "SELECT a.id_asistencia, a.id_kronos, e.nombre, e.apellido_p, e.apellido_m,
            a.fecha_asistencia, a.hora_entrada, a.es_falta, a.es_retardo
     FROM asistencia a
     INNER JOIN empleado e ON e.id_kronos = a.id_kronos
     WHERE a.fecha_asistencia = ?
     ORDER BY a.hora_entrada DESC"
);
$stmt->bind_param("s", $hoy);
$stmt->execute();
$registros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Devuelve [clase CSS, texto] según los campos es_falta y es_retardo
function estatus($esFalta, $esRetardo) {
    if ($esFalta)   return ["falta", "Falta"];
    if ($esRetardo) return ["retardo", "Retardo"];
    return ["puntual", "Puntual"];
}

$titulo = "Pase de Lista";
include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Pase de Lista</h1>
    <p>Registro de entrada por número de Kronos · <?php echo date("d/m/Y"); ?></p>
  </div>

  <?php if ($flash): ?>
    <div class="mensaje <?php echo htmlspecialchars($flash[0]); ?>">
      <?php echo htmlspecialchars($flash[1]); ?>
    </div>
  <?php endif; ?>

  <section class="panel">
    <h2>Registrar entrada</h2>
    <p class="descripcion">Captura o escanea el número de Kronos y presiona Enter.</p>

    <form class="form-rapido" method="post" action="pase_lista.php" autocomplete="off">
      <div class="campo">
        <label for="kronos">No. Kronos</label>
        <input type="text" id="kronos" name="kronos" inputmode="numeric"
               placeholder="Capturar o escanear" autofocus required>
      </div>
      <button type="submit" class="btn-primario" name="btn_registrar" value="1">Registrar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Registros del día</h2>
    <p class="descripcion"><?php echo count($registros); ?> registro(s) de asistencia hoy.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Kronos</th>
            <th>Empleado</th>
            <th>Fecha</th>
            <th>Hora de entrada</th>
            <th>Estatus</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($registros)): ?>
            <tr>
              <td colspan="5">Aún no hay entradas registradas hoy.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($registros as $r): ?>
              <?php [$clase, $texto] = estatus($r["es_falta"], $r["es_retardo"]); ?>
              <tr>
                <td><?php echo htmlspecialchars($r["id_kronos"]); ?></td>
                <td><?php echo htmlspecialchars($r["nombre"] . " " . $r["apellido_p"] . " " . $r["apellido_m"]); ?></td>
                <td><?php echo date("d/m/Y", strtotime($r["fecha_asistencia"])); ?></td>
                <td><?php echo date("H:i", strtotime($r["hora_entrada"])); ?></td>
                <td><span class="badge <?php echo $clase; ?>"><?php echo $texto; ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include("../../includes/footer.php"); ?>