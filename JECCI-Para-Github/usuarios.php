<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . "/config/conexion.php";

$db = isset($conn) ? $conn : $conexion;
$db->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["token_usuarios"])) {
    $_SESSION["token_usuarios"] = bin2hex(random_bytes(32));
}

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function redirigir($mensaje) {
    header("Location: usuarios.php?exito=" . urlencode($mensaje));
    exit;
}

$error = "";
$usuarioEditar = null;
$roles = ["Administrador", "Empleado"];

// PROCESAR FORMULARIOS
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["token_usuarios"],
        $_POST["token"] ?? ""
    )) {
        $error = "Solicitud no válida. Actualiza la página.";
    } else {

        $accion = $_POST["accion"] ?? "";
        $id = (int)($_POST["id"] ?? 0);

        try {

            if ($accion === "guardar" || $accion === "editar") {

                $nombre = trim($_POST["nombre"] ?? "");
                $correo = trim($_POST["correo"] ?? "");
                $rol = trim($_POST["rol"] ?? "");

                if ($nombre === "" || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Escribe un nombre y un correo válido.");
                }

                if (!in_array($rol, $roles, true)) {
                    throw new Exception("Selecciona un rol válido.");
                }

                // EVITAR CORREOS DUPLICADOS
                $stmt = $db->prepare(
                    "SELECT id FROM usuarios
                     WHERE correo = ? AND id <> ?
                     LIMIT 1"
                );
                $stmt->bind_param("si", $correo, $id);
                $stmt->execute();
                $duplicado = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($duplicado) {
                    throw new Exception("Este correo ya está registrado.");
                }

                if ($accion === "guardar") {

                    $stmt = $db->prepare(
                        "INSERT INTO usuarios
                         (nombre, correo, rol, estado)
                         VALUES (?, ?, ?, 'Activo')"
                    );

                    $stmt->bind_param("sss", $nombre, $correo, $rol);
                    $stmt->execute();
                    $stmt->close();

                    redirigir("agregado");

                } else {

                    if ($id <= 0) {
                        throw new Exception("Usuario no válido.");
                    }

                    $stmt = $db->prepare(
                        "UPDATE usuarios
                         SET nombre = ?, correo = ?, rol = ?
                         WHERE id = ?"
                    );

                    $stmt->bind_param("sssi", $nombre, $correo, $rol, $id);
                    $stmt->execute();
                    $stmt->close();

                    redirigir("actualizado");
                }
            }

            if ($accion === "estado") {

                if ($id <= 0) {
                    throw new Exception("Usuario no válido.");
                }

                $stmt = $db->prepare(
                    "SELECT estado FROM usuarios WHERE id = ?"
                );
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $fila = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$fila) {
                    throw new Exception("No se encontró el usuario.");
                }

                $nuevoEstado = $fila["estado"] === "Activo"
                    ? "Inactivo"
                    : "Activo";

                $stmt = $db->prepare(
                    "UPDATE usuarios SET estado = ? WHERE id = ?"
                );
                $stmt->bind_param("si", $nuevoEstado, $id);
                $stmt->execute();
                $stmt->close();

                redirigir("estado");
            }

            if ($accion === "eliminar") {

                if ($id <= 0) {
                    throw new Exception("Usuario no válido.");
                }

                $stmt = $db->prepare(
                    "DELETE FROM usuarios WHERE id = ?"
                );
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();

                redirigir("eliminado");
            }

        } catch (Throwable $ex) {
            $error = "No se pudo completar la operación: " . $ex->getMessage();
        }
    }
}

