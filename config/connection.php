<?php

    $connection = new mysqli ("localhost", "root", "", "gestion_operativa_db");

    if ($connection->connect_error) {
    die("Error de conexión: " . $connection->connect_error);
}

    $connection->set_charset("utf8mb4");
    // echo "Conexión exitosa";
?>