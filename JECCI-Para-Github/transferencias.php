<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/config/conexion.php';

$db = isset($conexion) ? $conexion : $conn;
$db->set_charset('utf8mb4');

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $producto = (int)($_POST['producto_id'] ?? 0);
    $origen = (int)($_POST['origen_id'] ?? 0);
    $destino = (int)($_POST['destino_id'] ?? 0);
    $cantidad = (int)($_POST['cantidad'] ?? 0);
    $responsable = trim($_POST['responsable'] ?? '');
    $observacion = trim($_POST['observacion'] ?? '');

    if ($producto <= 0 || $origen <= 0 || $destino <= 0 ||
        $origen === $destino || $cantidad <= 0 || $responsable === '') {
        $error = 'Completa correctamente los campos. Las bodegas deben ser diferentes.';
    } else {
        $transaccion = false;

        try {
            $db->begin_transaction();
            $transaccion = true;

            // Comprobar que ambas bodegas estén activas.
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS total
                 FROM bodegas
                 WHERE id IN (?, ?)
                   AND estado = 'Activa'"
            );
            $stmt->bind_param('ii', $origen, $destino);
            $stmt->execute();
            $activas = (int)$stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            if ($activas !== 2) {
                throw new RuntimeException('Las dos bodegas deben estar activas.');
            }

            // Retirar las unidades de la bodega de origen.
            $stmt = $db->prepare(
                "UPDATE inventario_bodegas
                 SET cantidad = cantidad - ?
                 WHERE producto_id = ?
                   AND bodega_id = ?
                   AND cantidad >= ?"
            );
            $stmt->bind_param(
                'iiii',
                $cantidad,
                $producto,
                $origen,
                $cantidad
            );
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException(
                    'La bodega de origen no tiene suficientes unidades.'
                );
            }
            $stmt->close();

            // Agregar unidades a la bodega de destino.
            $stmt = $db->prepare(
                "SELECT id
                 FROM inventario_bodegas
                 WHERE producto_id = ? AND bodega_id = ?
                 FOR UPDATE"
            );
            $stmt->bind_param('ii', $producto, $destino);
            $stmt->execute();
            $filaDestino = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($filaDestino) {
                $stmt = $db->prepare(
                    "UPDATE inventario_bodegas
                     SET cantidad = cantidad + ?
                     WHERE id = ?"
                );
                $idInventario = (int)$filaDestino['id'];
                $stmt->bind_param('ii', $cantidad, $idInventario);
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO inventario_bodegas
                     (producto_id, bodega_id, cantidad)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param('iii', $producto, $destino, $cantidad);
            }

            $stmt->execute();
            $stmt->close();

            // Guardar el movimiento.
            $stmt = $db->prepare(
                "INSERT INTO transferencias
                 (producto_id, bodega_origen_id, bodega_destino_id,
                  cantidad, responsable, observacion)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                'iiiiss',
                $producto,
                $origen,
                $destino,
                $cantidad,
                $responsable,
                $observacion
            );
            $stmt->execute();
            $stmt->close();

            // El stock general NO cambia en una transferencia.
            $db->commit();
            $transaccion = false;

            header('Location: transferencias.php?ok=1');
            exit;

        } catch (Throwable $ex) {
            if ($transaccion) {
                $db->rollback();
            }
            $error = 'No se pudo transferir: ' . $ex->getMessage();
        }
    }
}

$productos = $db->query(
    "SELECT id, codigo, nombre FROM productos ORDER BY nombre"
)->fetch_all(MYSQLI_ASSOC);

$bodegas = $db->query(
    "SELECT id, nombre, ubicacion
     FROM bodegas WHERE estado = 'Activa' ORDER BY id"
)->fetch_all(MYSQLI_ASSOC);

$existencias = $db->query(
    "SELECT producto_id, bodega_id, cantidad
     FROM inventario_bodegas"
)->fetch_all(MYSQLI_ASSOC);

