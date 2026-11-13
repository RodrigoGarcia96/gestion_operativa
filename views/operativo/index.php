<?php
include("../../includes/auth.php");
requireRole(["operativo"]);
$titulo = "Mi resumen";
include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Hola, <?php echo htmlspecialchars($_SESSION["nombre"]); ?></h1>
    <p>Mi resumen personal de asistencia, puntualidad y productividad.</p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta ok">
      <span class="etiqueta">Entradas del mes</span>
      <span class="valor">18</span>
      <span class="detalle">Registros de asistencia</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos</span>
      <span class="valor">2</span>
      <span class="detalle">En el periodo actual</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Tiempo por reponer</span>
      <span class="valor">35 min</span>
      <span class="detalle">Acumulado pendiente</span>
    </div>
    <div class="tarjeta acento">
      <span class="etiqueta">Mi productividad</span>
      <span class="valor">92%</span>
      <span class="detalle">Resultado del periodo</span>
    </div>
  </section>

  <section class="panel">
    <h2>Mis últimos registros</h2>
    <p class="descripcion">Entradas, estatus y tiempo acumulado por retardos.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora de entrada</th>
            <th>Estatus</th>
            <th>Tiempo por reponer</th>
          </tr>
        </thead>
        <tbody>
          <!-- Datos de ejemplo: después se llenan desde la tabla asistencia, filtrando por $_SESSION["id_kronos"] -->
          <tr>
            <td>12/11/2026</td>
            <td>07:58</td>
            <td><span class="badge puntual">Puntual</span></td>
            <td>0 min</td>
          </tr>
          <tr>
            <td>11/11/2026</td>
            <td>08:15</td>
            <td><span class="badge retardo">Retardo</span></td>
            <td>15 min</td>
          </tr>
          <tr>
            <td>10/11/2026</td>
            <td>07:55</td>
            <td><span class="badge puntual">Puntual</span></td>
            <td>0 min</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include("../../includes/footer.php"); ?>