<?php
require_once __DIR__ . "/config/conexion.php";

$db = isset($conn) ? $conn : $conexion;
$db->set_charset("utf8mb4");

function escapar($valor) {
    return htmlspecialchars((string)($valor ?? ""), ENT_QUOTES, "UTF-8");
}

function redirigir($mensaje) {
    header("Location: proveedores.php?exito=" . urlencode($mensaje));
    exit;
}

$mensaje = "";
$proveedorEditar = null;

// REGISTRAR Y ACTUALIZAR
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";
    $id = (int)($_POST["id"] ?? 0);
    $nombre = trim($_POST["nombre"] ?? "");
    $nit = trim($_POST["nit"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $direccion = trim($_POST["direccion"] ?? "");

    if (in_array($accion, ["guardar", "editar"], true)) {
        if ($nombre === "") {
            $mensaje = "El nombre del proveedor es obligatorio.";
        } elseif ($correo !== "" && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje = "El correo electrónico no es válido.";
        } else {
            try {
                if ($accion === "guardar") {
                    $stmt = $db->prepare(
                        "INSERT INTO proveedores
                        (nombre, nit, telefono, correo, direccion)
                        VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param(
                        "sssss",
                        $nombre, $nit, $telefono, $correo, $direccion
                    );
                    $stmt->execute();
                    redirigir("agregado");
                }

                if ($accion === "editar") {
                    if ($id <= 0) {
                        throw new Exception("ID del proveedor inválido.");
                    }

                    $stmt = $db->prepare(
                        "UPDATE proveedores
                         SET nombre = ?,
                             nit = ?,
                             telefono = ?,
                             correo = ?,
                             direccion = ?
                         WHERE id = ?"
                    );
                    $stmt->bind_param(
                        "sssssi",
                        $nombre, $nit, $telefono, $correo, $direccion, $id
                    );
                    $stmt->execute();
                    redirigir("actualizado");
                }
            } catch (Throwable $e) {
                $mensaje = "No se pudo guardar: " . $e->getMessage();
            }
        }
    }
}

// ELIMINAR POR POST
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && ($_POST["accion"] ?? "") === "eliminar") {

    $id = (int)($_POST["id"] ?? 0);

    try {
        if ($id <= 0) {
            throw new Exception("ID inválido.");
        }

        $stmt = $db->prepare(
            "DELETE FROM proveedores WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();

        redirigir("eliminado");
    } catch (Throwable $e) {
        $mensaje = "No se pudo eliminar: " . $e->getMessage();
    }
}

// CARGAR PROVEEDOR PARA EDITAR
if (isset($_GET["editar"])) {
    $idEditar = (int)$_GET["editar"];

    if ($idEditar > 0) {
        $stmt = $db->prepare(
            "SELECT id, nombre, nit, telefono, correo, direccion
             FROM proveedores WHERE id = ?"
        );
        $stmt->bind_param("i", $idEditar);
        $stmt->execute();
        $proveedorEditar = $stmt->get_result()->fetch_assoc();

        if (!$proveedorEditar) {
            $mensaje = "No se encontró el proveedor seleccionado.";
        }
    }
}

// INDICADORES REALES
$total = (int)$db->query(
    "SELECT COUNT(*) FROM proveedores"
)->fetch_row()[0];

$activos = (int)$db->query(
    "SELECT COUNT(*) FROM proveedores WHERE estado = 'Activo'"
)->fetch_row()[0];

$registradosMes = (int)$db->query(
    "SELECT COUNT(*) FROM proveedores
     WHERE fecha_registro >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND fecha_registro < DATE_FORMAT(
           CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01'
       )"
)->fetch_row()[0];

// BUSCAR
$buscar = trim($_GET["buscar"] ?? "");

if ($buscar !== "") {
    $parametro = "%" . $buscar . "%";
    $stmt = $db->prepare(
        "SELECT * FROM proveedores
         WHERE nombre LIKE ?
            OR nit LIKE ?
            OR telefono LIKE ?
         ORDER BY id DESC"
    );
    $stmt->bind_param(
        "sss",
        $parametro, $parametro, $parametro
    );
    $stmt->execute();
    $proveedores = $stmt->get_result();
} else {
    $proveedores = $db->query(
        "SELECT * FROM proveedores ORDER BY id DESC"
    );
}

$mensajesExito = [
    "agregado" => "Proveedor registrado correctamente.",
    "actualizado" => "Proveedor actualizado correctamente.",
    "eliminado" => "Proveedor eliminado correctamente."
];

$exito = $_GET["exito"] ?? "";
$mostrarFormulario = $proveedorEditar !== null
    || ($_GET["nuevo"] ?? "") === "1"
    || $mensaje !== "";
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proveedores | JECCI</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    background: #eef2f7;
    color: #1e293b;
}

.principal {
    margin-left: 250px;
    padding: 30px;
}

h1 {
    font-size: 26px;
    margin-bottom: 6px;
}

.subtitulo {
    color: #64748b;
    margin-bottom: 25px;
}

.admin-tag {
    float: right;
    background: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
}

.cajas {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.caja {
    background: white;
    padding: 24px;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,.04);
}

.caja h3 {
    color: #2563eb;
    font-size: 30px;
    margin-bottom: 7px;
}

.caja p {
    color: #64748b;
    font-size: 14px;
}

.arriba {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.buscar-input {
    padding: 11px;
    width: 270px;
    max-width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

.btn {
    display: inline-block;
    border: none;
    padding: 10px 15px;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.btn-azul {
    background: #2563eb;
    color: white;
}

.btn-verde {
    background: #16a34a;
    color: white;
}

.btn-rojo {
    background: #ef4444;
    color: white;
}

.btn-gris {
    background: #e5e7eb;
    color: #374151;
}

.btn-sm {
    padding: 7px 11px;
}

.form-cuadro, .tabla-cuadro {
    background: white;
    padding: 23px;
    border-radius: 10px;
    margin-bottom: 25px;
}

.form-cuadro h3 {
    margin-bottom: 20px;
}

.fila-form {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

.campo label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 600;
}

.campo input {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

.campo input:focus {
    outline: 2px solid #bfdbfe;
    border-color: #2563eb;
}

.tabla-cuadro {
    overflow-x: auto;
}

.tabla-titulo {
    font-size: 17px;
    font-weight: 600;
    margin-bottom: 18px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    text-align: left;
    padding: 13px 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
}

th {
    background: #f8fafc;
    color: #64748b;
}

.acciones {
    display: flex;
    gap: 6px;
}

.mensaje {
    padding: 14px;
    border-radius: 7px;
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

@media(max-width: 900px) {
    .cajas, .fila-form {
        grid-template-columns: 1fr;
    }

    .principal {
        margin-left: 200px;
        padding: 15px;
    }
}
</style>
</head>

<body>

<?php
// Usar el mismo menú compartido de JECCI
include __DIR__ . "/menu.php";
?>

<div class="principal">

    <span class="admin-tag">👤 Administrador</span>

    <h1>🚚 Gestión de Proveedores</h1>
    <p class="subtitulo">
        Registra, edita y administra tus proveedores.
    </p>

    <?php if (isset($mensajesExito[$exito])): ?>
        <div class="mensaje exito">
            ✅ <?= escapar($mensajesExito[$exito]) ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje error">
            ⚠️ <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <div class="cajas">
        <div class="caja">
            <h3><?= $total ?></h3>
            <p>Total de proveedores</p>
        </div>

        <div class="caja">
            <h3><?= $activos ?></h3>
            <p>Proveedores activos</p>
        </div>

        <div class="caja">
            <h3><?= $registradosMes ?></h3>
            <p>Registrados este mes</p>
        </div>
    </div>

    <div class="arriba">

        <form method="GET" action="proveedores.php">
            <input
                type="text"
                name="buscar"
                class="buscar-input"
                placeholder="Buscar nombre, NIT o teléfono"
                value="<?= escapar($buscar) ?>"
            >

            <button type="submit" class="btn btn-gris">
                🔍 Buscar
            </button>

            <a href="proveedores.php" class="btn btn-gris">
                Limpiar
            </a>
        </form>

        <a href="proveedores.php?nuevo=1#formulario"
           class="btn btn-azul">
            ➕ Nuevo Proveedor
        </a>
    </div>

    <?php if ($mostrarFormulario): ?>

    <div class="form-cuadro" id="formulario">

        <h3>
            <?= $proveedorEditar
                ? "✏️ Editar Proveedor"
                : "📝 Registrar Nuevo Proveedor" ?>
        </h3>

        <form method="POST" action="proveedores.php">

            <input
                type="hidden"
                name="accion"
                value="<?= $proveedorEditar ? 'editar' : 'guardar' ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= escapar($proveedorEditar['id'] ?? '') ?>"
            >

            <div class="fila-form">

                <div class="campo">
                    <label>Nombre del proveedor *</label>
                    <input
                        type="text"
                        name="nombre"
                        required
                        value="<?= escapar($proveedorEditar['nombre'] ?? '') ?>"
                    >
                </div>

                <div class="campo">
                    <label>NIT</label>
                    <input
                        type="text"
                        name="nit"
                        value="<?= escapar($proveedorEditar['nit'] ?? '') ?>"
                    >
                </div>

                <div class="campo">
                    <label>Teléfono</label>
                    <input
                        type="text"
                        name="telefono"
                        value="<?= escapar($proveedorEditar['telefono'] ?? '') ?>"
                    >
                </div>
            </div>

            <div class="fila-form">

                <div class="campo">
                    <label>Correo electrónico</label>
                    <input
                        type="email"
                        name="correo"
                        value="<?= escapar($proveedorEditar['correo'] ?? '') ?>"
                    >
                </div>

                <div class="campo">
                    <label>Dirección</label>
                    <input
                        type="text"
                        name="direccion"
                        value="<?= escapar($proveedorEditar['direccion'] ?? '') ?>"
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-verde">
                <?= $proveedorEditar
                    ? "✅ Actualizar Proveedor"
                    : "✅ Guardar Proveedor" ?>
            </button>

            <a href="proveedores.php" class="btn btn-gris">
                Cancelar
            </a>

        </form>
    </div>

    <?php endif; ?>

    <div class="tabla-cuadro">

        <div class="tabla-titulo">
            📋 Proveedores registrados —
            <?= $proveedores->num_rows ?> registro(s)
        </div>

        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>NIT</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Dirección</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($proveedores->num_rows === 0): ?>

                <tr>
                    <td colspan="6">
                        No se encontraron proveedores.
                    </td>
                </tr>

            <?php else: ?>

                <?php while ($p = $proveedores->fetch_assoc()): ?>

                <tr>
                    <td>
                        <strong><?= escapar($p["nombre"]) ?></strong>
                    </td>

                    <td><?= escapar($p["nit"] ?: "—") ?></td>
                    <td><?= escapar($p["telefono"] ?: "—") ?></td>
                    <td><?= escapar($p["correo"] ?: "—") ?></td>
                    <td><?= escapar($p["direccion"] ?: "—") ?></td>

                    <td>
                        <div class="acciones">

                            <a
                                href="proveedores.php?editar=<?= (int)$p['id'] ?>#formulario"
                                class="btn btn-azul btn-sm"
                            >
                                ✏️ Editar
                            </a>

                            <form
                                method="POST"
                                action="proveedores.php"
                                onsubmit="return confirm('¿Seguro que deseas eliminar este proveedor?');"
                            >
                                <input
                                    type="hidden"
                                    name="accion"
                                    value="eliminar"
                                >

                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int)$p['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-rojo btn-sm"
                                >
                                    🗑️ Eliminar
                                </button>
                            </form>

                        </div>
                    </td>
                </tr>

                <?php endwhile; ?>

            <?php endif; ?>

            </tbody>
        </table>

    </div>

</div>

</body>
</html>