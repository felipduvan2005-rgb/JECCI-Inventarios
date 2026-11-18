
<?php
require_once __DIR__ . "/config/conexion.php";

$db = $conn ?? $conexion ?? null;

if (!$db instanceof mysqli) {
    die("Error: no se encontró la conexión MySQL.");
}

$db->set_charset("utf8mb4");

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

$mensaje = "";

// REGISTRAR NUEVA ENTRADA
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["guardar"])) {

    $producto_id = (int)($_POST["producto_id"] ?? 0);
    $bodega_id = (int)($_POST["bodega_id"] ?? 0);
    $cantidad = filter_var(
        $_POST["cantidad"] ?? null,
        FILTER_VALIDATE_INT
    );
    $fecha = trim($_POST["fecha"] ?? "");
    $observacion = trim($_POST["observacion"] ?? "");

    $fecha_obj = DateTime::createFromFormat("!Y-m-d", $fecha);

    if (
        $producto_id <= 0 ||
        $bodega_id <= 0 ||
        $cantidad === false ||
        $cantidad <= 0 ||
        !$fecha_obj ||
        $fecha_obj->format("Y-m-d") !== $fecha
    ) {
        $mensaje = "Completa correctamente los campos obligatorios.";
    } else {

        try {
            $db->begin_transaction();

            // Verificar producto
            $stmt = $db->prepare(
                "SELECT id FROM productos
                 WHERE id = ? FOR UPDATE"
            );
            $stmt->bind_param("i", $producto_id);
            $stmt->execute();

            $producto = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$producto) {
                throw new Exception("El producto no existe.");
            }

            // Verificar bodega
            $stmt = $db->prepare(
                "SELECT id FROM bodegas WHERE id = ?"
            );
            $stmt->bind_param("i", $bodega_id);
            $stmt->execute();

            $bodega = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$bodega) {
                throw new Exception("La bodega no existe.");
            }

            // Guardar entrada
            $stmt = $db->prepare(
                "INSERT INTO entradas
                (producto_id, bodega_id, cantidad, fecha, observacion)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "iiiss",
                $producto_id,
                $bodega_id,
                $cantidad,
                $fecha,
                $observacion
            );

            $stmt->execute();
            $stmt->close();

            // Actualizar stock general
            $stmt = $db->prepare(
                "UPDATE productos
                 SET stock = stock + ?
                 WHERE id = ?"
            );

            $stmt->bind_param("ii", $cantidad, $producto_id);
            $stmt->execute();
            $stmt->close();

            // Actualizar inventario por bodega
            $stmt = $db->prepare(
                "INSERT INTO inventario_bodegas
                (producto_id, bodega_id, cantidad)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                cantidad = cantidad + VALUES(cantidad)"
            );

            $stmt->bind_param(
                "iii",
                $producto_id,
                $bodega_id,
                $cantidad
            );

            $stmt->execute();
            $stmt->close();

            $db->commit();

            header("Location: entradas.php?ok=1");
            exit;

        } catch (Throwable $e) {
            $db->rollback();
            $mensaje = "Error al registrar entrada: " .
                       $e->getMessage();
        }
    }
}

// CONSULTAR PRODUCTOS
$productos_lista = [];

$resultado = $db->query(
    "SELECT id, codigo, nombre, stock
     FROM productos
     ORDER BY nombre"
);

while ($fila = $resultado->fetch_assoc()) {
    $productos_lista[] = $fila;
}

// CONSULTAR BODEGAS
$bodegas_lista = [];

$resultado = $db->query(
    "SELECT id, nombre
     FROM bodegas
     ORDER BY nombre"
);

while ($fila = $resultado->fetch_assoc()) {
    $bodegas_lista[] = $fila;
}

// CONSULTAR HISTORIAL
$entradas = [];

$resultado = $db->query(
    "SELECT
        e.id,
        e.fecha,
        e.cantidad,
        e.observacion,
        p.codigo AS producto_codigo,
        p.nombre AS producto_nombre,
        COALESCE(b.nombre, 'Sin bodega') AS bodega_nombre
     FROM entradas e
     INNER JOIN productos p ON e.producto_id = p.id
     LEFT JOIN bodegas b ON e.bodega_id = b.id
     ORDER BY e.id DESC"
);

while ($fila = $resultado->fetch_assoc()) {
    $entradas[] = $fila;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entradas | JECCI</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    background: #f1f4f9;
    color: #263445;
}

.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    background: #101d35;
    color: white;
    padding: 25px 16px;
    overflow-y: auto;
}

.logo {
    text-align: center;
    margin-bottom: 35px;
}

.logo h1 {
    font-size: 29px;
    letter-spacing: 2px;
}

.logo p {
    font-size: 10px;
    color: #9eacc2;
    margin-top: 7px;
}

.menu a {
    display: block;
    padding: 14px;
    margin-bottom: 7px;
    text-decoration: none;
    color: #b8c5d9;
    border-radius: 9px;
}

.menu a:hover,
.menu a.active {
    background: #2463eb;
    color: white;
}

.cerrar {
    margin-top: 30px;
}

.main {
    margin-left: 250px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    gap: 15px;
}

.topbar h2 {
    font-size: 27px;
    color: #14233d;
}

.topbar p {
    color: #8290a5;
    margin-top: 5px;
    font-size: 13px;
}

.admin {
    background: white;
    padding: 12px 18px;
    border-radius: 12px;
}

