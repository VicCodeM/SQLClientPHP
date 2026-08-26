<?php

use App\DTOs\ConnectionConfigDTO;
use App\Services\Database\Contracts\DatabaseDriverContract;
use App\Services\Database\DatabaseDriverManager;
use App\Services\Database\Drivers\MySQLDriver;
use App\Services\Database\Drivers\SQLCipherDriver;
use App\Services\Database\Drivers\SQLiteDriver;

test('it resolves all secondary drivers through DatabaseDriverManager', function () {
    /** @var DatabaseDriverManager $manager */
    $manager = app(DatabaseDriverManager::class);

    expect($manager->make('mysql'))->toBeInstanceOf(MySQLDriver::class);
    expect($manager->make('mariadb'))->toBeInstanceOf(MySQLDriver::class);
    expect($manager->make('sqlite'))->toBeInstanceOf(SQLiteDriver::class);
    expect($manager->make('sqlcipher'))->toBeInstanceOf(SQLCipherDriver::class);
});

test('it performs full schema introspection and query execution using SQLiteDriver on in-memory database', function () {
    $driver = new SQLiteDriver;

    $config = new ConnectionConfigDTO(
        id: 'test-sqlite-uuid',
        workspaceId: 'ws-uuid',
        name: 'In-Memory Test DB',
        driver: 'sqlite',
        host: null,
        port: null,
        databaseName: ':memory:',
        username: null,
        password: null,
    );

    $pdo = $driver->connect($config);
    expect($pdo)->toBeInstanceOf(PDO::class);

    // Create test tables and relationships
    $pdo->exec('CREATE TABLE categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT UNIQUE
    );');

    $pdo->exec('CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        price REAL NOT NULL DEFAULT 0.0,
        FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
    );');

    $pdo->exec('CREATE INDEX idx_products_category ON products (category_id);');

    $pdo->exec("INSERT INTO categories (name, slug) VALUES ('Electronics', 'electronics');");
    $pdo->exec("INSERT INTO products (category_id, title, price) VALUES (1, 'Mechanical Keyboard', 129.99);");

    // Introspection assertions
    $tables = $driver->getTables('main');
    $tableNames = array_map(fn ($t) => $t->name, $tables);
    expect($tableNames)->toContain('categories', 'products');

    $columns = $driver->getTableColumns('main', 'products');
    $colNames = array_map(fn ($c) => $c->name, $columns);
    expect($colNames)->toContain('id', 'category_id', 'title', 'price');

    $idCol = collect($columns)->firstWhere('name', 'id');
    expect($idCol?->isPrimaryKey)->toBeTrue();
    expect($idCol?->isAutoIncrement)->toBeTrue();

    $fks = $driver->getTableForeignKeys('main', 'products');
    expect($fks)->toHaveCount(1);
    expect($fks[0]->foreignTable)->toBe('categories');
    expect($fks[0]->onDelete)->toBe('CASCADE');

    $indexes = $driver->getTableIndexes('main', 'products');
    $idxNames = array_map(fn ($i) => $i->name, $indexes);
    expect($idxNames)->toContain('idx_products_category');

    $ddl = $driver->getTableDdl('main', 'products');
    expect($ddl)->toContain('CREATE TABLE products');

    // Query Execution
    $result = $driver->executeQuery('SELECT p.title, p.price, c.name as category FROM products p JOIN categories c ON c.id = p.category_id WHERE p.price > :min_price', [
        'min_price' => 50.0,
    ]);

    expect($result->isSelect)->toBeTrue();
    expect($result->affectedRows)->toBe(1);
    expect($result->rows[0]['title'])->toBe('Mechanical Keyboard');
    expect($result->rows[0]['category'])->toBe('Electronics');

    // Explain query
    $explain = $driver->explainQuery('SELECT * FROM products WHERE category_id = 1');
    expect($explain->format)->toBe('text');
    expect($explain->rawOutput)->not->toBeEmpty();
});

test('it verifies SQLCipher driver inherits from SQLiteDriver and configures cipher options', function () {
    $driver = new SQLCipherDriver;
    expect($driver)->toBeInstanceOf(SQLiteDriver::class);
    expect($driver)->toBeInstanceOf(DatabaseDriverContract::class);

    $config = new ConnectionConfigDTO(
        id: 'test-cipher-uuid',
        workspaceId: 'ws-uuid',
        name: 'Encrypted Vault SQLite',
        driver: 'sqlcipher',
        host: null,
        port: null,
        databaseName: ':memory:',
        username: null,
        password: 'SuperSecretPassphrase2026',
        options: [
            'cipher_compatibility' => 4,
            'cipher_page_size' => 4096,
            'kdf_iter' => 256000,
        ],
    );

    $pdo = $driver->connect($config);
    expect($pdo)->toBeInstanceOf(PDO::class);

    $result = $driver->executeQuery('SELECT 1 + 1 AS solution');
    expect($result->rows[0]['solution'])->toBe(2);
});
