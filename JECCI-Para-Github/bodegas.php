<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . "/config/conexion.php";

$db = $conexion ?? $conn ?? null;

if (!$db instanceof mysqli) {
    die("No se encontró una conexión válida en config/conexion.php");
}

$db->set_charset("utf8mb4");

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["csrf_bodegas"])) {
    $_SESSION["csrf_bodegas"] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION["csrf_bodegas"];
$mensaje = "";
$tipo = "error";
$editar = null;
$detalle = null;
$productosDetalle = [];
$bodegas = [];
$resumen = ["bodegas" => 0, "activas" => 0, "unidades" => 0];
$buscar = trim($_GET["buscar"] ?? "");

try {
    // GUARDAR, ACTUALIZAR, ACTIVAR Y ELIMINAR
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        if (!hash_equals($csrf, (string)($_POST["csrf"] ?? ""))) {
            throw new RuntimeException("Solicitud no válida. Actualiza la página.");
        }

        $accion = $_POST["accion"] ?? "";
        $id = (int)($_POST["id"] ?? 0);

        if ($accion === "guardar") {
            $nombre = trim($_POST["nombre"] ?? "");
            $ubicacion = trim($_POST["ubicacion"] ?? "");
            $descripcion = trim($_POST["descripcion"] ?? "");
            $estado = $_POST["estado"] ?? "Activa";

            if ($nombre === "" || $ubicacion === "") {
                throw new RuntimeException("Nombre y ubicación son obligatorios.");
            }

            if (strlen($nombre) > 100) {
                throw new RuntimeException("El nombre es demasiado largo.");
            }

            if (!in_array($estado, ["Activa", "Inactiva"], true)) {
                throw new RuntimeException("Estado no válido.");
            }

            if ($id > 0) {
                $stmt = $db->prepare(
                    "UPDATE bodegas
                     SET nombre=?, ubicacion=?, descripcion=?, estado=?
                     WHERE id=?"
                );
                $stmt->bind_param(
                    "ssssi",
                    $nombre,
                    $ubicacion,
                    $descripcion,
                    $estado,
                    $id
                );
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO bodegas
                     (nombre, ubicacion, descripcion, estado)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "ssss",
                    $nombre,
                    $ubicacion,
                    $descripcion,
                    $estado
                );
            }

            $stmt->execute();

            header("Location: bodegas.php?mensaje=guardado");
            exit;
        }

        // ACTIVAR O DESACTIVAR
        if ($accion === "estado" && $id > 0) {
            $stmt = $db->prepare(
                "UPDATE bodegas
                 SET estado = IF(estado='Activa','Inactiva','Activa')
                 WHERE id=?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();

            header("Location: bodegas.php?mensaje=estado");
            exit;
        }

        // ELIMINAR BODEGA SIN INVENTARIO
        if ($accion === "eliminar" && $id > 0) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS total
                 FROM inventario_bodegas
                 WHERE bodega_id=?"
            );
            $stmt->bind_param("i", $id);
            $stmt->execute();

            $total = (int)$stmt->get_result()->fetch_assoc()["total"];

            if ($total > 0) {
                throw new RuntimeException(
                    "Esta bodega tiene registros de inventario. Desactívala en lugar de eliminarla."
                );
            }

            $stmt = $db->prepare("DELETE FROM bodegas WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            header("Location: bodegas.php?mensaje=eliminado");
            exit;
        }
    }

    // BUSCAR BODEGA PARA EDITAR
    if (isset($_GET["editar"]) && (int)$_GET["editar"] > 0) {
        $idEditar = (int)$_GET["editar"];

        $stmt = $db->prepare("SELECT * FROM bodegas WHERE id=?");
        $stmt->bind_param("i", $idEditar);
        $stmt->execute();

        $editar = $stmt->get_result()->fetch_assoc();
    }

    // CONSULTAR PRODUCTOS POR BODEGA
    if (isset($_GET["detalle"]) && (int)$_GET["detalle"] > 0) {
        $idDetalle = (int)$_GET["detalle"];

        $stmt = $db->prepare("SELECT * FROM bodegas WHERE id=?");
        $stmt->bind_param("i", $idDetalle);
        $stmt->execute();

        $detalle = $stmt->get_result()->fetch_assoc();

        if ($detalle) {
            $stmt = $db->prepare(
                "SELECT p.nombre AS producto, SUM(ib.cantidad) AS cantidad
                 FROM inventario_bodegas ib
                 INNER JOIN productos p ON p.id = ib.producto_id
                 WHERE ib.bodega_id=?
                 GROUP BY p.id, p.nombre
                 ORDER BY p.nombre"
            );
            $stmt->bind_param("i", $idDetalle);
            $stmt->execute();

            $productosDetalle = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }

    // LISTADO DE BODEGAS CON INVENTARIO REAL
    $filtro = "%" . $buscar . "%";

    $stmt = $db->prepare(
        "SELECT
            b.id,
            b.nombre,
            b.ubicacion,
            b.descripcion,
            b.estado,
            COALESCE(SUM(ib.cantidad), 0) AS unidades,
            COUNT(DISTINCT CASE
                WHEN ib.cantidad > 0 THEN ib.producto_id
            END) AS productos
         FROM bodegas b
         LEFT JOIN inventario_bodegas ib ON ib.bodega_id=b.id
         WHERE b.nombre LIKE ? OR b.ubicacion LIKE ?
         GROUP BY b.id, b.nombre, b.ubicacion, b.descripcion, b.estado
         ORDER BY b.id DESC"
    );

    $stmt->bind_param("ss", $filtro, $filtro);
    $stmt->execute();

    $bodegas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // INDICADORES
    $resumen["bodegas"] = count($bodegas);

    foreach ($bodegas as $b) {
        if ($b["estado"] === "Activa") {
            $resumen["activas"]++;
        }
        $resumen["unidades"] += (int)$b["unidades"];
    }

} catch (Throwable $ex) {
    $mensaje = $ex instanceof mysqli_sql_exception
        ? "Error de base de datos: " . $ex->getMessage()
        : $ex->getMessage();
}

