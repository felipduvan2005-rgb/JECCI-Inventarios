<?php
require_once __DIR__ . "/config/conexion.php";

$db = $conexion ?? $conn ?? null;

if (!($db instanceof mysqli)) {
    die("Error: no se encontró la conexión con MySQL.");
}

$db->set_charset("utf8mb4");

function escapar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

// LÍMITE PARA ALERTAS DE STOCK BAJO
$stockMinimo = 10;
$productosBajos = [];
$error = "";

try {

    // CONSULTAR STOCK REAL DESDE LAS BODEGAS
    $sql = "
        SELECT
            p.id,
            p.codigo,
            p.nombre,
            p.categoria,
            COALESCE(SUM(ib.cantidad), 0) AS stock_actual
        FROM productos p
        LEFT JOIN inventario_bodegas ib
            ON ib.producto_id = p.id
        GROUP BY
            p.id,
            p.codigo,
            p.nombre,
            p.categoria
        HAVING stock_actual <= ?
        ORDER BY stock_actual ASC, p.nombre ASC
    ";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        throw new Exception($db->error);
    }

    $stmt->bind_param("i", $stockMinimo);
    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($producto = $resultado->fetch_assoc()) {
        $productosBajos[] = $producto;
    }

    $stmt->close();

} catch (Throwable $e) {
    $error = "No se pudieron cargar las alertas: "
           . $e->getMessage();
}

$totalAlertas = count($productosBajos);
?>

<!DOCTYPE html>
<html lang="es">

<head>
<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Alertas | JECCI Inventarios</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f1f4f8;
    color: #263548;
}

