<?php
$MENUS = [
    "operativo" => [
        "etiqueta"  => "OPERATIVO",
        "subtitulo" => "Portal del Colaborador",
        "titulo"    => "MENÚ PRINCIPAL (OPERATIVO)",
        "items"     => [
            ["Mi Asistencia y Entradas",    "mi_asistencia.php"],
            ["Mis Retardos y Tiempo Acum.", "mis_retardos.php"],
            ["Mi Productividad",            "mi_productividad.php"],
        ],
    ],
    "supervisor" => [
        "etiqueta"  => "SUPERVISOR",
        "subtitulo" => "Gestión Operativa",
        "titulo"    => "MENÚ PRINCIPAL (SUPERVISOR)",
        "items"     => [
            ["Pase de Lista (Kronos)",       "pase_lista.php"],
            ["Control de Faltas y Retardos", "faltas_retardos.php"],
            ["Registro de Productividad",    "productividad.php"],
            ["Reportes Operativos del Día",  "reportes.php"],
        ],
    ],
    "analista" => [
        "etiqueta"  => "ANALISTA",
        "subtitulo" => "Evaluación y Métricas",
        "titulo"    => "MENÚ PRINCIPAL (ANALISTA)",
        "items"     => [
            ["Análisis de Productividad",        "productividad.php"],
            ["Historial de Asistencia y Faltas", "historial_asistencia.php"],
            ["Acumulado de Retardos",            "retardos.php"],
            ["Generador de Reportes",            "reportes.php"],
        ],
    ],
    "jefe_area" => [
        "etiqueta"  => "JEFE DE ÁREA",
        "subtitulo" => "Supervisión Ejecutiva",
        "titulo"    => "MENÚ PRINCIPAL (JEFE DE ÁREA)",
        "items"     => [
            ["Tablero General del Área",      "index.php"],
            ["Indicadores de Productividad",  "productividad.php"],
            ["Resumen Asistencia y Retardos", "resumen_asistencia.php"],
            ["Reportes Ejecutivos",           "reportes.php"],
        ],
    ],
];