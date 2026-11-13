<?php
include("../../includes/auth.php");
requireRole(["supervisor"]);
$titulo = "Operación del día";
include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<main class="contenido">
  <div class="encabezado">
    <h1>Operación del día</h1>
    <p>Pase de lista, retardos y estado de la plantilla en tiempo real.</p>
  </div>

  <section class="tarjetas">
    <div class="tarjeta ok">
      <span class="etiqueta">Presentes</span>
      <span class="valor">42</span>
      <span class="detalle">De 48 en plantilla</span>
    </div>
    <div class="tarjeta alerta">
      <span class="etiqueta">Retardos hoy</span>
      <span class="valor">4</span>
      <span class="detalle">Con tiempo por reponer</span>
    </div>
    <div class="tarjeta peligro">
      <span class="etiqueta">Faltas</span>
      <span class="valor">2</span>
      <span class="detalle">Sin justificar</span>
    </div>
    <div class="tarjeta acento">
      <span class="etiqueta">Pendientes</span>
      <span class="valor">0</span>
      <span class="detalle">Sin registrar entrada</span>
    </div>
  </section>

  <section class="panel">
    <h2>Entrada rápida</h2>
    <p class="descripcion">Captura o escanea el número de Kronos para registrar la entrada.</p>

    <form class="form-rapido" method="post" action="pase_lista.php">
      <div class="campo">
        <label for="kronos">No. Kronos</label>
        <input type="text" id="kronos" name="kronos" placeholder="Capturar o escanear">
      </div>
      <div class="campo">
        <label for="modo">Modo</label>
        <select id="modo" name="modo">
          <option value="regular">Turno regular</option>
          <option value="emergencia">Emergencia</option>
        </select>
      </div>
      <button type="submit" class="btn-primario">Registrar</button>
    </form>
  </section>

  <section class="panel">
    <h2>Pase de lista de hoy</h2>
    <p class="descripcion">Últimas entradas registradas.</p>

    <div class="tabla-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Kronos</th>
            <th>Nombre del empleado</th>
            <th>Hora de entrada</th>
            <th>Estatus</th>
          </tr>
        </thead>
        <tbody>
          <!-- Datos de ejemplo: después se llenan desde asistencia JOIN empleado -->
          <tr>
            <td>1001</td>
            <td>Ana Lopez Ruiz</td>
            <td>07:58</td>
            <td><span class="badge puntual">Puntual</span></td>
          </tr>
          <tr>
            <td>1005</td>
            <td>Mario Salas Vega</td>
            <td>08:12</td>
            <td><span class="badge retardo">Retardo</span></td>
          </tr>
          <tr>
            <td>1006</td>
            <td>Sofia Ramos Cruz</td>
            <td>--:--</td>
            <td><span class="badge falta">Falta</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</main>

<?php include("../../includes/footer.php"); ?>