<?php
require_once __DIR__ . "/config/conexion.php";

$db = $conexion ?? $conn ?? null;

if (!($db instanceof mysqli)) {
    die("Error: no se encontró la conexión a MySQL.");
}

$db->set_charset("utf8mb4");
date_default_timezone_set("America/Bogota");

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function existeTabla($db, $tabla) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?"
    );
    $stmt->bind_param("s", $tabla);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()["total"] > 0;
}

function existeColumna($db, $tabla, $columna) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
         AND COLUMN_NAME = ?"
    );
    $stmt->bind_param("ss", $tabla, $columna);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()["total"] > 0;
}

function columnaFecha($db, $tabla) {
    foreach ([
        "fecha",
        "fecha_entrada",
        "fecha_salida",
        "created_at",
        "fecha_registro"
    ] as $campo) {
        if (existeColumna($db, $tabla, $campo)) {
            return $campo;
        }
    }
    return null;
}

$totalProductos = 0;
$totalBodegas = 0;
$totalUnidades = 0;
$totalEntradas = null;
$totalSalidas = null;
$inventarioInicial = null;
$entradasHoy = null;
$salidasHoy = null;
$stockBajo = 0;
$alertas = [];
$movimientos = [];
$grafico = [];
$avisos = [];
$limiteBajo = 10;

