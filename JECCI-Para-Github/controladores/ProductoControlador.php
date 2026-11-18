<?php

require_once __DIR__ . "/../config/conexion.php";
require_once __DIR__ . "/../modelos/ProductoModelo.php";

$db = $conexion ?? $conn ?? null;

if (!$db instanceof mysqli) {
    die("Error: no se encontró la conexión a MySQL.");
}

$db->set_charset("utf8mb4");

$modelo = new ProductoModelo($db);

$mensaje = "";
$error = "";
$editar = null;
$productos = [];
$buscar = trim($_GET["buscar"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";
    $id = (int)($_POST["id"] ?? 0);

    if ($accion === "guardar") {

        $codigo = trim($_POST["codigo"] ?? "");
        $nombre = trim($_POST["nombre"] ?? "");
        $categoria = trim($_POST["categoria"] ?? "");
        $precioTexto = trim($_POST["precio"] ?? "");

        if (
            $codigo === "" ||
            $nombre === "" ||
            $categoria === "" ||
            $precioTexto === ""
        ) {
            $error = "Completa todos los campos.";
        } elseif (
            !is_numeric($precioTexto) ||
            (float)$precioTexto < 0
        ) {
            $error = "El precio debe ser válido.";
        } else {

            try {
                $modelo->guardar(
                    $id,
                    $codigo,
                    $nombre,
                    $categoria,
                    (float)$precioTexto
                );

                header("Location: productos.php?mensaje=guardado");
                exit;

            } catch (mysqli_sql_exception $e) {
                $error = "No se pudo guardar el producto. Comprueba que el código no esté repetido.";
            }
        }
    }

    if ($accion === "eliminar" && $id > 0) {

        try {
            if ($modelo->eliminar($id)) {
                header("Location: productos.php?mensaje=eliminado");
                exit;
            }

            $error = "No puedes eliminar este producto porque tiene registros de inventario.";

        } catch (mysqli_sql_exception $e) {
            $error = "No se puede eliminar este producto porque tiene movimientos relacionados.";
        }
    }
}

if (isset($_GET["mensaje"])) {
    if ($_GET["mensaje"] === "guardado") {
        $mensaje = "Producto guardado correctamente.";
    }

    if ($_GET["mensaje"] === "eliminado") {
        $mensaje = "Producto eliminado correctamente.";
    }
}

if (isset($_GET["editar"])) {
    $idEditar = (int)$_GET["editar"];

    if ($idEditar > 0) {
        try {
            $editar = $modelo->obtenerPorId($idEditar);
        } catch (mysqli_sql_exception $e) {
            $error = "No se pudo consultar el producto.";
        }
    }
}

try {
    $productos = $modelo->listar($buscar);
} catch (mysqli_sql_exception $e) {
    $error = "Error al consultar productos e inventario.";
}

$totalProductos = count($productos);
$totalUnidades = 0;
$stockBajo = 0;

foreach ($productos as $producto) {
    $cantidad = (int)$producto["stock_real"];

    $totalUnidades += $cantidad;

    if ($cantidad <= 10) {
        $stockBajo++;
    }
}