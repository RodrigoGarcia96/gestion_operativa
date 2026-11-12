<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Exige que haya iniciado sesión
function requireLogin() {
    if (!isset($_SESSION["id_kronos"])) {
        header("Location: ../login.php");
        exit;
    }
}

// Exige sesión y que el rol esté en la lista permitida
function requireRole(array $roles) {
    requireLogin();
    if (!in_array($_SESSION["rol"] ?? "", $roles, true)) {
        http_response_code(403);
        exit("No tienes permiso para ver esta página");
    }
}