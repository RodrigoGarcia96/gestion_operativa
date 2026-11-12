<?php
include_once __DIR__ . "/../config/roles.php";

$config = $MENUS[$_SESSION["rol"]];
$paginaActual = basename($_SERVER["SCRIPT_NAME"]);
?>
<aside class="sidebar">
  <h3><?php echo htmlspecialchars($config["titulo"]); ?></h3>

  <ul>
    <?php foreach ($config["items"] as [$texto, $enlace]): ?>
      <li>
        <a href="<?php echo $enlace; ?>"
           class="<?php echo ($paginaActual === $enlace) ? 'activo' : ''; ?>">
          ▸ <?php echo htmlspecialchars($texto); ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <a class="cerrar-sesion" href="../../controllers/logout.php">Cerrar Sesión</a>
</aside>