/* MENÚ LATERAL */
.menu {
    width: 250px;
    height: 100vh;
    background: #091d33;
    position: fixed;
    left: 0;
    top: 0;
    padding: 25px 17px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

.logo {
    text-align: center;
    color: white;
    padding: 10px 0 30px;
    border-bottom: 1px solid #294158;
    margin-bottom: 25px;
}

.logo h1 {
    margin: 0;
    font-size: 30px;
    letter-spacing: 3px;
}

.logo p {
    font-size: 10px;
    color: #a9bdce;
    margin-top: 6px;
}

.menu-titulo {
    color: #8095a9;
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 2px;
    padding: 0 12px 12px;
}

.menu a {
    display: block;
    color: #c4d1de;
    text-decoration: none;
    padding: 13px;
    margin-bottom: 6px;
    border-radius: 9px;
    font-size: 14px;
}

.menu a:hover,
.menu a.activo {
    background: #174d75;
    color: white;
}

.menu a.activo {
    border-left: 4px solid #ed343d;
}

.cerrar {
    margin-top: auto;
    border-top: 1px solid #294158;
    padding-top: 15px;
}

.cerrar a {
    color: #ff9298;
}

/* CONTENIDO */
.contenido {
    margin-left: 250px;
    padding: 30px;
}

.encabezado {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
}

h2 {
    color: #102b46;
    margin: 0;
    font-size: 27px;
}

.subtitulo {
    color: #8996a5;
    font-size: 13px;
    margin-top: 7px;
}

.usuario {
    background: white;
    padding: 12px 17px;
    border-radius: 10px;
    font-size: 13px;
}

/* INDICADORES */
.indicadores {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.indicador {
    background: white;
    padding: 22px;
    border-radius: 15px;
    border: 1px solid #e9eef3;
    box-shadow: 0 4px 15px #102b460b;
}

.indicador h3 {
    margin: 0;
    color: #8290a0;
    font-size: 13px;
    font-weight: 500;
}

.numero {
    font-size: 30px;
    font-weight: bold;
    color: #142f4a;
    margin-top: 14px;
}

.detalle {
    color: #98a3af;
    font-size: 12px;
    margin-top: 5px;
}

/* TARJETAS */
.tarjeta {
    background: white;
    padding: 22px;
    border-radius: 15px;
    margin-bottom: 22px;
    border: 1px solid #e9eef3;
    box-shadow: 0 4px 15px #102b460b;
}

.tarjeta h3 {
    color: #17334e;
    margin-top: 0;
}

.descripcion {
    color: #8996a5;
    font-size: 13px;
    margin-bottom: 22px;
}

/* ALERTAS */
.alerta {
    border-left: 5px solid #e74c3c;
    background: #fff5f5;
    padding: 20px;
    margin-bottom: 15px;
    border-radius: 9px;
}

.alerta h3 {
    margin-top: 0;
    color: #c0392b;
}

.alerta p {
    font-size: 13px;
    margin: 9px 0;
}

.cantidad {
    font-weight: bold;
    color: #c0392b;
}

.boton {
    display: inline-block;
    background: #1769aa;
    color: white;
    text-decoration: none;
    padding: 11px 18px;
    border-radius: 8px;
    margin-top: 12px;
    font-size: 13px;
}

.boton:hover {
    background: #0d4f82;
}

/* MENSAJES */
.sin-alertas {
    background: #e2f7ed;
    color: #137b4b;
    padding: 20px;
    border-radius: 10px;
    font-weight: bold;
}

.error {
    background: #ffe6e7;
    color: #b91c1c;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

/* TABLA */
.tabla-contenedor {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 14px 12px;
    border-bottom: 1px solid #edf0f4;
    text-align: left;
    font-size: 13px;
}

th {
    background: #f7f9fb;
    color: #788798;
}

.critico {
    color: #c0392b;
    font-weight: bold;
}

/* RESPONSIVE */
@media (max-width: 700px) {
    .menu {
        position: relative;
        width: 100%;
        height: auto;
    }

    .contenido {
        margin-left: 0;
        padding: 18px;
    }

    .encabezado {
        flex-wrap: wrap;
    }

    .indicadores {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<!-- MENÚ LATERAL -->
<aside class="menu">

    <div class="logo">
        <h1>⚙ JECCI</h1>
        <p>SOLUCIONES INDUSTRIALES</p>
    </div>

    <div class="menu-titulo">
        MENÚ PRINCIPAL
    </div>

    <a href="dashboard.php">🏠 Inicio</a>
    <a href="productos.php">📦 Productos</a>
    <a href="bodegas.php">🏬 Bodegas</a>
    <a href="entradas.php">➕ Entradas</a>
    <a href="salidas.php">➖ Salidas</a>
    <a href="proveedores.php">🚚 Proveedores</a>
    <a href="reportes.php">📊 Reportes</a>
    <a href="alertas.php" class="activo">⚠️ Alertas</a>
    <a href="usuarios.php">👥 Usuarios</a>

    <div class="cerrar">
        <a href="index.php">↩ Cerrar sesión</a>
    </div>

</aside>

<!-- CONTENIDO -->
<main class="contenido">

    <header class="encabezado">
        <div>
            <h2>Alertas de inventario</h2>
            <p class="subtitulo">
                Monitoreo de existencias reales
                en las bodegas de JECCI.
            </p>
        </div>

        <div class="usuario">
            👤 Administrador
        </div>
    </header>

    <?php if ($error !== ""): ?>
        <div class="error">
            <?= escapar($error) ?>
        </div>
    <?php endif; ?>

    <!-- INDICADORES -->
    <section class="indicadores">

        <div class="indicador">
            <h3>Productos con stock bajo</h3>

            <div class="numero">
                <?= $error === "" ? $totalAlertas : "—" ?>
            </div>

            <p class="detalle">
                Productos que requieren atención
            </p>
        </div>

        <div class="indicador">
            <h3>Límite de stock bajo</h3>

            <div class="numero">
                <?= $stockMinimo ?>
            </div>

            <p class="detalle">
                Unidades o menos por producto
            </p>
        </div>

    </section>

    <!-- PRODUCTOS CON STOCK BAJO -->
    <section class="tarjeta">

        <h3>⚠️ Productos con stock bajo</h3>

        <p class="descripcion">
            Estos productos necesitan ser revisados
            y posiblemente requieren una nueva compra.
        </p>

        <?php if ($error === "" && $totalAlertas === 0): ?>

            <div class="sin-alertas">
                ✅ No hay productos con stock bajo.
            </div>

        <?php endif; ?>

        <?php foreach ($productosBajos as $producto): ?>

            <div class="alerta">

                <h3>
                    🔴 <?= escapar($producto["nombre"]) ?>
                </h3>

                <p>
                    Código:
                    <?= escapar($producto["codigo"]) ?>
                </p>

                <p>
                    Categoría:
                    <?= escapar($producto["categoria"]) ?>
                </p>

                <p>
                    Stock actual:
                    <span class="cantidad">
                        <?= (int)$producto["stock_actual"] ?>
                        unidades
                    </span>
                </p>

                <p>
                    Límite de stock bajo:
                    <?= $stockMinimo ?> unidades
                </p>

                <a href="entradas.php" class="boton">
                    ➕ Registrar entrada
                </a>

            </div>

        <?php endforeach; ?>

    </section>

    <!-- RESUMEN DE ALERTAS -->
    <section class="tarjeta">

        <h3>📋 Resumen de alertas</h3>

        <div class="tabla-contenedor">

            <table>

                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Stock actual</th>
                        <th>Límite</th>
                        <th>Estado</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($productosBajos as $producto): ?>

                        <tr>
                            <td>
                                <?= escapar($producto["nombre"]) ?>
                            </td>

                            <td>
                                <?= (int)$producto["stock_actual"] ?>
                            </td>

                            <td>
                                <?= $stockMinimo ?>
                            </td>

                            <td class="critico">
                                <?= (int)$producto["stock_actual"] === 0
                                    ? "Sin existencias"
                                    : "Stock bajo" ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                    <?php if (empty($productosBajos)): ?>

                        <tr>
                            <td colspan="4">
                                <?= $error !== ""
                                    ? "No se pudieron consultar las alertas."
                                    : "No hay alertas de inventario." ?>
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>