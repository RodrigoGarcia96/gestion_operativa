<?php
// ===== Depuración: muestra los errores en pantalla. Bórralo cuando todo funcione =====
ini_set("display_errors", 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

include_once("../../includes/auth.php");
requireRole(["analista"]);
include_once("../../config/connection.php");

date_default_timezone_set("America/Mexico_City");
$hoy = date("Y-m-d");

// ===== Funciones auxiliares =====
function fechaValida($f) {
    $d = DateTime::createFromFormat("Y-m-d", $f);
    return $d && $d->format("Y-m-d") === $f;
}

// ===== Filtros (GET) =====
$desde = fechaValida($_GET["desde"] ?? "") ? $_GET["desde"] : date("Y-m-01");
$hasta = fechaValida($_GET["hasta"] ?? "") ? $_GET["hasta"] : $hoy;
if ($hasta > $hoy)   $hasta = $hoy;
if ($desde > $hasta) $desde = $hasta;

$filtroArea    = trim($_GET["area"] ?? "");
$filtroKronos  = trim($_GET["kronos"] ?? "");
if (!ctype_digit($filtroKronos)) $filtroKronos = "";

$filtroEstatus = $_GET["estatus"] ?? "todos";
if (!in_array($filtroEstatus, ["todos", "puntual", "retardo", "falta"], true)) {
    $filtroEstatus = "todos";
}

// ===== Lista de áreas para el filtro =====
$areas = $connection->query(
    "SELECT DISTINCT area FROM empleado
     WHERE rol = 'operativo' AND area IS NOT NULL AND area <> ''
     ORDER BY area"
)->fetch_all(MYSQLI_ASSOC);

// ===== Condiciones comunes a las dos consultas =====
$where  = "WHERE e.rol = 'operativo' AND a.fecha_asistencia BETWEEN ? AND ?";
$tipos  = "ss";
$params = [$desde, $hasta];

if ($filtroArea !== "") {
    $where   .= " AND e.area = ?";
    $tipos   .= "s";
    $params[] = $filtroArea;
}
if ($filtroKronos !== "") {
    $where   .= " AND a.id_kronos = ?";
    $tipos   .= "i";
    $params[] = (int) $filtroKronos;
}
if ($filtroEstatus === "puntual") {
    $where .= " AND a.es_falta = 0 AND a.es_retardo = 0";
} elseif ($filtroEstatus === "retardo") {
    $where .= " AND a.es_retardo = 1";
} elseif ($filtroEstatus === "falta") {
    $where .= " AND a.es_falta = 1";
}

// ===== Totales del periodo (sobre todo el conjunto filtrado) =====
$stmt = $connection->prepare(
    "SELECT COUNT(*) AS total,
            SUM(a.es_falta = 0 AND a.es_retardo = 0) AS puntuales,
            SUM(a.es_retardo = 1) AS retardos,
            SUM(a.es_falta = 1) AS faltas
     FROM asistencia a
     INNER JOIN empleado e ON e.id_kronos = a.id_kronos
     $where"
);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total     = (int) $t["total"];
$puntuales = (int) $t["puntuales"];
$retardos  = (int) $t["retardos"];
$faltas    = (int) $t["faltas"];
$asistenciaPct = $total > 0 ? round((($puntuales + $retardos) / $total) * 100) : 0;

// ===== Registros (máximo 500 para no saturar la página) =====
$limite = 500;
$stmt = $connection->prepare(
    "SELECT a.fecha_asistencia, a.id_kronos, e.nombre, e.apellido_p, e.apellido_m, e.area,
            a.hora_entrada, a.es_falta, a.es_retardo
     FROM asistencia a
     INNER JOIN empleado e ON e.id_kronos = a.id_kronos
     $where
     ORDER BY a.fecha_asistencia DESC, a.hora_entrada DESC
     LIMIT $limite"
);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$registros = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function estatus($esFalta, $esRetardo) {
    if ($esFalta)   return ["falta", "Falta"];
    if ($esRetardo) return ["retardo", "Retardo"];
    return ["puntual", "Puntual"];
}

$titulo = "Historial de Asistencia y Faltas";
include_once("../../includes/header.php");
include_once("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Historial de Asistencia y Faltas</h1>
    <p>Consulta histórica · <?php echo date("d/m/Y", strtotime($desde)); ?> al <?php echo date("d/m/Y", strtotime($hasta)); ?></p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta">
      <span class="etiqueta">Registros</span>
      <span class="valor"><?php echo $total; ?></span>
      <span class="detalle">Del periodo filtrado</span>
    </div>
    <div class="tarjeta ok">
      <span class="etiqueta">Asistencia</span>
      <span class="valor"><?php echo $asistenciaPct; ?>%</span>
      <span class="detalle"><?php echo $puntuales; ?> puntuales</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos</span>
      <span class="valor"><?php echo $retardos; ?></span>
      <span class="detalle">En el periodo</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Faltas</span>
      <span class="valor"><?php echo $faltas; ?></span>
      <span class="detalle">En el periodo</span>
    </div>
  </section>

  <section class="panel">
    <h2>Filtros</h2>
    <p class="descripcion">Combina periodo, área, empleado y estatus.</p>

    <form class="form-rapido" method="get" action="historial_asistencia.php">
      <div class="campo">
        <label for="desde">Desde</label>
        <input type="date" id="desde" name="desde" max="<?php echo $hoy; ?>"
               value="<?php echo htmlspecialchars($desde); ?>">
      </div>
      <div class="campo">
        <label for="hasta">Hasta</label>
        <input type="date" id="hasta" name="hasta" max="<?php echo $hoy; ?>"
               value="<?php echo htmlspecialchars($hasta); ?>">
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
      <div class="campo">
        <label for="kronos">No. Kronos</label>
        <input type="text" id="kronos" name="kronos" inputmode="numeric" placeholder="Todos"
               value="<?php echo htmlspecialchars($filtroKronos); ?>">
      </div>
      <div class="campo">
        <label for="estatus">Estatus</label>
        <select id="estatus" name="estatus">
          <option value="todos"   <?php echo $filtroEstatus === "todos"   ? "selected" : ""; ?>>Todos</option>
          <option value="puntual" <?php echo $filtroEstatus === "puntual" ? "selected" : ""; ?>>Puntual</option>
          <option value="retardo" <?php echo $filtroEstatus === "retardo" ? "selected" : ""; ?>>Retardo</option>
          <option value="falta"   <?php echo $filtroEstatus === "falta"   ? "selected" : ""; ?>>Falta</option>
        </select>
      </div>
      <button type="submit" class="btn-primario">Filtrar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Registros de asistencia</h2>
    <p class="descripcion">
      <?php if ($total > $limite): ?>
        Mostrando los <?php echo $limite; ?> más recientes de <?php echo $total; ?>. Acota el periodo para ver el resto.
      <?php else: ?>
        <?php echo $total; ?> registro(s) encontrados.
      <?php endif; ?>
    </p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>No. Kronos</th>
            <th>Empleado</th>
            <th>Área</th>
            <th>Hora de entrada</th>
            <th>Estatus</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($registros)): ?>
            <tr><td colspan="6">No hay registros con los filtros seleccionados.</td></tr>
          <?php else: ?>
            <?php foreach ($registros as $r): ?>
              <?php [$clase, $texto] = estatus($r["es_falta"], $r["es_retardo"]); ?>
              <tr>
                <td><?php echo date("d/m/Y", strtotime($r["fecha_asistencia"])); ?></td>
                <td><?php echo htmlspecialchars($r["id_kronos"]); ?></td>
                <td><?php echo htmlspecialchars($r["nombre"] . " " . $r["apellido_p"] . " " . $r["apellido_m"]); ?></td>
                <td><?php echo htmlspecialchars($r["area"]); ?></td>
                <td><?php echo $r["hora_entrada"] ? date("H:i", strtotime($r["hora_entrada"])) : "--:--"; ?></td>
                <td><span class="badge <?php echo $clase; ?>"><?php echo $texto; ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include_once("../../includes/footer.php"); ?>