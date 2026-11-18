<?php
$paginaActual = basename($_SERVER['SCRIPT_NAME']);

$opciones = [
    ['index.php', '🏠', 'Inicio'],
    ['productos.php', '📦', 'Productos'],
    ['bodegas.php', '🏬', 'Bodegas'],
    ['entradas.php', '➕', 'Entradas'],
    ['salidas.php', '➖', 'Salidas'],
    ['transferencias.php', '🔄', 'Transferencias'],
    ['proveedores.php', '🚚', 'Proveedores'],
    ['reportes.php', '📊', 'Reportes'],
    ['alertas.php', '⚠️', 'Alertas'],
    ['usuarios.php', '👥', 'Usuarios']
];
?>

<style>
.jecci-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 240px;
    height: 100vh;
    background: #101d35;
    color: white;
    padding: 22px 12px;
    overflow-y: auto;
    z-index: 1000;
    font-family: 'Segoe UI', Arial, sans-serif;
    box-sizing: border-box;
}

.jecci-sidebar * {
    box-sizing: border-box;
}

.jecci-logo {
    text-align: center;
    margin-bottom: 30px;
}

.jecci-logo h2 {
    font-size: 27px;
    color: white;
    margin: 0;
}

.jecci-logo p {
    font-size: 10px;
    color: #9eacc2;
    margin-top: 7px;
    letter-spacing: 1px;
}

.jecci-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    margin-bottom: 5px;
    border-radius: 8px;
    color: #cbd5e1;
    text-decoration: none;
    font-size: 14px;
    transition: background .2s;
}

.jecci-menu a:hover,
.jecci-menu a.activo {
    background: #2563eb;
    color: white;
}

.jecci-salir {
    margin-top: 22px;
    border-top: 1px solid #334155;
    padding-top: 15px;
}

@media (max-width: 768px) {
    .jecci-sidebar {
        width: 190px;
    }
}
</style>

<aside class="jecci-sidebar">
    <div class="jecci-logo">
        <h2>⚙ JECCI</h2>
        <p>SOLUCIONES INDUSTRIALES</p>
    </div>

    <nav class="jecci-menu">
        <?php foreach ($opciones as $opcion): ?>
            <a href="<?= htmlspecialchars($opcion[0]) ?>"
               class="<?= $paginaActual === $opcion[0] ? 'activo' : '' ?>">
                <span><?= $opcion[1] ?></span>
                <span><?= htmlspecialchars($opcion[2]) ?></span>
            </a>
        <?php endforeach; ?>

        <div class="jecci-salir">
            <a href="logout.php">
                <span>↩</span>
                <span>Cerrar sesión</span>
            </a>
        </div>
    </nav>
</aside>