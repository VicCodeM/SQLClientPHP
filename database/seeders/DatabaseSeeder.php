<?php

namespace Database\Seeders;

use App\Models\Connection;
use App\Models\ConnectionGroup;
use App\Models\QueryHistory;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Query\Contracts\QueryExecutionEngineContract;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo workspace, connections and sample tables.
     */
    public function run(): void
    {
        // 1. Create Default Master User
        $user = User::query()->firstOrCreate(
            ['email' => 'admin@sqlclient.local'],
            [
                'name' => 'Victor Administrador',
                'password' => Hash::make('password123'),
                'is_master_admin' => true,
                'is_active' => true,
            ]
        );

        // 2. Create Default Workspace
        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'workspace-principal'],
            [
                'owner_id' => $user->id,
                'name' => 'Entorno Principal de Desarrollo',
                'description' => 'Workspace predeterminado con conexiones de prueba y bases de datos locales.',
            ]
        );

        // 3. Create Connection Groups
        $devGroup = ConnectionGroup::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Desarrollo Local'],
            ['color' => '#10b981', 'sort_order' => 1]
        );

        $prodGroup = ConnectionGroup::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Producción Remota'],
            ['color' => '#ef4444', 'sort_order' => 2]
        );

        // 4. Create SQLite Demo Database File with Sample E-Commerce Data
        $sqliteDemoPath = database_path('demo_ecommerce.sqlite');
        if (!file_exists($sqliteDemoPath)) {
            touch($sqliteDemoPath);
        }

        $connection = Connection::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'E-Commerce Local DB'],
            [
                'group_id' => $devGroup->id,
                'driver' => 'sqlite',
                'database_name' => $sqliteDemoPath,
                'is_read_only' => false,
                'environment' => 'development',
                'color_tag' => '#10b981',
            ]
        );

        // Create Read-Only Production Mirror Connection
        Connection::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Analytics Production (Read-Only)'],
            [
                'group_id' => $prodGroup->id,
                'driver' => 'sqlite',
                'database_name' => $sqliteDemoPath,
                'is_read_only' => true,
                'environment' => 'production',
                'color_tag' => '#ef4444',
            ]
        );

        // Populate the demo SQLite database with tables and records
        /** @var QueryExecutionEngineContract $engine */
        $engine = app(QueryExecutionEngineContract::class);

        $engine->execute($connection, $user, 'CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );');

        $engine->execute($connection, $user, 'CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL,
            name VARCHAR(150) NOT NULL,
            sku VARCHAR(50) UNIQUE NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            stock INTEGER NOT NULL DEFAULT 0,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        );');

        $engine->execute($connection, $user, 'CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            city VARCHAR(100) NOT NULL,
            country VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );');

        $engine->execute($connection, $user, 'CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) DEFAULT \'completed\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        );');

        // Insert sample rows if empty
        $check = $engine->execute($connection, $user, 'SELECT COUNT(*) AS total FROM categories;');
        if (empty($check->rows) || ($check->rows[0]['total'] ?? 0) == 0) {
            $engine->execute($connection, $user, "INSERT INTO categories (name, slug) VALUES 
                ('Electrónica', 'electronica'),
                ('Hogar & Cocina', 'hogar-cocina'),
                ('Videojuegos', 'videojuegos'),
                ('Oficina', 'oficina');");

            $engine->execute($connection, $user, "INSERT INTO products (category_id, name, sku, price, stock) VALUES 
                (1, 'Laptop Pro 16\" M3', 'LAP-001', 2499.99, 15),
                (1, 'Monitor Curvo 34\" 144Hz', 'MON-002', 499.50, 28),
                (1, 'Teclado Mecánico RGB Wireless', 'KEY-003', 129.90, 60),
                (2, 'Cafetera Espresso Automática', 'CAF-004', 349.00, 10),
                (3, 'Consola de Juegos Next-Gen', 'CON-005', 599.99, 20),
                (4, 'Silla Ergonómica Pro', 'SIL-006', 289.00, 12);");

            $engine->execute($connection, $user, "INSERT INTO customers (full_name, email, city, country) VALUES 
                ('Carlos Mendoza', 'carlos@empresa.com', 'Madrid', 'España'),
                ('Lucía Fernández', 'lucia.f@gmail.com', 'Buenos Aires', 'Argentina'),
                ('Santiago Morales', 'santiago@tech.co', 'Bogotá', 'Colombia'),
                ('Mariana Gómez', 'mariana@innovate.mx', 'Ciudad de México', 'México');");

            $engine->execute($connection, $user, "INSERT INTO orders (customer_id, order_number, total_amount, status) VALUES 
                (1, 'ORD-2026-001', 2999.49, 'completed'),
                (2, 'ORD-2026-002', 499.50, 'completed'),
                (3, 'ORD-2026-003', 888.99, 'processing'),
                (4, 'ORD-2026-004', 129.90, 'completed');");
        }

        // 5. Create Sample Saved Queries / Snippets
        SavedQuery::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'title' => 'Top Clientes con Mayor Volumen de Compra'],
            [
                'user_id' => $user->id,
                'connection_id' => $connection->id,
                'description' => 'Agrupa las órdenes por cliente calculando el gasto total y la cantidad de compras.',
                'query_text' => "SELECT c.id, c.full_name, c.country, COUNT(o.id) AS total_ordenes, SUM(o.total_amount) AS total_gastado\nFROM customers c\nJOIN orders o ON o.customer_id = c.id\nGROUP BY c.id, c.full_name, c.country\nORDER BY total_gastado DESC;",
                'tags' => ['ventas', 'clientes', 'reportes', 'analytics'],
                'is_shared' => true,
            ]
        );

        SavedQuery::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'title' => 'Inventario de Productos con Stock Crítico'],
            [
                'user_id' => $user->id,
                'connection_id' => $connection->id,
                'description' => 'Filtra los productos activos cuyo inventario sea menor a 15 unidades.',
                'query_text' => "SELECT p.sku, p.name, c.name AS categoria, p.price, p.stock\nFROM products p\nJOIN categories c ON c.id = p.category_id\nWHERE p.stock < 15 AND p.is_active = 1\nORDER BY p.stock ASC;",
                'tags' => ['inventario', 'alerta', 'productos'],
                'is_shared' => true,
            ]
        );

        // 6. Create Initial Query History Entry
        QueryHistory::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'query_text' => 'SELECT * FROM products ORDER BY price DESC;'],
            [
                'connection_id' => $connection->id,
                'user_id' => $user->id,
                'database_name' => $connection->database_name,
                'duration_ms' => 3,
                'affected_rows' => 6,
                'status' => 'success',
                'executed_at' => now(),
            ]
        );
    }
}
