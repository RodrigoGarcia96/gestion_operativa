<?php
include_once __DIR__ . "/../config/roles.php";

$titulo = $titulo ?? "SIGEO";
$config = $MENUS[$_SESSION["rol"]];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($titulo); ?> | SIGEO</title>
  <link rel="stylesheet" href="../../assets/css/layout.css">
  <link rel="stylesheet" href="../../assets/css/tablas.css">
</head>
<body>

<header class="topbar">
  <span class="topbar-marca">SIGEO | <?php echo htmlspecialchars($config["subtitulo"]); ?></span>
  <span class="topbar-usuario">
    ROL: <?php echo htmlspecialchars($config["etiqueta"]); ?>
    · No. Kronos: <?php echo htmlspecialchars($_SESSION["id_kronos"]); ?>
    · <?php echo htmlspecialchars($_SESSION["nombre"]); ?>
  </span>
</header>

<div class="app">