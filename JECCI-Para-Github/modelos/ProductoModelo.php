<?php

class ProductoModelo
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    public function guardar(
        int $id,
        string $codigo,
        string $nombre,
        string $categoria,
        float $precio
    ): void {

        if ($id > 0) {
            $stmt = $this->db->prepare(
                "UPDATE productos
                 SET codigo = ?,
                     nombre = ?,
                     categoria = ?,
                     precio = ?
                 WHERE id = ?"
            );

            $stmt->bind_param(
                "sssdi",
                $codigo,
                $nombre,
                $categoria,
                $precio,
                $id
            );
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO productos
                 (codigo, nombre, categoria, precio, stock)
                 VALUES (?, ?, ?, ?, 0)"
            );

            $stmt->bind_param(
                "sssd",
                $codigo,
                $nombre,
                $categoria,
                $precio
            );
        }

        $stmt->execute();
        $stmt->close();
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, codigo, nombre, categoria, precio
             FROM productos
             WHERE id = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $producto = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $producto ?: null;
    }

    public function listar(string $buscar = ""): array
    {
        $filtro = "%" . $buscar . "%";

        $stmt = $this->db->prepare(
            "SELECT
                p.id,
                p.codigo,
                p.nombre,
                p.categoria,
                p.precio,
                COALESCE(i.stock_real, 0) AS stock_real
             FROM productos p
             LEFT JOIN (
                SELECT
                    producto_id,
                    SUM(cantidad) AS stock_real
                FROM inventario_bodegas
                GROUP BY producto_id
             ) i ON i.producto_id = p.id
             WHERE p.codigo LIKE ?
                OR p.nombre LIKE ?
                OR p.categoria LIKE ?
             ORDER BY p.id DESC"
        );

        $stmt->bind_param(
            "sss",
            $filtro,
            $filtro,
            $filtro
        );

        $stmt->execute();

        $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $productos;
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM inventario_bodegas
             WHERE producto_id = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $resultado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ((int)$resultado["total"] > 0) {
            return false;
        }

        $stmt = $this->db->prepare(
            "DELETE FROM productos WHERE id = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        return true;
    }
}