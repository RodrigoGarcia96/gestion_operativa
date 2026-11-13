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
  <title>Iniciar Sesión</title>
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

  <div class="login-box">
    <h2>Iniciar Sesión</h2>

    <p id="mensaje-error" class="error" <?php if (empty($error)) echo 'hidden'; ?>>
      <?php echo !empty($error) ? htmlspecialchars($error) : ''; ?>
    </p>

    <form id="form-login" method="post" action="">
      <div class="form-group">
        <label for="kronos">Número de Kronos</label>
        <input type="text" id="kronos" name="kronos">
      </div>

      <div class="form-group">
        <label for="contra">Contraseña</label>
        <input type="password" id="contra" name="contra">
      </div>

      <input type="submit" class="btn" name="btn_entrar" value="Entrar">
    </form>
  </div>

  <script src="../assets/js/login.js"></script>
</body>
</html>