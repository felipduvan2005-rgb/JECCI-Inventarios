
<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/config/conexion.php';

$db = isset($conexion) ? $conexion : $conn;
$db->set_charset('utf8mb4');

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {

    $producto = (int)($_POST['producto_id'] ?? 0);
    $bodega = (int)($_POST['bodega_id'] ?? 0);
    $cantidad = (int)($_POST['cantidad'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');
    $tipo = trim($_POST['tipo_salida'] ?? '');
    $observacion = trim($_POST['observacion'] ?? '');

    $tipos = ['Devolucion', 'Hurto', 'Vencimiento', 'Otros'];

    $fechaValida =
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) &&
        checkdate(
            (int)substr($fecha, 5, 2),
            (int)substr($fecha, 8, 2),
            (int)substr($fecha, 0, 4)
        );

    if (
        $producto <= 0 ||
        $bodega <= 0 ||
        $cantidad <= 0 ||
        !in_array($tipo, $tipos, true) ||
        $responsable === '' ||
        !$fechaValida
    ) {
        $error = 'Completa correctamente todos los campos obligatorios.';
    } else {

        $transaccionIniciada = false;

        try {
            $db->begin_transaction();
            $transaccionIniciada = true;

            // Descontar existencias de la bodega.
            $stmt = $db->prepare(
                "UPDATE inventario_bodegas
                 SET cantidad = cantidad - ?
                 WHERE producto_id = ?
                 AND bodega_id = ?
                 AND cantidad >= ?
                 AND EXISTS (
                     SELECT 1 FROM bodegas
                     WHERE bodegas.id = ?
                     AND bodegas.estado = 'Activa'
                 )"
            );

            $stmt->bind_param(
                'iiiii',
                $cantidad,
                $producto,
                $bodega,
                $cantidad,
                $bodega
            );

            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException(
                    'La bodega no está activa o no tiene existencias suficientes.'
                );
            }

            $stmt->close();

            // Descontar existencias generales.
            $stmt = $db->prepare(
                "UPDATE productos
                 SET stock = stock - ?
                 WHERE id = ?
                 AND stock >= ?"
            );

            $stmt->bind_param(
                'iii',
                $cantidad,
                $producto,
                $cantidad
            );

            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException(
                    'El stock general es insuficiente o no coincide.'
                );
            }

            $stmt->close();

            // Guardar el movimiento.
            $stmt = $db->prepare(
                "INSERT INTO salidas
                (
                    producto_id,
                    bodega_id,
                    cantidad,
                    fecha,
                    responsable,
                    tipo_salida,
                    observacion
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'iiissss',
                $producto,
                $bodega,
                $cantidad,
                $fecha,
                $responsable,
                $tipo,
                $observacion
            );

            $stmt->execute();
            $stmt->close();

            $db->commit();
            $transaccionIniciada = false;

            header('Location: salidas.php?ok=1');
            exit;

        } catch (Throwable $ex) {

            if ($transaccionIniciada) {
                $db->rollback();
            }

            $error = 'No se pudo registrar la salida: ' .
                     $ex->getMessage();
        }
    }
}

// Consultar productos.
$productos = $db->query(
    "SELECT id, codigo, nombre, stock
     FROM productos
     ORDER BY nombre"
)->fetch_all(MYSQLI_ASSOC);

// Consultar bodegas activas.
$bodegas = $db->query(
    "SELECT id, nombre
     FROM bodegas
     WHERE estado = 'Activa'
     ORDER BY nombre"
)->fetch_all(MYSQLI_ASSOC);

// Consultar existencias por bodega.
$existencias = $db->query(
    "SELECT producto_id, bodega_id, cantidad
     FROM inventario_bodegas"
)->fetch_all(MYSQLI_ASSOC);

// Consultar historial.
$historial = $db->query(
    "SELECT
        s.fecha,
        s.cantidad,
        s.responsable,
        s.tipo_salida,
        s.observacion,
        p.nombre AS producto,
        p.codigo,
        b.nombre AS bodega
     FROM salidas s
     INNER JOIN productos p
        ON p.id = s.producto_id
     INNER JOIN bodegas b
        ON b.id = s.bodega_id
     ORDER BY s.id DESC
     LIMIT 100"
)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Salidas | JECCI</title>

<style>
* {
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    margin: 0;
    background: #f1f4f9;
    color: #263445;
}

.menu {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100vh;
    background: #101d35;
    padding: 25px 16px;
    overflow-y: auto;
}

.logo {
    text-align: center;
    color: white;
    margin-bottom: 35px;
}

