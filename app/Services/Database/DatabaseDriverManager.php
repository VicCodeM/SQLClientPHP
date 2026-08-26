<?php

namespace App\Services\Database;

use App\DTOs\ConnectionConfigDTO;
use App\Services\Database\Contracts\DatabaseDriverContract;
use App\Services\Database\Drivers\PostgresDriver;
use Closure;
use InvalidArgumentException;

class DatabaseDriverManager
{
    /**
     * @var array<string, Closure(): DatabaseDriverContract>
     */
    protected array $customDrivers = [];

    /**
     * Resolve driver instance based on connection configuration DTO.
     */
    public function driver(ConnectionConfigDTO $config): DatabaseDriverContract
    {
        $driverName = strtolower($config->driver);

        if (isset($this->customDrivers[$driverName])) {
            $driver = ($this->customDrivers[$driverName])();
            $driver->connect($config);

            return $driver;
        }

        $driver = match ($driverName) {
            'pgsql', 'postgres', 'postgresql' => new PostgresDriver,
            default => throw new InvalidArgumentException("El motor de base de datos '{$config->driver}' no está soportado o registrado."),
        };

        $driver->connect($config);

        return $driver;
    }

    /**
     * Create an un-connected driver instance for inspection or testing.
     */
    public function make(string $driverName): DatabaseDriverContract
    {
        $normalized = strtolower($driverName);

        if (isset($this->customDrivers[$normalized])) {
            return ($this->customDrivers[$normalized])();
        }

        return match ($normalized) {
            'pgsql', 'postgres', 'postgresql' => new PostgresDriver,
            default => throw new InvalidArgumentException("El motor de base de datos '{$driverName}' no está soportado o registrado."),
        };
    }

    /**
     * Register a custom or community driver.
     *
     * @param  Closure(): DatabaseDriverContract  $callback
     */
    public function extend(string $driverName, Closure $callback): void
    {
        $this->customDrivers[strtolower($driverName)] = $callback;
    }
}
