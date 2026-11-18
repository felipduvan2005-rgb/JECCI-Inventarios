<!DOCTYPE html>
<html lang="es">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Productos | JECCI</title>

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
    color: white;
    padding: 25px 16px;
    overflow-y: auto;
}

.logo {
    text-align: center;
    margin-bottom: 35px;
}

.logo h1 {
    font-size: 30px;
    margin-bottom: 5px;
}

.logo p {
    font-size: 11px;
    color: #9eacc2;
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
    color: #101d35;
    margin-top: 0;
}

.subtitulo {
    color: #64748b;
    margin-bottom: 25px;
}

.resumen {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.indicador,
.tarjeta {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
}

.indicador small {
    color: #64748b;
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

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
}

button,
.boton {
    display: inline-block;
    border: none;
    padding: 11px 16px;
    border-radius: 8px;
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

.acciones {
    display: flex;
    gap: 7px;
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

th, td {
    padding: 13px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

th {
    background: #f8fafc;
}

.estado {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 12px;
}

.normal {
    background: #dcfce7;
    color: #166534;
}

.bajo {
    background: #fef3c7;
    color: #92400e;
}

.agotado {
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
    margin-bottom: 20px;
}

.buscador input {
    max-width: 400px;
}

.nota {
    padding: 13px;
    background: #eff6ff;
    border-radius: 8px;
    color: #1d4ed8;
    font-size: 13px;
    margin-top: 15px;
}

@media (max-width: 850px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }

    .contenido {
        margin-left: 0;
        padding: 16px;
    }

    .resumen,
    .formulario {
        grid-template-columns: 1fr;
    }

    .buscador {
        flex-wrap: wrap;
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
        <a href="dashboard.php">🏠 Panel principal</a>
        <a href="productos.php" class="activo">📦 Productos</a>
        <a href="proveedores.php">🚚 Proveedores</a>
        <a href="bodegas.php">🏬 Bodegas</a>
        <a href="entradas.php">➕ Entradas</a>
        <a href="salidas.php">➖ Salidas</a>
        <a href="reportes.php">📊 Reportes</a>
        <a href="alertas.php">⚠️ Alertas</a>
        <a href="usuarios.php">👥 Usuarios</a>
    </nav>

</aside>

<main class="contenido">

    <h2>📦 Gestión de Productos</h2>

    <p class="subtitulo">
        Administra los productos y consulta
        sus existencias reales en las bodegas.
    </p>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje exito">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="mensaje error">
            <?= escapar($error) ?>
        </div>
    <?php endif; ?>

    <section class="resumen">

        <div class="indicador">
            <small>Productos encontrados</small>
            <strong><?= $totalProductos ?></strong>
        </div>

        <div class="indicador">
            <small>Unidades almacenadas</small>
            <strong>
                <?= number_format($totalUnidades, 0, ',', '.') ?>
            </strong>
        </div>

        <div class="indicador">
            <small>Productos con stock bajo</small>
            <strong><?= $stockBajo ?></strong>
        </div>

    </section>

    <section class="tarjeta">

        <h3>
            <?= $editar
                ? "✏️ Editar producto"
                : "➕ Registrar nuevo producto" ?>
        </h3>

        <form method="POST" class="formulario">

            <input type="hidden" name="accion" value="guardar">

            <input
                type="hidden"
                name="id"
                value="<?= (int)($editar["id"] ?? 0) ?>"
            >

            <div class="campo">
                <label>Código del producto *</label>

                <input
                    type="text"
                    name="codigo"
                    required
                    value="<?= escapar($editar["codigo"] ?? "") ?>"
                    placeholder="Ej: P001"
                >
            </div>

            <div class="campo">
                <label>Nombre del producto *</label>

                <input
                    type="text"
                    name="nombre"
                    required
                    value="<?= escapar($editar["nombre"] ?? "") ?>"
                    placeholder="Ej: Tornillos"
                >
            </div>

            <div class="campo">
                <label>Categoría *</label>

                <input
                    type="text"
                    name="categoria"
                    required
                    value="<?= escapar($editar["categoria"] ?? "") ?>"
                    placeholder="Ej: Ferretería"
                >
            </div>

            <div class="campo">
                <label>Precio *</label>

                <input
                    type="number"
                    name="precio"
                    min="0"
                    step="0.01"
                    required
                    value="<?= escapar($editar["precio"] ?? "") ?>"
                    placeholder="Ej: 15000"
                >
            </div>

            <div class="campo completo">

                <button class="guardar" type="submit">
                    <?= $editar
                        ? "Actualizar producto"
                        : "Guardar producto" ?>
                </button>

                <?php if ($editar): ?>
                    <a class="boton cancelar" href="productos.php">
                        Cancelar
                    </a>
                <?php endif; ?>

            </div>

        </form>

        <div class="nota">
            ℹ️ El stock no se ingresa manualmente.
            Se calcula con las existencias registradas
            en las bodegas mediante entradas y salidas.
        </div>

    </section>

    <section class="tarjeta">

        <h3>📋 Productos registrados</h3>

        <form method="GET" class="buscador">

            <input
                type="text"
                name="buscar"
                value="<?= escapar($buscar) ?>"
                placeholder="Buscar código, nombre o categoría"
            >

            <button class="guardar" type="submit">
                🔍 Buscar
            </button>

            <a class="boton cancelar" href="productos.php">
                Limpiar
            </a>

        </form>

        <div class="tabla">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Stock real</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($productos as $producto): ?>

                    <?php
                    $stock = (int)$producto["stock_real"];

                    if ($stock === 0) {
                        $estado = "Agotado";
                        $clase = "agotado";
                    } elseif ($stock <= 10) {
                        $estado = "Stock bajo";
                        $clase = "bajo";
                    } else {
                        $estado = "Disponible";
                        $clase = "normal";
                    }
                    ?>

                    <tr>

                        <td>
                            <?= (int)$producto["id"] ?>
                        </td>

                        <td>
                            <?= escapar($producto["codigo"]) ?>
                        </td>

                        <td>
                            <strong>
                                <?= escapar($producto["nombre"]) ?>
                            </strong>
                        </td>

                        <td>
                            <?= escapar($producto["categoria"]) ?>
                        </td>

                        <td>
                            $<?= number_format(
                                (float)$producto["precio"],
                                2,
                                ',',
                                '.'
                            ) ?>
                        </td>

                        <td>
                            <strong><?= $stock ?></strong>
                        </td>

                        <td>
                            <span class="estado <?= $clase ?>">
                                <?= $estado ?>
                            </span>
                        </td>

                        <td>

                            <div class="acciones">

                                <a
                                    class="boton editar"
                                    href="productos.php?editar=<?= (int)$producto["id"] ?>"
                                >
                                    ✏️ Editar
                                </a>

                                <form
                                    method="POST"
                                    onsubmit="return confirm('¿Eliminar este producto?');"
                                >

                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="eliminar"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$producto["id"] ?>"
                                    >

                                    <button
                                        class="eliminar"
                                        type="submit"
                                    >
                                        🗑 Eliminar
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$productos): ?>

                    <tr>
                        <td colspan="8">
                            No se encontraron productos.
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