.logo h1 {
    font-size: 29px;
    margin: 0;
}

.logo p {
    font-size: 10px;
    color: #9eacc2;
    margin-top: 7px;
}

.menu a {
    display: block;
    color: #b8c5d9;
    text-decoration: none;
    padding: 14px;
    border-radius: 9px;
    margin-bottom: 7px;
}

.menu a:hover,
.menu a.activo {
    background: #2463eb;
    color: white;
}

.contenido {
    margin-left: 250px;
    padding: 30px;
}

.cabecera {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.cabecera h2 {
    margin: 0;
    color: #14233d;
    font-size: 27px;
}

.usuario {
    background: white;
    padding: 13px 20px;
    border-radius: 12px;
}

.panel {
    background: white;
    padding: 25px;
    border-radius: 13px;
    box-shadow: 0 3px 12px #00000009;
    margin-bottom: 23px;
}

.panel h3 {
    margin-top: 0;
    margin-bottom: 23px;
}

.fila {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 18px;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 7px;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #d6dce5;
    border-radius: 8px;
    font-size: 14px;
    background: white;
}

textarea {
    min-height: 90px;
    resize: vertical;
}

.btn {
    background: #2463eb;
    color: white;
    border: none;
    padding: 13px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    margin-top: 18px;
}

.btn:hover {
    background: #1d4ed8;
}

.btn:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

.nota {
    color: #64748b;
    font-size: 13px;
}

.alerta {
    padding: 14px;
    margin-bottom: 20px;
    border-radius: 8px;
    background: #fff1f2;
    color: #9f1239;
}

.exito {
    background: #ecfdf5;
    color: #166534;
}

.tabla {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th,
td {
    text-align: left;
    padding: 13px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
}

th {
    background: #f5f7fb;
    color: #64748b;
}

.cantidad-negativa {
    color: #dc2626;
    font-weight: bold;
}

@media(max-width:800px) {
    .menu {
        position: static;
        width: 100%;
        height: auto;
    }

    .contenido {
        margin-left: 0;
        padding: 18px;
    }

    .fila {
        grid-template-columns: 1fr;
    }

    .cabecera {
        display: block;
    }

    .usuario {
        margin-top: 12px;
        display: inline-block;
    }
}
</style>
</head>

<body>

<aside class="menu">

    <div class="logo">
        <h1>⚙ JECCI</h1>
        <p>SOLUCIONES INDUSTRIALES</p>
    </div>

    <a href="dashboard.php">🏠 Inicio</a>
    <a href="productos.php">📦 Productos</a>
    <a href="bodegas.php">🏬 Bodegas</a>
    <a href="entradas.php">➕ Entradas</a>
    <a href="salidas.php" class="activo">➖ Salidas</a>
    <a href="transferencias.php">🔄 Transferencias</a>
    <a href="proveedores.php">🚚 Proveedores</a>
    <a href="reportes.php">📊 Reportes</a>
    <a href="alertas.php">⚠ Alertas</a>
    <a href="usuarios.php">👥 Usuarios</a>
    <a href="index.php">↩ Cerrar sesión</a>

</aside>

<main class="contenido">

<header class="cabecera">

    <div>
        <h2>➖ Registro de Salidas</h2>
        <p class="nota">
            Control de productos que salen de las bodegas JECCI.
        </p>
    </div>

    <div class="usuario">
        👤 Administrador
    </div>

</header>

<?php if (isset($_GET['ok'])): ?>
<div class="alerta exito">
    ✅ Salida registrada correctamente.
    El inventario general y el de la bodega fueron actualizados.
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alerta">
    <?= e($error) ?>
</div>
<?php endif; ?>

<section class="panel">

<h3>📝 Registrar nueva salida</h3>

<form method="POST" id="formSalida">

<div class="fila">

    <div>
        <label for="producto">Producto *</label>

        <select id="producto" name="producto_id" required>
            <option value="">Selecciona un producto</option>

            <?php foreach ($productos as $p): ?>
                <option value="<?= (int)$p['id'] ?>">
                    <?= e($p['codigo'] . ' — ' . $p['nombre']) ?>
                    (Stock general: <?= (int)$p['stock'] ?>)
                </option>
            <?php endforeach; ?>

        </select>
    </div>

    <div>
        <label for="bodega">Bodega de origen *</label>

        <select id="bodega" name="bodega_id" required>
            <option value="">Selecciona una bodega</option>

            <?php foreach ($bodegas as $b): ?>
                <option value="<?= (int)$b['id'] ?>">
                    <?= e($b['nombre']) ?>
                </option>
            <?php endforeach; ?>

        </select>

        <p class="nota" id="disponible">
            Selecciona un producto y una bodega.
        </p>
    </div>

</div>

<div class="fila">

    <div>
        <label for="cantidad">Cantidad a retirar *</label>

        <input
            type="number"
            id="cantidad"
            name="cantidad"
            min="1"
            step="1"
            placeholder="Ej: 5"
            required
        >
    </div>

    <div>
        <label for="tipo">Tipo de salida *</label>

        <select id="tipo" name="tipo_salida" required>
            <option value="">Selecciona un tipo</option>
            <option value="Devolucion">Devolución</option>
            <option value="Hurto">Hurto</option>
            <option value="Vencimiento">Vencimiento</option>
            <option value="Otros">Otros</option>
        </select>
    </div>

</div>

<div class="fila">

    <div>
        <label for="fecha">Fecha *</label>

        <input
            type="date"
            id="fecha"
            name="fecha"
            value="<?= date('Y-m-d') ?>"
            required
        >
    </div>

    <div>
        <label for="responsable">Responsable *</label>

        <input
            type="text"
            id="responsable"
            name="responsable"
            maxlength="100"
            placeholder="Nombre del responsable"
            required
        >
    </div>

</div>

<label for="observacion">Observaciones</label>

<textarea
    id="observacion"
    name="observacion"
    placeholder="Ej: Entrega de materiales, uso interno, devolución..."
></textarea>

<button
    type="submit"
    name="guardar"
    value="1"
    class="btn"
    id="btnGuardar"
>
    ✅ Registrar salida
</button>

</form>

</section>

<section class="panel">

<h3>📋 Historial de Salidas</h3>

<div class="tabla">

<table>

<thead>
<tr>
    <th>Fecha</th>
    <th>Código</th>
    <th>Producto</th>
    <th>Bodega</th>
    <th>Cantidad</th>
    <th>Tipo</th>
    <th>Responsable</th>
    <th>Observaciones</th>
</tr>
</thead>

<tbody>

<?php foreach ($historial as $s): ?>

<tr>
    <td><?= e($s['fecha']) ?></td>
    <td><?= e($s['codigo']) ?></td>
    <td><?= e($s['producto']) ?></td>
    <td><?= e($s['bodega']) ?></td>

    <td class="cantidad-negativa">
        -<?= (int)$s['cantidad'] ?>
    </td>

    <td><?= e($s['tipo_salida']) ?></td>
    <td><?= e($s['responsable']) ?></td>
    <td><?= e($s['observacion'] ?: '—') ?></td>
</tr>

<?php endforeach; ?>

<?php if (empty($historial)): ?>

<tr>
    <td colspan="8">
        Todavía no hay salidas registradas.
    </td>
</tr>

<?php endif; ?>

</tbody>
</table>

</div>

</section>

</main>

<script>
const existencias = <?= json_encode(
    $existencias,
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

const producto = document.getElementById('producto');
const bodega = document.getElementById('bodega');
const cantidad = document.getElementById('cantidad');
const disponible = document.getElementById('disponible');
const boton = document.getElementById('btnGuardar');
const formulario = document.getElementById('formSalida');

function actualizar() {

    const productoId = Number(producto.value);
    const bodegaId = Number(bodega.value);

    if (!productoId || !bodegaId) {
        disponible.textContent =
            'Selecciona un producto y una bodega.';
        cantidad.removeAttribute('max');
        boton.disabled = true;
        return;
    }

    const fila = existencias.find(x =>
        Number(x.producto_id) === productoId &&
        Number(x.bodega_id) === bodegaId
    );

    const stockBodega = fila ? Number(fila.cantidad) : 0;

    disponible.textContent =
        'Disponibles en esta bodega: ' + stockBodega;

    cantidad.max = String(stockBodega);

    boton.disabled = stockBodega <= 0;

    if (stockBodega <= 0) {
        disponible.textContent +=
            ' — No hay unidades disponibles.';
    }
}

producto.addEventListener('change', actualizar);
bodega.addEventListener('change', actualizar);

formulario.addEventListener('submit', function(event) {

    const n = Number(cantidad.value);
    const maximo = Number(cantidad.max);

    if (
        !Number.isInteger(n) ||
        n <= 0 ||
        !producto.value ||
        !bodega.value ||
        n > maximo
    ) {
        event.preventDefault();
        alert('Verifica la cantidad y las existencias disponibles.');
    }
});

actualizar();
</script>

</body>
</html>