try {
    // PRODUCTOS REGISTRADOS
    if (existeTabla($db, "productos")) {
        $totalProductos = (int)$db->query(
            "SELECT COUNT(*) AS total FROM productos"
        )->fetch_assoc()["total"];
    }

    // BODEGAS REGISTRADAS
    if (existeTabla($db, "bodegas")) {
        $totalBodegas = (int)$db->query(
            "SELECT COUNT(*) AS total FROM bodegas"
        )->fetch_assoc()["total"];
    }

    // EXISTENCIAS REALES
    if (existeTabla($db, "inventario_bodegas")) {
        $totalUnidades = (int)$db->query(
            "SELECT COALESCE(SUM(cantidad),0) AS total
             FROM inventario_bodegas"
        )->fetch_assoc()["total"];
    }

    // TOTAL HISTÓRICO DE ENTRADAS
    if (
        existeTabla($db, "entradas") &&
        existeColumna($db, "entradas", "cantidad")
    ) {
        $totalEntradas = (int)$db->query(
            "SELECT COALESCE(SUM(cantidad),0) AS total
             FROM entradas"
        )->fetch_assoc()["total"];
    }

    // TOTAL HISTÓRICO DE SALIDAS
    if (
        existeTabla($db, "salidas") &&
        existeColumna($db, "salidas", "cantidad")
    ) {
        $totalSalidas = (int)$db->query(
            "SELECT COALESCE(SUM(cantidad),0) AS total
             FROM salidas"
        )->fetch_assoc()["total"];
    }

    // INVENTARIO INICIAL ESTIMADO
    if (
        existeTabla($db, "inventario_bodegas") &&
        $totalEntradas !== null &&
        $totalSalidas !== null
    ) {
        $inventarioInicial =
            $totalUnidades - $totalEntradas + $totalSalidas;
    }

    // ALERTAS DE STOCK BAJO
    if (
        existeTabla($db, "productos") &&
        existeTabla($db, "inventario_bodegas")
    ) {
        $stmt = $db->prepare(
            "SELECT
                p.id,
                p.nombre,
                COALESCE(SUM(ib.cantidad),0) AS stock
             FROM productos p
             LEFT JOIN inventario_bodegas ib
                ON ib.producto_id = p.id
             GROUP BY p.id, p.nombre
             HAVING stock <= ?
             ORDER BY stock ASC, p.nombre ASC"
        );

        $stmt->bind_param("i", $limiteBajo);
        $stmt->execute();

        $todasAlertas = $stmt->get_result()
                             ->fetch_all(MYSQLI_ASSOC);

        $stockBajo = count($todasAlertas);
        $alertas = array_slice($todasAlertas, 0, 5);
    }

    // IDENTIFICAR COLUMNAS DE FECHA
    $fechaEntradas = existeTabla($db, "entradas")
        ? columnaFecha($db, "entradas") : null;

    $fechaSalidas = existeTabla($db, "salidas")
        ? columnaFecha($db, "salidas") : null;

    // ENTRADAS REGISTRADAS HOY
    if ($fechaEntradas !== null) {
        $entradasHoy = (int)$db->query(
            "SELECT COUNT(*) AS total
             FROM entradas
             WHERE DATE(`$fechaEntradas`) = CURDATE()"
        )->fetch_assoc()["total"];
    } else {
        $avisos[] = "No se identificó la fecha de entradas.";
    }

    // SALIDAS REGISTRADAS HOY
    if ($fechaSalidas !== null) {
        $salidasHoy = (int)$db->query(
            "SELECT COUNT(*) AS total
             FROM salidas
             WHERE DATE(`$fechaSalidas`) = CURDATE()"
        )->fetch_assoc()["total"];
    } else {
        $avisos[] = "No se identificó la fecha de salidas.";
    }

    // GRÁFICA: UNIDADES DE LOS ÚLTIMOS 7 DÍAS
    for ($i = 6; $i >= 0; $i--) {
        $fecha = date("Y-m-d", strtotime("-$i days"));

        $cantidadEntradas = 0;
        $cantidadSalidas = 0;

        if ($fechaEntradas !== null) {
            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(cantidad),0) AS total
                 FROM entradas
                 WHERE DATE(`$fechaEntradas`) = ?"
            );

            $stmt->bind_param("s", $fecha);
            $stmt->execute();

            $cantidadEntradas = (int)$stmt->get_result()
                ->fetch_assoc()["total"];
        }

        if ($fechaSalidas !== null) {
            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(cantidad),0) AS total
                 FROM salidas
                 WHERE DATE(`$fechaSalidas`) = ?"
            );

            $stmt->bind_param("s", $fecha);
            $stmt->execute();

            $cantidadSalidas = (int)$stmt->get_result()
                ->fetch_assoc()["total"];
        }

        $grafico[] = [
            "dia" => date("d/m", strtotime($fecha)),
            "entradas" => $cantidadEntradas,
            "salidas" => $cantidadSalidas
        ];
    }

    // MOVIMIENTOS RECIENTES
    foreach ([
        [
            "tabla" => "entradas",
            "tipo" => "Entrada",
            "fecha" => $fechaEntradas
        ],
        [
            "tabla" => "salidas",
            "tipo" => "Salida",
            "fecha" => $fechaSalidas
        ]
    ] as $config) {

        $tabla = $config["tabla"];
        $campoFecha = $config["fecha"];

        if (
            $campoFecha === null ||
            !existeColumna($db, $tabla, "producto_id") ||
            !existeColumna($db, $tabla, "cantidad") ||
            !existeTabla($db, "productos")
        ) {
            continue;
        }

        $sql = "
            SELECT
                m.`$campoFecha` AS fecha,
                p.nombre AS producto,
                m.cantidad
            FROM `$tabla` m
            INNER JOIN productos p
                ON p.id = m.producto_id
            ORDER BY m.`$campoFecha` DESC
            LIMIT 10
        ";

        $resultado = $db->query($sql);

        while ($fila = $resultado->fetch_assoc()) {
            $fila["tipo"] = $config["tipo"];
            $movimientos[] = $fila;
        }
    }

    usort($movimientos, function($a, $b) {
        return strcmp(
            (string)$b["fecha"],
            (string)$a["fecha"]
        );
    });

    $movimientos = array_slice($movimientos, 0, 6);

} catch (Throwable $e) {
    $avisos[] = "Error al cargar indicadores: "
              . $e->getMessage();
}

// ESCALA DE LA GRÁFICA
$maxGrafico = 1;

foreach ($grafico as $dia) {
    $maxGrafico = max(
        $maxGrafico,
        $dia["entradas"],
        $dia["salidas"]
    );
}

function formatoNumero($valor) {
    return $valor === null
        ? "—"
        : number_format((int)$valor, 0, ",", ".");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>JECCI | Panel principal</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    background: #f1f4f8;
    color: #263548;
}

/* MENÚ LATERAL */
.menu {
    position: fixed;
    width: 250px;
    height: 100vh;
    background: #091d33;
    padding: 25px 17px;
    color: white;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 10;
}

.logo {
    text-align: center;
    padding: 10px 0 30px;
    border-bottom: 1px solid #294158;
    margin-bottom: 25px;
}

.logo h1 {
    font-size: 30px;
    letter-spacing: 3px;
}

.logo p {
    color: #a9bdce;
    font-size: 10px;
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
    border-radius: 9px;
    margin-bottom: 6px;
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
    margin-bottom: 30px;
    gap: 15px;
}

