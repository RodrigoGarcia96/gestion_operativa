<?php
include("../../includes/auth.php");
requireRole(["jefe_area"]);
$titulo = "Tablero general del área";
include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Tablero general del área</h1>
    <p>Vista consolidada de puntualidad, cobertura de plantilla y cumplimiento de metas.</p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta ok">
      <span class="etiqueta">Asistencia hoy</span>
      <span class="valor">94%</span>
      <span class="detalle">Cobertura de plantilla</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos hoy</span>
      <span class="valor">4</span>
      <span class="detalle">Personal con retardo</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Tiempo por reponer</span>
      <span class="valor">14 h</span>
      <span class="detalle">Acumulado mensual</span>
    </div>
    <div class="tarjeta acento">
      <span class="etiqueta">Productividad</span>
      <span class="valor">87%</span>
      <span class="detalle">Índice del área</span>
    </div>
  </section>

  <section class="panel">
    <h2>Comportamiento general de la operación</h2>
    <p class="descripcion">Indicadores clave por corte diario, mensual y trimestral.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>Indicador operativo</th>
            <th>Corte diario</th>
            <th>Acumulado mensual</th>
            <th>Acumulado trimestral</th>
          </tr>
        </thead>
        <tbody>
          <!-- Datos de ejemplo: después se calculan desde asistencia y productividad -->
          <tr>
            <td>Asistencia general</td>
            <td>
              <div class="celda-progreso">
                <strong>94%</strong>
                <div class="barra"><span style="width: 94%"></span></div>
              </div>
            </td>
            <td>
              <div class="celda-progreso">
                <strong>92%</strong>
                <div class="barra"><span style="width: 92%"></span></div>
              </div>
            </td>
            <td>
              <div class="celda-progreso">
                <strong>93%</strong>
                <div class="barra"><span style="width: 93%"></span></div>
              </div>
            </td>
          </tr>
          <tr>
            <td>Retardos y tiempo por reponer</td>
            <td>2 h 10 min</td>
            <td>14 h</td>
            <td>41 h</td>
          </tr>
          <tr>
            <td>Productividad del área</td>
            <td>
              <div class="celda-progreso">
                <strong>89%</strong>
                <div class="barra"><span style="width: 89%"></span></div>
              </div>
            </td>
            <td>
              <div class="celda-progreso">
                <strong>87%</strong>
                <div class="barra"><span style="width: 87%"></span></div>
              </div>
            </td>
            <td>
              <div class="celda-progreso">
                <strong>85%</strong>
                <div class="barra"><span style="width: 85%"></span></div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include("../../includes/footer.php"); ?>