<?php
require_once __DIR__ . '/config/conexion.php';

$db = $conexion ?? $conn ?? null;

if (!$db instanceof mysqli) {
    die('No se encontró la conexión MySQL.');
}

$db->set_charset('utf8mb4');

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function consultar($db, $sql) {
    $r = $db->query($sql);
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

function numero($v) {
    return number_format((int)$v, 0, ',', '.');
}

$tipos = [
    'movimientos' => 'Movimientos de inventario',
    'entradas' => 'Entradas de productos',
    'salidas' => 'Salidas de productos',
    'stock' => 'Stock actual por producto y bodega',
    'bajo' => 'Productos con stock bajo'
];

$tipo = $_GET['tipo'] ?? 'movimientos';

if (!isset($tipos[$tipo])) {
    $tipo = 'movimientos';
}

$inicio = $_GET['inicio'] ?? '';
$fin = $_GET['fin'] ?? '';
$error = '';

foreach (['inicio' => $inicio, 'fin' => $fin] as $campo => $valor) {
    if ($valor !== '') {
        $fecha = DateTime::createFromFormat('!Y-m-d', $valor);

        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ||
            !$fecha ||
            $fecha->format('Y-m-d') !== $valor
        ) {
            $error = 'Las fechas no son válidas.';
        }
    }
}

if ($inicio !== '' && $fin !== '' && $inicio > $fin) {
    $error = 'La fecha inicial no puede ser posterior a la final.';
}

$desde = $inicio !== ''
    ? " AND fecha >= '" . $db->real_escape_string($inicio) . "'"
    : '';

$hasta = $fin !== ''
    ? " AND fecha <= '" . $db->real_escape_string($fin) . "'"
    : '';

$filtroEntradas = $desde . $hasta;
$filtroSalidas = $desde . $hasta;

// INDICADORES REALES DE MYSQL

