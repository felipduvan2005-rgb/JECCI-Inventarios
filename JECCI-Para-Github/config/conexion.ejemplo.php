<?php

$servidor = "localhost";
$usuario = "TU_USUARIO_MYSQL";
$contrasena = "TU_CONTRASENA_MYSQL";

$base_datos = "sistema_inventario_jecci";
$puerto = 3306;

$conexion = new mysqli(
    $servidor,
    $usuario,
    $contrasena,
    $base_datos,
    $puerto
);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");

?>