<?php
session_start();

// 1. Si no hay sesión, regresa al login
if (!isset($_SESSION["id_kronos"])) {
    header("Location: login.php");
    exit;
}

// 2. Panel de destino según el rol
$destinos = [
    "operativo" => "operativo/index.php",
    "supervisor" => "supervisor/index.php",
    "analista"   => "analista/index.php",
    "jefe_area"  => "jefe_area/index.php",
];

$rol = $_SESSION["rol"] ?? "";

// 3. Si el rol es válido, redirige a su panel
if (isset($destinos[$rol])) {
    header("Location: " . $destinos[$rol]);
    exit;
}

// 4. Si el rol no existe o está vacío, cierra la sesión y regresa al login
session_destroy();
header("Location: login.php");
exit;