$totales = consultar($db, "
    SELECT
        (SELECT COALESCE(SUM(cantidad), 0) FROM entradas) AS entradas,
        (SELECT COALESCE(SUM(cantidad), 0) FROM salidas) AS salidas,
        (SELECT COALESCE(SUM(cantidad), 0) FROM inventario_bodegas) AS unidades,
        (SELECT COUNT(*) FROM productos) AS productos
");

$indicadores = $totales[0] ?? [
    'entradas' => 0,
    'salidas' => 0,
    'unidades' => 0,
    'productos' => 0
];

// STOCK REAL POR PRODUCTO

$stocks = consultar($db, "
    SELECT
        p.id,
        p.codigo,
        p.nombre,
        p.categoria,
        COALESCE(SUM(ib.cantidad), 0) AS stock
    FROM productos p
    LEFT JOIN inventario_bodegas ib
        ON ib.producto_id = p.id
    GROUP BY
        p.id, p.codigo, p.nombre, p.categoria
    ORDER BY p.nombre
");

$bajos = array_values(array_filter(
    $stocks,
    fn($p) => (int)$p['stock'] <= 10
));

$filas = [];
$encabezados = [];

// GENERAR REPORTE

if (!$error) {

    if (
        $tipo === 'movimientos' ||
        $tipo === 'entradas' ||
        $tipo === 'salidas'
    ) {

        $partes = [];

        if ($tipo !== 'salidas') {
            $partes[] = "
                SELECT
                    e.fecha AS fecha,
                    'Entrada' AS movimiento,
                    p.codigo AS codigo,
                    p.nombre AS producto,
                    COALESCE(b.nombre, 'Sin bodega') AS bodega,
                    e.cantidad AS cantidad,
                    '' AS tipo_salida,
                    '' AS responsable,
                    COALESCE(e.observacion, '') AS observacion,
                    e.id AS orden_id
                FROM entradas e
                JOIN productos p ON p.id = e.producto_id
                LEFT JOIN bodegas b ON b.id = e.bodega_id
                WHERE 1=1 $filtroEntradas
            ";
        }

        if ($tipo !== 'entradas') {
            $partes[] = "
                SELECT
                    s.fecha AS fecha,
                    'Salida' AS movimiento,
                    p.codigo AS codigo,
                    p.nombre AS producto,
                    COALESCE(b.nombre, 'Sin bodega') AS bodega,
                    s.cantidad AS cantidad,
                    s.tipo_salida AS tipo_salida,
                    s.responsable AS responsable,
                    COALESCE(s.observacion, '') AS observacion,
                    s.id AS orden_id
                FROM salidas s
                JOIN productos p ON p.id = s.producto_id
                LEFT JOIN bodegas b ON b.id = s.bodega_id
                WHERE 1=1 $filtroSalidas
            ";
        }

        $sql = "
            SELECT
                fecha,
                movimiento,
                codigo,
                producto,
                bodega,
                cantidad,
                tipo_salida,
                responsable,
                observacion
            FROM (" . implode(' UNION ALL ', $partes) . ") m
            ORDER BY fecha DESC, orden_id DESC
            LIMIT 5000
        ";

        $filas = consultar($db, $sql);

        $encabezados = [
            'fecha' => 'Fecha',
            'movimiento' => 'Movimiento',
            'codigo' => 'Código',
            'producto' => 'Producto',
            'bodega' => 'Bodega',
            'cantidad' => 'Cantidad',
            'tipo_salida' => 'Tipo de salida',
            'responsable' => 'Responsable',
            'observacion' => 'Observación'
        ];

    } elseif ($tipo === 'stock') {

        $filas = consultar($db, "
            SELECT
                p.codigo AS codigo,
                p.nombre AS producto,
                p.categoria AS categoria,
                b.nombre AS bodega,
                ib.cantidad AS cantidad
            FROM inventario_bodegas ib
            JOIN productos p ON p.id = ib.producto_id
            JOIN bodegas b ON b.id = ib.bodega_id
            ORDER BY p.nombre, b.nombre
        ");

        $encabezados = [
            'codigo' => 'Código',
            'producto' => 'Producto',
            'categoria' => 'Categoría',
            'bodega' => 'Bodega',
            'cantidad' => 'Unidades'
        ];

    } else {

        $filas = array_map(
            fn($p) => [
                'codigo' => $p['codigo'],
                'producto' => $p['nombre'],
                'categoria' => $p['categoria'],
                'cantidad' => $p['stock'],
                'estado' => (int)$p['stock'] === 0
                    ? 'Sin existencias'
                    : 'Stock bajo'
            ],
            $bajos
        );

        $encabezados = [
            'codigo' => 'Código',
            'producto' => 'Producto',
            'categoria' => 'Categoría',
            'cantidad' => 'Unidades',
            'estado' => 'Estado'
        ];
    }
}

// EXPORTAR A EXCEL MEDIANTE CSV

if (!$error && ($_GET['exportar'] ?? '') === 'csv') {

    header('Content-Type: text/csv; charset=UTF-8');

    header(
        'Content-Disposition: attachment; filename="jecci_' .
        $tipo . '_' . date('Ymd') . '.csv"'
    );

    $out = fopen('php://output', 'w');

    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, array_values($encabezados), ';');

    foreach ($filas as $fila) {
        fputcsv(
            $out,
            array_map(
                fn($clave) => $fila[$clave] ?? '',
                array_keys($encabezados)
            ),
            ';'
        );
    }

    fclose($out);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Reportes | JECCI</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: #f1f4f9;
    color: #263445;
    font-family: 'Segoe UI', Arial, sans-serif;
}

.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 250px;
    background: #101d35;
    padding: 25px 16px;
    overflow: auto;
}

.logo {
    text-align: center;
    color: white;
    margin-bottom: 32px;
}

.logo h1 {
    font-size: 30px;
    margin: 0;
}

.logo small {
    color: #9eacc2;
}

.menu a {
    display: block;
    color: #cbd5e1;
    text-decoration: none;
    padding: 13px 15px;
    border-radius: 9px;
    margin-bottom: 5px;
}

.menu a:hover,
.menu a.activo {
    background: #2563eb;
    color: white;
}

.contenido {
    margin-left: 250px;
    padding: 30px;
    max-width: 1700px;
}

.subtitulo {
    color: #64748b;
}

.tarjeta,
.indicador {
    background: white;
    border-radius: 13px;
    padding: 23px;
    box-shadow: 0 3px 12px #0000000a;
}

.tarjeta {
    margin-bottom: 22px;
}

.resumen {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 15px;
    margin: 22px 0;
}

.indicador small {
    color: #64748b;
}

.indicador strong {
    display: block;
    font-size: 28px;
    color: #2563eb;
    margin-top: 9px;
}

.filtros {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 14px;
    align-items: end;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 14px;
}

input,
select {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
}

.boton {
    border: 0;
    border-radius: 8px;
    padding: 12px 16px;
    background: #2563eb;
    color: white;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
}

.secundario {
    background: #64748b;
}

.exportar {
    background: #0f766e;
}

.acciones {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.tabla {
    overflow-x: auto;
}

table {
    border-collapse: collapse;
    width: 100%;
    min-width: 900px;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    font-size: 13px;
}

th {
    background: #f8fafc;
}

.entrada {
    color: #047857;
    font-weight: 700;
}

.salida {
    color: #be123c;
    font-weight: 700;
}

.error {
    padding: 14px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 8px;
    margin: 16px 0;
}

.nota {
    color: #64748b;
    font-size: 13px;
}

@media (max-width: 1100px) {
    .resumen {
        grid-template-columns: repeat(2, 1fr);
    }

    .filtros {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 750px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }

    .contenido {
        margin: 0;
        padding: 16px;
    }

    .resumen,
    .filtros {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">
        <h1>JECCI</h1>
        <small>SISTEMA DE INVENTARIOS</small>
    </div>

    <nav class="menu">
        <a href="dashboard.php">🏠 Panel principal</a>
        <a href="productos.php">📦 Productos</a>
        <a href="proveedores.php">🚚 Proveedores</a>
        <a href="bodegas.php">🏬 Bodegas</a>
        <a href="entradas.php">➕ Entradas</a>
        <a href="salidas.php">➖ Salidas</a>
        <a href="reportes.php" class="activo">📊 Reportes</a>
        <a href="alertas.php">⚠️ Alertas</a>
        <a href="usuarios.php">👥 Usuarios</a>
    </nav>

</aside>

<main class="contenido">

    <h2>📊 Reportes de inventario</h2>

    <p class="subtitulo">
        Información consultada directamente desde MySQL.
    </p>

    <div class="resumen">

        <div class="indicador">
            <small>Unidades ingresadas (histórico)</small>
            <strong>
                <?= numero($indicadores['entradas']) ?>
            </strong>
        </div>

        <div class="indicador">
            <small>Unidades retiradas (histórico)</small>
            <strong>
                <?= numero($indicadores['salidas']) ?>
            </strong>
        </div>

        <div class="indicador">
            <small>Unidades almacenadas</small>
            <strong>
                <?= numero($indicadores['unidades']) ?>
            </strong>
        </div>

        <div class="indicador">
            <small>Productos registrados</small>
            <strong>
                <?= numero($indicadores['productos']) ?>
            </strong>
        </div>

        <div class="indicador">
            <small>Productos con stock bajo (10 o menos)</small>
            <strong>
                <?= numero(count($bajos)) ?>
            </strong>
        </div>

    </div>

    <section class="tarjeta">

        <h3>Generar reporte</h3>

        <form method="get" class="filtros">

            <div>
                <label for="tipo">Tipo de reporte</label>

                <select name="tipo" id="tipo">

                    <?php foreach ($tipos as $valor => $nombre): ?>

                        <option
                            value="<?= h($valor) ?>"
                            <?= $tipo === $valor ? 'selected' : '' ?>
                        >
                            <?= h($nombre) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div>
                <label for="inicio">Fecha inicial</label>

                <input
                    type="date"
                    id="inicio"
                    name="inicio"
                    value="<?= h($inicio) ?>"
                >
            </div>

            <div>
                <label for="fin">Fecha final</label>

                <input
                    type="date"
                    id="fin"
                    name="fin"
                    value="<?= h($fin) ?>"
                >
            </div>

            <div>
                <button class="boton" type="submit">
                    🔎 Generar
                </button>
            </div>

        </form>

        <p class="nota">
            Las fechas filtran entradas y salidas.
            Los reportes de existencias muestran el stock actual.
        </p>

    </section>

    <section class="tarjeta">

        <h3><?= h($tipos[$tipo]) ?></h3>

        <?php if ($error): ?>

            <div class="error">
                <?= h($error) ?>
            </div>

        <?php else: ?>

            <div class="acciones">

                <a
                    class="boton exportar"
                    href="?<?= h(http_build_query([
                        'tipo' => $tipo,
                        'inicio' => $inicio,
                        'fin' => $fin,
                        'exportar' => 'csv'
                    ])) ?>"
                >
                    ⬇ Exportar CSV
                </a>

                <a class="boton secundario" href="reportes.php">
                    Limpiar filtros
                </a>

            </div>

            <p class="nota">
                <?= numero(count($filas)) ?> registros mostrados.
            </p>

            <div class="tabla">

                <table>

                    <thead>
                        <tr>
                            <?php foreach ($encabezados as $etiqueta): ?>
                                <th><?= h($etiqueta) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($filas as $fila): ?>

                            <tr>

                                <?php foreach ($encabezados as $clave => $etiqueta): ?>

                                    <td class="<?=
                                        $clave === 'movimiento'
                                            ? ($fila[$clave] === 'Entrada' ? 'entrada' : 'salida')
                                            : ''
                                    ?>">
                                        <?= h($fila[$clave] ?? '') ?>
                                    </td>

                                <?php endforeach; ?>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (!$filas): ?>

                            <tr>
                                <td colspan="<?= count($encabezados) ?>">
                                    No se encontraron registros para este reporte.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>