.encabezado h2 {
    font-size: 27px;
    color: #102b46;
}

.encabezado p {
    color: #8996a5;
    margin-top: 5px;
    font-size: 13px;
}

.perfil {
    display: flex;
    align-items: center;
    gap: 12px;
    background: white;
    padding: 11px 17px;
    border-radius: 12px;
}

.avatar {
    width: 38px;
    height: 38px;
    background: #e7edf5;
    color: #174d75;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: bold;
}

.perfil strong {
    font-size: 13px;
}

.perfil small {
    display: block;
    color: #8996a5;
    margin-top: 3px;
    font-size: 11px;
}

/* INDICADORES */
.tarjetas {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.tarjeta,
.panel {
    background: white;
    padding: 22px;
    border-radius: 15px;
    box-shadow: 0 4px 15px #102b460b;
    border: 1px solid #e9eef3;
}

.tarjeta {
    transition: .2s;
}

.tarjeta:hover {
    transform: translateY(-3px);
}

.tarjeta-superior {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.tarjeta h3 {
    font-size: 13px;
    color: #8290a0;
    font-weight: 500;
}

.icono {
    width: 43px;
    height: 43px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 21px;
    flex-shrink: 0;
}

.azul {
    background: #e5efff;
}

.verde {
    background: #e2f7ed;
}

.rojo {
    background: #ffe7e8;
}

.amarillo {
    background: #fff4d9;
}

.numero {
    font-size: 30px;
    font-weight: 700;
    color: #142f4a;
    margin-top: 16px;
}

.detalle {
    color: #98a3af;
    font-size: 11px;
    margin-top: 5px;
}

/* PANELES */
.panel {
    margin-bottom: 22px;
}

.panel-titulo {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 10px;
}

.panel-titulo h3 {
    font-size: 16px;
    color: #17334e;
}

.panel-titulo span {
    font-size: 12px;
    color: #8996a5;
}

/* GRÁFICA */
.grafico {
    height: 200px;
    display: flex;
    align-items: end;
    justify-content: space-around;
    border-bottom: 1px solid #e8edf2;
    padding: 0 10px;
    gap: 10px;
}

.barra-grupo {
    height: 100%;
    display: flex;
    align-items: end;
    gap: 5px;
    position: relative;
    padding-bottom: 30px;
}

.barra {
    width: 17px;
    border-radius: 5px 5px 0 0;
    background: #1e72ad;
}

.barra.roja {
    background: #e33b45;
}

.dia {
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    color: #8794a2;
    white-space: nowrap;
}

/* TABLAS */
.secciones {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    text-align: left;
    padding: 14px 10px;
    border-bottom: 1px solid #edf0f4;
    font-size: 12px;
}

th {
    background: #f7f9fb;
    color: #788798;
}

.estado {
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: bold;
}

.estado-entrada {
    background: #e0f7eb;
    color: #15945a;
}

.estado-salida {
    background: #ffe6e7;
    color: #d32c38;
}

.ver-todos {
    display: inline-block;
    color: #1769aa;
    text-decoration: none;
    font-size: 12px;
    margin-top: 17px;
    font-weight: bold;
}

.producto-alerta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid #edf0f4;
    gap: 10px;
}

.producto-alerta strong {
    display: block;
    font-size: 13px;
}

.producto-alerta small {
    color: #9aa5b0;
    font-size: 11px;
}

.stock {
    background: #fff0d2;
    color: #b5790c;
    padding: 6px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}

.aviso {
    background: #fff4d9;
    color: #92400e;
    border-radius: 9px;
    padding: 12px;
    margin-bottom: 15px;
    font-size: 13px;
}

.menu-btn {
    display: none;
    border: none;
    background: #102f4c;
    color: white;
    padding: 10px 14px;
    border-radius: 8px;
    cursor: pointer;
}

/* RESPONSIVE */
@media(max-width:1100px) {
    .tarjetas {
        grid-template-columns: repeat(2,1fr);
    }

    .secciones {
        grid-template-columns: 1fr;
    }
}

@media(max-width:700px) {
    .menu {
        transform: translateX(-100%);
        transition: .3s;
    }

    .menu.abierto {
        transform: translateX(0);
    }

    .contenido {
        margin-left: 0;
        padding: 18px;
    }

    .menu-btn {
        display: block;
    }

    .tarjetas {
        grid-template-columns: repeat(2,1fr);
        gap: 10px;
    }

    .tarjeta {
        padding: 15px;
    }

    .numero {
        font-size: 24px;
    }

    .encabezado {
        flex-wrap: wrap;
    }

    .panel {
        overflow-x: auto;
    }

    table {
        min-width: 400px;
    }
}
</style>
</head>

<body>

<!-- MENÚ -->
<aside class="menu" id="menu">

    <div class="logo">
        <h1>⚙ JECCI</h1>
        <p>SOLUCIONES INDUSTRIALES</p>
    </div>

    <div class="menu-titulo">MENÚ PRINCIPAL</div>

    <a href="dashboard.php" class="activo">🏠 Inicio</a>
    <a href="productos.php">📦 Productos</a>
    <a href="bodegas.php">🏬 Bodegas</a>
    <a href="entradas.php">➕ Entradas</a>
    <a href="salidas.php">➖ Salidas</a>
    <a href="proveedores.php">🚚 Proveedores</a>
    <a href="reportes.php">📊 Reportes</a>
    <a href="alertas.php">⚠️ Alertas</a>
    <a href="usuarios.php">👥 Usuarios</a>

    <div class="cerrar">
        <a href="index.php">↩ Cerrar sesión</a>
    </div>

</aside>

<!-- CONTENIDO -->
<main class="contenido">

    <header class="encabezado">

        <div>
            <button class="menu-btn"
                    onclick="abrirMenu()">☰</button>

            <h2>Panel principal</h2>

            <p>
                Indicadores conectados con la base
                de datos JECCI.
            </p>
        </div>

        <div class="perfil">
            <div class="avatar">A</div>

            <div>
                <strong>Administrador</strong>
                <small>Gestión de inventario</small>
            </div>
        </div>

    </header>

    <!-- AVISOS -->
    <?php foreach ($avisos as $aviso): ?>
        <div class="aviso">
            <?= escapar($aviso) ?>
        </div>
    <?php endforeach; ?>

    <!-- INDICADORES PRINCIPALES -->
    <section class="tarjetas">

        <div class="tarjeta">
            <div class="tarjeta-superior">
                <h3>Total de productos</h3>
                <div class="icono azul">📦</div>
            </div>

            <div class="numero">
                <?= formatoNumero($totalProductos) ?>
            </div>

            <div class="detalle">
                Productos registrados en MySQL
            </div>
        </div>

        <div class="tarjeta">
            <div class="tarjeta-superior">
                <h3>Unidades almacenadas</h3>
                <div class="icono azul">🏬</div>
            </div>

            <div class="numero">
                <?= formatoNumero($totalUnidades) ?>
            </div>

            <div class="detalle">
                Stock real en todas las bodegas
            </div>
        </div>

        <div class="tarjeta">
            <div class="tarjeta-superior">
                <h3>Unidades ingresadas</h3>
                <div class="icono verde">📥</div>
            </div>

            <div class="numero">
                <?= formatoNumero($totalEntradas) ?>
            </div>

            <div class="detalle">
                Total histórico de entradas
            </div>
        </div>

        <div class="tarjeta">
            <div class="tarjeta-superior">
                <h3>Unidades retiradas</h3>
                <div class="icono rojo">📤</div>
            </div>

            <div class="numero">
                <?= formatoNumero($totalSalidas) ?>
            </div>

            <div class="detalle">
                Total histórico de salidas
            </div>
        </div>

        <div class="tarjeta">
            <div class="tarjeta-superior">
                <h3>Entradas de hoy</h3>
                <div class="icono verde">➕</div>
            </div>

            <div class="numero">
                <?= formatoNumero($entradasHoy) ?>
            </div>

            <div class="detalle">
                Movimientos registrados hoy
            </div>
        </div>

        <div class="tarjeta">
            <div class="tarjeta-superior">
                <h3>Salidas de hoy</h3>
                <div class="icono rojo">➖</div>
            </div>

            <div class="numero">
                <?= formatoNumero($salidasHoy) ?>
            </div>

            <div class="detalle">
                Movimientos registrados hoy
            </div>
        </div>

        <div class="tarjeta">
            <div class="tarjeta-superior">
                <h3>Stock bajo</h3>
                <div class="icono amarillo">⚠️</div>
            </div>

            <div class="numero">
                <?= formatoNumero($stockBajo) ?>
            </div>

            <div class="detalle">
                Productos con <?= $limiteBajo ?>
                unidades o menos
            </div>
        </div>

        <div class="tarjeta">
            <div class="tarjeta-superior">
                <h3>Bodegas registradas</h3>
                <div class="icono azul">🏭</div>
            </div>

            <div class="numero">
                <?= formatoNumero($totalBodegas) ?>
            </div>

            <div class="detalle">
                Bodegas creadas en JECCI
            </div>
        </div>

    </section>

    <!-- INVENTARIO INICIAL ESTIMADO -->
    <section class="panel">

        <div class="panel-titulo">
            <h3>📊 Conciliación de inventario</h3>
            <span>Indicador calculado desde MySQL</span>
        </div>

        <div class="numero">
            <?= formatoNumero($inventarioInicial) ?>
        </div>

        <p class="detalle">
            Inventario inicial estimado =
            Stock actual - Entradas + Salidas.
        </p>

        <p class="detalle">
            Esta cifra es una estimación y requiere
            confirmar que todos los movimientos
            históricos estén registrados.
        </p>

    </section>

    <!-- GRÁFICA -->
    <section class="panel">

        <!-- GRÁFICA -->
<section class="panel">
    <div class="panel-titulo">
        <h3>📈 Movimientos de inventario</h3>
        <span>Unidades de los últimos 7 días</span>
    </div>

    <div class="grafico">
        <?php foreach ($grafico as $dia): ?>
            <?php
                $alturaEntrada = ($dia["entradas"] / $maxGrafico) * 100;
                $alturaSalida = ($dia["salidas"] / $maxGrafico) * 100;
            ?>

            <div class="barra-grupo">
                <div class="barra"
                     style="height:<?= $alturaEntrada ?>%"
                     title="Entradas: <?= (int)$dia["entradas"] ?> unidades">
                </div>

                <div class="barra roja"
                     style="height:<?= $alturaSalida ?>%"
                     title="Salidas: <?= (int)$dia["salidas"] ?> unidades">
                </div>

                <span class="dia">
                    <?= escapar($dia["dia"]) ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="detalle" style="margin-top:15px">
        <span style="color:#1e72ad">●</span>
        Entradas (unidades)
        &nbsp;&nbsp;
        <span style="color:#e33b45">●</span>
        Salidas (unidades)
    </div>
</section>

<!-- MOVIMIENTOS Y ALERTAS -->
<section class="secciones">

    <div class="panel">
        <div class="panel-titulo">
            <h3>📋 Movimientos recientes</h3>
            <span>Datos de MySQL</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td>
                            <?= escapar(date(
                                "d/m/Y",
                                strtotime($mov["fecha"])
                            )) ?>
                        </td>

                        <td>
                            <span class="estado <?= $mov["tipo"] === "Entrada"
                                ? "estado-entrada"
                                : "estado-salida" ?>">
                                <?= escapar($mov["tipo"]) ?>
                            </span>
                        </td>

                        <td>
                            <?= escapar($mov["producto"]) ?>
                        </td>

                        <td>
                            <?= formatoNumero($mov["cantidad"]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($movimientos)): ?>
                    <tr>
                        <td colspan="4">
                            No hay movimientos disponibles.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="reportes.php" class="ver-todos">
            Ver todos los reportes →
        </a>
    </div>

    <!-- ALERTAS DE STOCK -->
    <div class="panel">
        <div class="panel-titulo">
            <h3>⚠️ Stock bajo</h3>
            <span>Productos por revisar</span>
        </div>

        <?php foreach ($alertas as $producto): ?>
            <div class="producto-alerta">
                <div>
                    <strong>
                        <?= escapar($producto["nombre"]) ?>
                    </strong>
                    <small>Existencias disponibles</small>
                </div>

                <span class="stock">
                    <?= formatoNumero($producto["stock"]) ?>
                    unidades
                </span>
            </div>
        <?php endforeach; ?>

        <?php if (empty($alertas)): ?>
            <p class="detalle">
                No hay productos con stock bajo.
            </p>
        <?php endif; ?>

        <a href="alertas.php" class="ver-todos">
            Ver todas las alertas →
        </a>
    </div>

</section>

</main>

<script>
function abrirMenu() {
    document.getElementById("menu")
        .classList.toggle("abierto");
}
</script>

</body>
</html>