.panel,
.tabla {
    background: white;
    padding: 23px;
    border-radius: 13px;
    box-shadow: 0 3px 12px #00000009;
    margin-bottom: 23px;
}

.panel h3,
.tabla h3 {
    margin-bottom: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fit,
        minmax(220px, 1fr)
    );
    gap: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 14px;
    color: #475569;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
}

.form-group textarea {
    resize: vertical;
}

.btn {
    background: #2463eb;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    margin-top: 15px;
}

.btn:hover {
    background: #1d4ed8;
}

.mensaje {
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.exito {
    background: #dcfce7;
    color: #166534;
    border-left: 4px solid #16a34a;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #dc2626;
}

.tabla-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th {
    background: #f5f7fb;
    color: #64748b;
    font-size: 12px;
    padding: 16px 12px;
    text-align: left;
}

td {
    padding: 15px 12px;
    border-bottom: 1px solid #edf0f5;
    font-size: 14px;
}

.cantidad {
    color: #16a34a;
    font-weight: bold;
}

@media(max-width: 800px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .menu {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
    }

    .main {
        margin: 0;
        padding: 18px;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>

<body>

<aside class="sidebar">

    <div class="logo">
        <h1>⚙ JECCI</h1>
        <p>SOLUCIONES INDUSTRIALES</p>
    </div>

    <nav class="menu">
        <a href="dashboard.php">🏠 Inicio</a>
        <a href="productos.php">📦 Productos</a>
        <a href="entradas.php" class="active">➕ Entradas</a>
        <a href="salidas.php">➖ Salidas</a>
        <a href="bodegas.php">🏭 Bodegas</a>
        <a href="proveedores.php">🚚 Proveedores</a>
        <a href="reportes.php">📊 Reportes</a>
        <a href="alertas.php">⚠ Alertas</a>
        <a href="usuarios.php">👥 Usuarios</a>
        <a href="index.php" class="cerrar">↩ Cerrar sesión</a>
    </nav>

</aside>

<main class="main">

<header class="topbar">
    <div>
        <h2>➕ Registro de Entradas</h2>
        <p>Control de productos que ingresan a las bodegas JECCI.</p>
    </div>

    <div class="admin">👤 Administrador</div>
</header>

<?php if (isset($_GET["ok"]) && $_GET["ok"] === "1"): ?>
    <div class="mensaje exito">
        ✅ Entrada registrada correctamente.
        El stock general y el de la bodega fueron actualizados.
    </div>
<?php endif; ?>

<?php if ($mensaje): ?>
    <div class="mensaje error">
        <?= escapar($mensaje) ?>
    </div>
<?php endif; ?>

<section class="panel">

    <h3>📝 Registrar nueva entrada</h3>

    <form method="POST">

        <div class="form-grid">

            <div class="form-group">
                <label for="producto_id">Producto *</label>

                <select name="producto_id" id="producto_id" required>
                    <option value="">Selecciona un producto</option>

                    <?php foreach ($productos_lista as $p): ?>
                        <option value="<?= (int)$p["id"] ?>">
                            <?= escapar($p["codigo"]) ?>
                            -
                            <?= escapar($p["nombre"]) ?>
                            (Stock: <?= (int)$p["stock"] ?>)
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label for="bodega_id">Bodega de destino *</label>

                <select name="bodega_id" id="bodega_id" required>
                    <option value="">Selecciona una bodega</option>

                    <?php foreach ($bodegas_lista as $b): ?>
                        <option value="<?= (int)$b["id"] ?>">
                            <?= escapar($b["nombre"]) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label for="cantidad">Cantidad a ingresar *</label>

                <input
                    type="number"
                    name="cantidad"
                    id="cantidad"
                    min="1"
                    step="1"
                    required
                    placeholder="Ej: 50"
                >
            </div>

            <div class="form-group">
                <label for="fecha">Fecha *</label>

                <input
                    type="date"
                    name="fecha"
                    id="fecha"
                    value="<?= date("Y-m-d") ?>"
                    required
                >
            </div>

        </div>

        <div class="form-group" style="margin-top:15px;">
            <label for="observacion">Observaciones</label>

            <textarea
                name="observacion"
                id="observacion"
                rows="3"
                placeholder="Ej: Compra de materiales, factura, proveedor..."
            ></textarea>
        </div>

        <button
            type="submit"
            name="guardar"
            value="1"
            class="btn"
        >
            ✅ Registrar Entrada
        </button>

    </form>

</section>

<section class="tabla">

    <h3>📋 Historial de Entradas</h3>

    <?php if (empty($entradas)): ?>

        <p style="color:#64748b;">
            Todavía no hay entradas registradas.
        </p>

    <?php else: ?>

        <div class="tabla-scroll">

            <table>

                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Bodega</th>
                        <th>Cantidad</th>
                        <th>Observación</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($entradas as $e): ?>

                        <tr>
                            <td><?= escapar($e["fecha"]) ?></td>

                            <td>
                                <strong>
                                    <?= escapar($e["producto_codigo"]) ?>
                                </strong>
                            </td>

                            <td>
                                <?= escapar($e["producto_nombre"]) ?>
                            </td>

                            <td>
                                <?= escapar($e["bodega_nombre"]) ?>
                            </td>

                            <td class="cantidad">
                                +<?= (int)$e["cantidad"] ?>
                            </td>

                            <td>
                                <?= escapar($e["observacion"] ?: "—") ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

</main>

</body>
</html>
