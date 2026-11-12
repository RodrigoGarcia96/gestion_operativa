<?php

$error = "";

if (!empty($_POST["btn_entrar"])) {
    $kronos = trim($_POST["kronos"] ?? "");
    $contra = $_POST["contra"] ?? "";

    if ($kronos === "" || $contra === "") {
        $error = "Los campos están vacíos";
    } else {
        $stmt = $connection->prepare(
            "SELECT id_kronos, nombre, rol
             FROM empleado
             WHERE id_kronos = ? AND contra = ?"
        );
        $stmt->bind_param("ss", $kronos, $contra);
        $stmt->execute();
        $empleado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($empleado) {
            $_SESSION["id_kronos"] = $empleado["id_kronos"];
            $_SESSION["nombre"]    = $empleado["nombre"];
            $_SESSION["rol"]       = $empleado["rol"];

            header("Location: inicio.php");
            exit;
        } else {
            $error = "Número de Kronos o contraseña incorrectos";
        }
    }
}