// MENSAJES DE CONFIRMACIÓN
$mensajes = [
    "guardado" => "Bodega guardada correctamente.",
    "estado" => "Estado actualizado correctamente.",
    "eliminado" => "Bodega eliminada correctamente."
];

$codigoMensaje = $_GET["mensaje"] ?? "";

if ($mensaje === "" && isset($mensajes[$codigoMensaje])) {
    $mensaje = $mensajes[$codigoMensaje];
    $tipo = "exito";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Bodegas | JECCI</title>

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

.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: #101d35;
    padding: 25px 16px;
    overflow-y: auto;
}

.logo {
    text-align: center;
    color: white;
    margin-bottom: 30px;
}

.logo h1 {
    font-size: 30px;
    letter-spacing: 2px;
    margin: 0;
}

.logo p {
    color: #9eacc2;
    font-size: 11px;
}

.menu a {
    display: block;
    color: #cbd5e1;
    text-decoration: none;
    padding: 13px 15px;
    border-radius: 8px;
    margin-bottom: 6px;
}

.menu a:hover,
.menu a.activo {
    background: #2563eb;
    color: white;
}

.contenido {
    margin-left: 250px;
    padding: 30px;
}

h2 {
    margin: 0;
    color: #101d35;
}

.subtitulo {
    color: #64748b;
    margin-bottom: 25px;
}

.resumen {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 25px;
}

