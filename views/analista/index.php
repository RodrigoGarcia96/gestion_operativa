<?php
include("../../includes/auth.php");
requireRole(["analista"]);
$titulo = "Resumen analítico";
include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Resumen analítico</h1>
    <p>Comparativo de productividad, asistencia y retardos por periodo.</p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta acento">
      <span class="etiqueta">Productividad promedio</span>
      <span class="valor">87%</span>
      <span class="detalle">Periodo seleccionado</span>
    </div>
    <div class="tarjeta ok">
      <span class="etiqueta">Asistencia</span>
      <span class="valor">94%</span>
      <span class="detalle">Del personal del área</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos</span>
      <span class="valor">23</span>
      <span class="detalle">En el periodo</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Tiempo por reponer</span>
      <span class="valor">14 h</span>
      <span class="detalle">Acumulado del área</span>
    </div>
  </section>

  <section class="panel">
    <h2>Análisis comparativo de productividad</h2>
    <p class="descripcion">Consulta filtrada por periodicidad y área.</p>

    <div class="filtros">
      <div class="chips">
        <button type="button" class="chip activo">Diario</button>
        <button type="button" class="chip">Mensual</button>
        <button type="button" class="chip">Trimestral</button>
      </div>
      <select aria-label="Área">
        <option>Todas las áreas</option>
        <option>Operaciones</option>
      </select>
    </div>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Kronos</th>
            <th>Personal / Puesto</th>
            <th>Resultado del periodo</th>
            <th>Tiempo de retardo acum.</th>
          </tr>
        </thead>
        <tbody>
          <!-- Datos de ejemplo: después se llenan desde empleado, productividad y asistencia -->
          <tr>
            <td>1001</td>
            <td>Ana Lopez · Operador</td>
            <td>
              <div class="celda-progreso">
                <strong>92%</strong>
                <div class="barra"><span style="width: 92%"></span></div>
              </div>
            </td>
            <td>0 min</td>
          </tr>
          <tr>
            <td>1005</td>
            <td>Mario Salas · Operador</td>
            <td>
              <div class="celda-progreso">
                <strong>81%</strong>
                <div class="barra"><span style="width: 81%"></span></div>
              </div>
            </td>
            <td>45 min</td>
          </tr>
          <tr>
            <td>1006</td>
            <td>Sofia Ramos · Operador</td>
            <td>
              <div class="celda-progreso">
                <strong>76%</strong>
                <div class="barra"><span style="width: 76%"></span></div>
              </div>
            </td>
            <td>120 min</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include("../../includes/footer.php"); ?>