// CONSULTAR USUARIO PARA EDITAR
if (isset($_GET["editar_usuario"])) {

    $idEditar = (int)$_GET["editar_usuario"];

    if ($idEditar > 0) {

        $stmt = $db->prepare(
            "SELECT id, nombre, correo, rol
             FROM usuarios WHERE id = ?"
        );

        $stmt->bind_param("i", $idEditar);
        $stmt->execute();

        $usuarioEditar = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// CONSULTAR USUARIOS
$usuarios = $db->query(
    "SELECT id, nombre, correo, rol, estado
     FROM usuarios ORDER BY id ASC"
)->fetch_all(MYSQLI_ASSOC);

$total = count($usuarios);
$activos = 0;
$inactivos = 0;

foreach ($usuarios as $usuario) {
    if ($usuario["estado"] === "Activo") {
        $activos++;
    } else {
        $inactivos++;
    }
}

$mensajes = [
    "agregado" => "Usuario registrado correctamente.",
    "actualizado" => "Usuario actualizado correctamente.",
    "estado" => "Estado del usuario actualizado.",
    "eliminado" => "Usuario eliminado correctamente."
];

$exito = $_GET["exito"] ?? "";
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Usuarios | JECCI</title>

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
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: #101d35;
    color: white;
    padding: 25px 16px;
    overflow-y: auto;
}

.logo {
    text-align: center;
    margin-bottom: 35px;
}

.logo h2 {
    font-size: 29px;
    color: white;
}

.logo p {
    color: #9eacc2;
    font-size: 10px;
    margin-top: 7px;
}

.sidebar a {
    display: block;
    color: #b8c5d9;
    padding: 14px;
    margin-bottom: 7px;
    text-decoration: none;
    border-radius: 9px;
}

.sidebar a:hover,
.sidebar a.activo {
    background: #2463eb;
    color: white;
}

.principal {
    margin-left: 250px;
    padding: 30px;
}

.cabecera {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

h1 {
    font-size: 27px;
    color: #14233d;
}

.subtitulo {
    color: #64748b;
    font-size: 14px;
    margin-top: 8px;
}

.admin-tag {
    background: white;
    padding: 13px 20px;
    border-radius: 12px;
}

.cajas {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.caja {
    background: white;
    padding: 23px;
    border-radius: 13px;
    box-shadow: 0 3px 12px #00000009;
}

.caja h3 {
    font-size: 30px;
    color: #2463eb;
    margin-bottom: 7px;
}

.caja p {
    color: #64748b;
    font-size: 14px;
}

.panel {
    background: white;
    padding: 25px;
    border-radius: 13px;
    margin-bottom: 23px;
    box-shadow: 0 3px 12px #00000009;
}

.panel h3 {
    margin-bottom: 23px;
    font-size: 18px;
}

.fila-form {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.campo {
    margin-bottom: 18px;
}

.campo label {
    display: block;
    font-weight: 600;
    margin-bottom: 7px;
    font-size: 14px;
}

.campo input,
.campo select {
    width: 100%;
    padding: 12px;
    border: 1px solid #d6dce5;
    border-radius: 8px;
    font-size: 14px;
    background: white;
}

.btn {
    display: inline-block;
    border: none;
    padding: 11px 17px;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    margin: 3px;
}

.btn-azul {
    background: #2463eb;
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
    padding: 8px 12px;
    font-size: 12px;
}

.tabla {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
}

th {
    background: #f5f7fb;
    color: #64748b;
}

.estado-activo {
    color: #16a34a;
    font-weight: bold;
}

.estado-inactivo {
    color: #dc2626;
    font-weight: bold;
}

.mensaje {
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.exito {
    background: #ecfdf5;
    color: #166534;
}

.error {
    background: #fff1f2;
    color: #9f1239;
}

.acciones {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
}

.acciones form {
    display: inline;
}

@media(max-width: 900px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }

    .principal {
        margin-left: 0;
        padding: 18px;
    }

    .fila-form {
        grid-template-columns: 1fr;
    }

    .cajas {
        grid-template-columns: 1fr;
    }

    .cabecera {
        display: block;
    }

    .admin-tag {
        display: inline-block;
        margin-top: 15px;
    }
}
</style>
</head>

<body>

<?php include __DIR__ . '/menu.php'; ?>

    <div class="logo">
        <h2>⚙ JECCI</h2>
        <p>SOLUCIONES INDUSTRIALES</p>
    </div>

    <a href="dashboard.php">🏠 Inicio</a>
    <a href="productos.php">📦 Productos</a>
    <a href="bodegas.php">🏬 Bodegas</a>
    <a href="entradas.php">➕ Entradas</a>
    <a href="salidas.php">➖ Salidas</a>
    <a href="transferencias.php">🔄 Transferencias</a>
    <a href="proveedores.php">🚚 Proveedores</a>
    <a href="reportes.php">📊 Reportes</a>
    <a href="alertas.php">⚠ Alertas</a>
    <a href="usuarios.php" class="activo">👥 Usuarios</a>
    <a href="index.php">↩ Cerrar sesión</a>

</aside>

<main class="principal">

<header class="cabecera">

    <div>
        <h1>👥 Gestión de Usuarios</h1>
        <p class="subtitulo">
            Registra, edita y administra los usuarios de JECCI.
        </p>
    </div>

    <div class="admin-tag">
        👤 Administrador
    </div>

</header>

<?php if (isset($mensajes[$exito])): ?>
<div class="mensaje exito">
    ✅ <?= escapar($mensajes[$exito]) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mensaje error">
    ⚠️ <?= escapar($error) ?>
</div>
<?php endif; ?>

<div class="cajas">

    <div class="caja">
        <h3><?= $total ?></h3>
        <p>Total de usuarios</p>
    </div>

    <div class="caja">
        <h3><?= $activos ?></h3>
        <p>Usuarios activos</p>
    </div>

    <div class="caja">
        <h3><?= $inactivos ?></h3>
        <p>Usuarios inactivos</p>
    </div>

</div>

<section class="panel" id="formulario">

<h3>
    <?= $usuarioEditar
        ? "✏️ Editar Usuario"
        : "📝 Registrar Nuevo Usuario" ?>
</h3>

<form method="POST" action="usuarios.php">

    <input
        type="hidden"
        name="token"
        value="<?= escapar($_SESSION['token_usuarios']) ?>"
    >

    <input
        type="hidden"
        name="accion"
        value="<?= $usuarioEditar ? 'editar' : 'guardar' ?>"
    >

    <input
        type="hidden"
        name="id"
        value="<?= (int)($usuarioEditar['id'] ?? 0) ?>"
    >

    <div class="fila-form">

        <div class="campo">
            <label>Nombre completo *</label>

            <input
                type="text"
                name="nombre"
                maxlength="150"
                required
                placeholder="Nombre del usuario"
                value="<?= escapar($usuarioEditar['nombre'] ?? '') ?>"
            >
        </div>

        <div class="campo">
            <label>Correo electrónico *</label>

            <input
                type="email"
                name="correo"
                maxlength="190"
                required
                placeholder="correo@ejemplo.com"
                value="<?= escapar($usuarioEditar['correo'] ?? '') ?>"
            >
        </div>

        <div class="campo">
            <label>Rol del usuario *</label>

            <select name="rol" required>

                <option value="Empleado"
                    <?= ($usuarioEditar['rol'] ?? '') === 'Empleado'
                        ? 'selected'
                        : '' ?>>
                    Empleado
                </option>

                <option value="Administrador"
                    <?= ($usuarioEditar['rol'] ?? '') === 'Administrador'
                        ? 'selected'
                        : '' ?>>
                    Administrador
                </option>

            </select>
        </div>

    </div>

    <button type="submit" class="btn btn-azul">
        <?= $usuarioEditar
            ? "✅ Actualizar Usuario"
            : "➕ Registrar Usuario" ?>
    </button>

    <a href="usuarios.php" class="btn btn-gris">
        <?= $usuarioEditar ? "Cancelar edición" : "Limpiar" ?>
    </a>

</form>

</section>

<section class="panel">

<h3>📋 Usuarios Registrados — <?= $total ?> usuario(s)</h3>

<div class="tabla">

<table>

<thead>
<tr>
    <th>ID</th>
    <th>Nombre</th>
    <th>Correo</th>
    <th>Rol</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr>
</thead>

<tbody>

<?php foreach ($usuarios as $u): ?>

<tr>

    <td><?= (int)$u["id"] ?></td>
    <td><?= escapar($u["nombre"]) ?></td>
    <td><?= escapar($u["correo"]) ?></td>
    <td><?= escapar($u["rol"]) ?></td>

    <td>
        <span class="<?= $u["estado"] === "Activo"
            ? "estado-activo"
            : "estado-inactivo" ?>">
            <?= escapar($u["estado"]) ?>
        </span>
    </td>

    <td>

        <div class="acciones">

            <a
                href="usuarios.php?editar_usuario=<?= (int)$u['id'] ?>#formulario"
                class="btn btn-sm btn-azul"
            >
                ✏️ Editar
            </a>

            <form method="POST" action="usuarios.php">

                <input type="hidden" name="token"
                    value="<?= escapar($_SESSION['token_usuarios']) ?>">

                <input type="hidden" name="accion" value="estado">

                <input type="hidden" name="id"
                    value="<?= (int)$u['id'] ?>">

                <button
                    type="submit"
                    class="btn btn-sm btn-gris"
                    onclick="return confirm('¿Cambiar el estado de este usuario?')"
                >
                    🔄 Estado
                </button>

            </form>

            <form method="POST" action="usuarios.php">

                <input type="hidden" name="token"
                    value="<?= escapar($_SESSION['token_usuarios']) ?>">

                <input type="hidden" name="accion" value="eliminar">

                <input type="hidden" name="id"
                    value="<?= (int)$u['id'] ?>">

                <button
                    type="submit"
                    class="btn btn-sm btn-rojo"
                    onclick="return confirm('¿Seguro que deseas eliminar este usuario? Esta acción no se puede deshacer.')"
                >
                    🗑️ Eliminar
                </button>

            </form>

        </div>

    </td>

</tr>

<?php endforeach; ?>

<?php if (empty($usuarios)): ?>
<tr>
    <td colspan="6" style="text-align:center; padding:25px;">
        No hay usuarios registrados.
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