.indicador,
.tarjeta {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

.indicador span {
    color: #64748b;
    font-size: 14px;
}

.indicador strong {
    display: block;
    font-size: 30px;
    color: #2563eb;
    margin-top: 10px;
}

.tarjeta {
    margin-bottom: 25px;
}

.formulario {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.campo {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.completo {
    grid-column: 1 / -1;
}

label {
    font-weight: 600;
    font-size: 14px;
}

input,
select,
textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font: inherit;
}

button,
.boton {
    display: inline-block;
    border: none;
    border-radius: 7px;
    padding: 11px 14px;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
}

.guardar {
    background: #2563eb;
    color: white;
}

.editar {
    background: #f59e0b;
    color: #111827;
}

.eliminar {
    background: #dc2626;
    color: white;
}

.cancelar {
    background: #64748b;
    color: white;
}

.ver {
    background: #0f766e;
    color: white;
}

.acciones {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.acciones form {
    margin: 0;
}

.tabla {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th,
td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

th {
    background: #f8fafc;
}

.etiqueta {
    display: inline-block;
    border-radius: 20px;
    padding: 6px 10px;
    font-weight: 600;
}

.activa {
    background: #dcfce7;
    color: #166534;
}

.inactiva {
    background: #fee2e2;
    color: #991b1b;
}

.mensaje {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.exito {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.buscador {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.buscador input {
    max-width: 350px;
}

@media (max-width: 850px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }

    .contenido {
        margin-left: 0;
        padding: 18px;
    }

    .resumen,
    .formulario {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="logo">
        <h1>JECCI</h1>
        <p>SISTEMA DE INVENTARIOS</p>
    </div>

    <nav class="menu">
        <a href="dashboard.php">Panel principal</a>
        <a href="productos.php">Productos</a>
        <a href="proveedores.php">Proveedores</a>
        <a href="bodegas.php" class="activo">Bodegas</a>
        <a href="entradas.php">Entradas</a>
        <a href="salidas.php">Salidas</a>
        <a href="reportes.php">Reportes</a>
        <a href="alertas.php">Alertas</a>
        <a href="usuarios.php">Usuarios</a>
    </nav>
</aside>

<main class="contenido">

    <h2>Gestión de Bodegas</h2>
    <p class="subtitulo">
        Administra las bodegas y consulta sus existencias reales.
    </p>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje <?= e($tipo) ?>">
            <?= e($mensaje) ?>
        </div>
    <?php endif; ?>

    <!-- INDICADORES -->
    <section class="resumen">
        <div class="indicador">
            <span>Bodegas encontradas</span>
            <strong><?= (int)$resumen["bodegas"] ?></strong>
        </div>

        <div class="indicador">
            <span>Bodegas activas</span>
            <strong><?= (int)$resumen["activas"] ?></strong>
        </div>

        <div class="indicador">
            <span>Unidades almacenadas</span>
            <strong>
                <?= number_format($resumen["unidades"], 0, ",", ".") ?>
            </strong>
        </div>
    </section>

    <!-- FORMULARIO -->
    <section class="tarjeta">
        <h3>
            <?= $editar ? "Editar bodega" : "Registrar nueva bodega" ?>
        </h3>

        <form method="POST" class="formulario">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id"
                   value="<?= (int)($editar["id"] ?? 0) ?>">

            <div class="campo">
                <label>Nombre de la bodega *</label>
                <input type="text"
                       name="nombre"
                       maxlength="100"
                       required
                       value="<?= e($editar["nombre"] ?? "") ?>"
                       placeholder="Ej: Bodega Principal">
            </div>

            <div class="campo">
                <label>Ubicación *</label>
                <input type="text"
                       name="ubicacion"
                       required
                       value="<?= e($editar["ubicacion"] ?? "") ?>"
                       placeholder="Ej: Cali">
            </div>

            <div class="campo">
                <label>Estado</label>
                <select name="estado">
                    <option value="Activa"
                        <?= ($editar["estado"] ?? "Activa") === "Activa"
                            ? "selected" : "" ?>>
                        Activa
                    </option>
                    <option value="Inactiva"
                        <?= ($editar["estado"] ?? "") === "Inactiva"
                            ? "selected" : "" ?>>
                        Inactiva
                    </option>
                </select>
            </div>

            <div class="campo completo">
                <label>Descripción</label>
                <textarea name="descripcion"
                          rows="3"><?= e($editar["descripcion"] ?? "") ?></textarea>
            </div>

            <div class="campo completo">
                <button type="submit" class="guardar">
                    <?= $editar ? "Actualizar bodega" : "Guardar bodega" ?>
                </button>

                <?php if ($editar): ?>
                    <a href="bodegas.php" class="boton cancelar">
                        Cancelar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- LISTADO -->
    <section class="tarjeta">
        <h3>Bodegas registradas</h3>

        <form method="GET" class="buscador">
            <input type="text"
                   name="buscar"
                   value="<?= e($buscar) ?>"
                   placeholder="Buscar por nombre o ubicación">

            <button type="submit" class="guardar">Buscar</button>

            <a href="bodegas.php" class="boton cancelar">
                Limpiar
            </a>
        </form>

        <div class="tabla">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Ubicación</th>
                        <th>Productos</th>
                        <th>Unidades</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($bodegas as $b): ?>
                    <tr>
                        <td><?= (int)$b["id"] ?></td>
                        <td><?= e($b["nombre"]) ?></td>
                        <td><?= e($b["ubicacion"]) ?></td>
                        <td><?= (int)$b["productos"] ?></td>
                        <td><?= (int)$b["unidades"] ?></td>

                        <td>
                            <span class="etiqueta <?= $b["estado"] === "Activa"
                                ? "activa" : "inactiva" ?>">
                                <?= e($b["estado"]) ?>
                            </span>
                        </td>

                        <td>
                            <div class="acciones">
                                <a class="boton ver"
                                   href="bodegas.php?detalle=<?= (int)$b["id"] ?>#detalle">
                                    Ver productos
                                </a>

                                <a class="boton editar"
                                   href="bodegas.php?editar=<?= (int)$b["id"] ?>">
                                    Editar
                                </a>

                                <form method="POST">
                                    <input type="hidden" name="csrf"
                                           value="<?= e($csrf) ?>">
                                    <input type="hidden" name="accion"
                                           value="estado">
                                    <input type="hidden" name="id"
                                           value="<?= (int)$b["id"] ?>">

                                    <button type="submit" class="ver">
                                        <?= $b["estado"] === "Activa"
                                            ? "Desactivar" : "Activar" ?>
                                    </button>
                                </form>

                                <form method="POST"
                                      onsubmit="return confirm('¿Seguro que deseas eliminar esta bodega?');">
                                    <input type="hidden" name="csrf"
                                           value="<?= e($csrf) ?>">
                                    <input type="hidden" name="accion"
                                           value="eliminar">
                                    <input type="hidden" name="id"
                                           value="<?= (int)$b["id"] ?>">

                                    <button type="submit" class="eliminar">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($bodegas)): ?>
                    <tr>
                        <td colspan="7">
                            No se encontraron bodegas.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- PRODUCTOS DE UNA BODEGA -->
    <?php if ($detalle): ?>
    <section class="tarjeta" id="detalle">
        <h3>Productos de <?= e($detalle["nombre"]) ?></h3>

        <div class="tabla">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($productosDetalle as $producto): ?>
                    <tr>
                        <td><?= e($producto["producto"]) ?></td>
                        <td><?= (int)$producto["cantidad"] ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($productosDetalle)): ?>
                    <tr>
                        <td colspan="2">
                            No hay productos registrados en esta bodega.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p>
            <a href="bodegas.php" class="boton cancelar">
                Cerrar detalle
            </a>
        </p>
    </section>
    <?php endif; ?>

</main>
</body>
</html>