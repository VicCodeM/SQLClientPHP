<?php

use App\DTOs\Database\ColumnMetadataDTO;
use App\DTOs\Database\ForeignKeyMetadataDTO;
use App\Services\Database\Contracts\DatabaseDriverContract;
use App\Services\Database\DatabaseDriverManager;
use App\Services\Database\Drivers\PostgresDriver;

test('it resolves PostgresDriver through DatabaseDriverManager factory', function () {
    /** @var DatabaseDriverManager $manager */
    $manager = app(DatabaseDriverManager::class);

    $driver = $manager->make('pgsql');
    expect($driver)->toBeInstanceOf(PostgresDriver::class);
    expect($driver)->toBeInstanceOf(DatabaseDriverContract::class);

    $driverAlt = $manager->make('postgresql');
    expect($driverAlt)->toBeInstanceOf(PostgresDriver::class);
});

test('it allows registering and resolving custom community database drivers', function () {
    /** @var DatabaseDriverManager $manager */
    $manager = app(DatabaseDriverManager::class);

    $mockDriver = Mockery::mock(DatabaseDriverContract::class);

    $manager->extend('cockroachdb', fn () => $mockDriver);

    $resolved = $manager->make('cockroachdb');
    expect($resolved)->toBe($mockDriver);
});

test('it throws exception for unsupported database driver names', function () {
    /** @var DatabaseDriverManager $manager */
    $manager = app(DatabaseDriverManager::class);

    expect(fn () => $manager->make('unsupported_db_engine'))
        ->toThrow(InvalidArgumentException::class);
});

test('it verifies postgres DDL reconstruction logic with columns and constraints', function () {
    $driver = new class extends PostgresDriver
    {
        /**
         * @return list<ColumnMetadataDTO>
         */
        public function getTableColumns(string $schema, string $table): array
        {
            return [
                new ColumnMetadataDTO(
                    name: 'id',
                    dataType: 'uuid',
                    fullType: 'uuid',
                    isNullable: false,
                    defaultValue: 'gen_random_uuid()',
                    isPrimaryKey: true,
                    isAutoIncrement: false,
                ),
                new ColumnMetadataDTO(
                    name: 'name',
                    dataType: 'character varying',
                    fullType: 'character varying(255)',
                    isNullable: false,
                    defaultValue: null,
                    isPrimaryKey: false,
                    isAutoIncrement: false,
                ),
                new ColumnMetadataDTO(
                    name: 'role_id',
                    dataType: 'uuid',
                    fullType: 'uuid',
                    isNullable: true,
                    defaultValue: null,
                    isPrimaryKey: false,
                    isAutoIncrement: false,
                ),
            ];
        }

        /**
         * @return list<ForeignKeyMetadataDTO>
         */
        public function getTableForeignKeys(string $schema, string $table): array
        {
            return [
                new ForeignKeyMetadataDTO(
                    name: 'fk_users_role',
                    tableName: $table,
                    columns: ['role_id'],
                    foreignSchema: 'public',
                    foreignTable: 'roles',
                    foreignColumns: ['id'],
                    onUpdate: 'CASCADE',
                    onDelete: 'SET NULL',
                ),
            ];
        }
    };

    $ddl = $driver->getTableDdl('public', 'users');

    expect($ddl)->toContain('CREATE TABLE "public"."users"');
    expect($ddl)->toContain('"id" uuid NOT NULL DEFAULT gen_random_uuid()');
    expect($ddl)->toContain('"name" character varying(255) NOT NULL');
    expect($ddl)->toContain('PRIMARY KEY ("id")');
    expect($ddl)->toContain('CONSTRAINT "fk_users_role" FOREIGN KEY ("role_id") REFERENCES "public"."roles" ("id") ON UPDATE CASCADE ON DELETE SET NULL');
});
