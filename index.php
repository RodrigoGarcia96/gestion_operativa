<?php
session_start();
include("config/connection.php");
include("controllers/controller.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión | SIGEO</title>
  <link rel="stylesheet" href="assets/css/login.css?v=<?php echo filemtime(__DIR__ . '/assets/css/login.css'); ?>">
</head>
<body>

  <div class="login-layout">

    <!-- Panel de marca -->
    <aside class="login-brand">
      <div class="marca">
        <span class="marca-logo">S</span>
        <span class="marca-nombre">SIGEO</span>
      </div>

      <h1>Gestión y evaluación operativa en un solo lugar</h1>
      <p class="lema">
        Sistema Integral de Gestión y Evaluación Operativa. Controla la asistencia,
        la puntualidad y el desempeño de tu equipo.
      </p>

      <ul class="funciones">
        <li>Pase de lista por número de Kronos</li>
        <li>Control de faltas y retardos</li>
        <li>Productividad operativa</li>
        <li>Reportes e indicadores</li>
      </ul>
    </aside>

    <!-- Formulario -->
    <main class="login-panel">
      <div class="login-box">
        <h2>Bienvenido</h2>
        <p class="subtitulo">Ingresa con tu número de Kronos para continuar.</p>

        <p id="mensaje-error" class="error" role="alert" <?php if (empty($error)) echo 'hidden'; ?>>
          <?php echo !empty($error) ? htmlspecialchars($error) : ''; ?>
        </p>

        <form id="form-login" method="post" action="" autocomplete="off">
          <div class="form-group">
            <label for="kronos">Número de Kronos</label>
            <input type="text" id="kronos" name="kronos" inputmode="numeric"
                   placeholder="Ej. 1001" autofocus>
          </div>

          <div class="form-group">
            <label for="contra">Contraseña</label>
            <div class="campo-clave">
              <input type="password" id="contra" name="contra" placeholder="Tu contraseña">
              <button type="button" id="toggle-clave" class="toggle-clave"
                      aria-label="Mostrar contraseña">Mostrar</button>
            </div>
          </div>

          <input type="submit" class="btn" name="btn_entrar" value="Entrar">
        </form>

        <p class="pie">SIGEO · Acceso para personal autorizado</p>
      </div>
    </main>

  </div>
  <script src="assets/js/login.js?v=<?php echo filemtime(__DIR__ . '/assets/js/login.js'); ?>"></script>
</body>
</html>