$historial = $db->query(
    "SELECT t.fecha, t.cantidad, t.responsable, t.observacion,
            p.nombre AS producto,
            bo.nombre AS origen, bo.ubicacion AS ubicacion_origen,
            bd.nombre AS destino, bd.ubicacion AS ubicacion_destino
     FROM transferencias t
     JOIN productos p ON p.id = t.producto_id
     JOIN bodegas bo ON bo.id = t.bodega_origen_id
     JOIN bodegas bd ON bd.id = t.bodega_destino_id
     ORDER BY t.id DESC LIMIT 100"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transferencias | JECCI</title>
<style>
* { box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
body { margin: 0; background: #f1f4f9; color: #263445; }
.menu {
    position: fixed; left: 0; top: 0; width: 250px;
    height: 100vh; background: #101d35;
    padding: 25px 16px; overflow-y: auto;
}
.logo { text-align: center; color: white; margin-bottom: 35px; }
.logo h1 { font-size: 29px; margin: 0; }
.logo p { font-size: 10px; color: #9eacc2; }
.menu a {
    display: block; color: #b8c5d9; text-decoration: none;
    padding: 14px; border-radius: 9px; margin-bottom: 7px;
}
.menu a:hover, .menu a.activo { background: #2463eb; color: white; }
.contenido { margin-left: 250px; padding: 30px; }
h2 { color: #14233d; }
.panel {
    background: white; padding: 25px; border-radius: 13px;
    box-shadow: 0 3px 12px #00000009; margin-bottom: 23px;
}
.fila {
    display: grid; grid-template-columns: repeat(2, minmax(0,1fr));
    gap: 18px; margin-bottom: 18px;
}
label { display: block; font-weight: 600; margin-bottom: 7px; }
input, select, textarea {
    width: 100%; padding: 12px; border: 1px solid #d6dce5;
    border-radius: 8px; font-size: 14px; background: white;
}
textarea { min-height: 85px; resize: vertical; }
.btn {
    background: #2463eb; color: white; border: none;
    padding: 13px 24px; border-radius: 8px;
    cursor: pointer; font-weight: bold;
}
.btn:disabled { background: #94a3b8; cursor: not-allowed; }
.nota { color: #64748b; font-size: 13px; }
.alerta {
    padding: 14px; margin-bottom: 20px; border-radius: 8px;
    background: #fff1f2; color: #9f1239;
}
.exito { background: #ecfdf5; color: #166534; }
.tabla { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 800px; }
th, td {
    text-align: left; padding: 13px;
    border-bottom: 1px solid #e5e7eb; font-size: 13px;
}
th { background: #f5f7fb; }
@media(max-width:800px) {
    .menu { position: static; width: 100%; height: auto; }
    .contenido { margin-left: 0; padding: 18px; }
    .fila { grid-template-columns: 1fr; }
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
    <a href="salidas.php">➖ Salidas</a>
    <a href="transferencias.php" class="activo">🔄 Transferencias</a>
    <a href="proveedores.php">🚚 Proveedores</a>
    <a href="reportes.php">📊 Reportes</a>
    <a href="alertas.php">⚠ Alertas</a>
    <a href="usuarios.php">👥 Usuarios</a>
    <a href="index.php">↩ Cerrar sesión</a>
</aside>

<main class="contenido">
    <h2>🔄 Transferencias entre Bodegas</h2>
    <p class="nota">Traslada productos sin modificar el stock general.</p>

    <?php if (isset($_GET['ok'])): ?>
        <div class="alerta exito">✅ Transferencia realizada correctamente.</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alerta"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h3>Registrar transferencia</h3>

        <form method="POST" id="formTransferencia">
            <div class="fila">
                <div>
                    <label>Producto *</label>
                    <select name="producto_id" id="producto" required>
                        <option value="">Selecciona un producto</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= e($p['codigo'] . ' - ' . $p['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Cantidad *</label>
                    <input type="number" name="cantidad" id="cantidad"
                           min="1" step="1" required>
                </div>
            </div>

            <div class="fila">
                <div>
                    <label>Bodega de origen *</label>
                    <select name="origen_id" id="origen" required>
                        <option value="">Selecciona origen</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?= (int)$b['id'] ?>">
                                <?= e($b['nombre'] . ' - ' . $b['ubicacion']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Bodega de destino *</label>
                    <select name="destino_id" id="destino" required>
                        <option value="">Selecciona destino</option>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?= (int)$b['id'] ?>">
                                <?= e($b['nombre'] . ' - ' . $b['ubicacion']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <p class="nota" id="disponible">
                Selecciona un producto y una bodega de origen.
            </p>

            <div class="fila">
                <div>
                    <label>Responsable *</label>
                    <input type="text" name="responsable"
                           maxlength="100" required>
                </div>
                <div>
                    <label>Observación</label>
                    <textarea name="observacion"
                              placeholder="Motivo del traslado"></textarea>
                </div>
            </div>

            <button type="submit" name="guardar" value="1"
                    class="btn" id="guardar" disabled>
                ✅ Registrar transferencia
            </button>
        </form>
    </section>

    <section class="panel">
        <h3>Historial de transferencias</h3>
        <div class="tabla">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Cantidad</th>
                        <th>Responsable</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $t): ?>
                        <tr>
                            <td><?= e($t['fecha']) ?></td>
                            <td><?= e($t['producto']) ?></td>
                            <td><?= e($t['origen'] . ' - ' . $t['ubicacion_origen']) ?></td>
                            <td><?= e($t['destino'] . ' - ' . $t['ubicacion_destino']) ?></td>
                            <td><?= (int)$t['cantidad'] ?></td>
                            <td><?= e($t['responsable']) ?></td>
                            <td><?= e($t['observacion'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$historial): ?>
                        <tr><td colspan="7">No hay transferencias registradas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script>
const existencias = <?= json_encode($existencias) ?>;
const producto = document.getElementById('producto');
const origen = document.getElementById('origen');
const destino = document.getElementById('destino');
const cantidad = document.getElementById('cantidad');
const disponible = document.getElementById('disponible');
const guardar = document.getElementById('guardar');
const formulario = document.getElementById('formTransferencia');

function actualizar() {
    const productoId = Number(producto.value);
    const origenId = Number(origen.value);
    const destinoId = Number(destino.value);

    const fila = existencias.find(x =>
        Number(x.producto_id) === productoId &&
        Number(x.bodega_id) === origenId
    );

    const stock = fila ? Number(fila.cantidad) : 0;

    if (!productoId || !origenId) {
        disponible.textContent = 'Selecciona un producto y una bodega de origen.';
        guardar.disabled = true;
        return;
    }

    disponible.textContent = 'Disponibles en origen: ' + stock;
    cantidad.max = String(stock);

    guardar.disabled =
        !destinoId || origenId === destinoId || stock <= 0;

    if (origenId === destinoId && destinoId) {
        disponible.textContent += ' — El origen y destino deben ser diferentes.';
    }
}

producto.addEventListener('change', actualizar);
origen.addEventListener('change', actualizar);
destino.addEventListener('change', actualizar);

formulario.addEventListener('submit', function(event) {
    const n = Number(cantidad.value);
    const max = Number(cantidad.max);

    if (!Number.isInteger(n) || n <= 0 || n > max ||
        origen.value === destino.value) {
        event.preventDefault();
        alert('Revisa las bodegas y la cantidad disponible.');
    }
});

actualizar();
</script>
</body>
</html>