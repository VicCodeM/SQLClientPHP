<?php

namespace App\DTOs;

use App\Models\Connection;

readonly class ConnectionConfigDTO
{
    /**
     * @param  array<string, mixed>  $sslOptions
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $name,
        public string $driver,
        public ?string $host,
        public ?int $port,
        public string $databaseName,
        public ?string $username,
        public ?string $password,
        public array $sslOptions = [],
        public bool $isReadOnly = false,
        public bool $useSshTunnel = false,
        public string $environment = 'development',
        public ?string $colorTag = null,
        public array $options = [],
        public ?SshTunnelDTO $sshTunnel = null,
    ) {}

    /**
     * Create DTO from Eloquent Connection Model.
     */
    public static function fromModel(Connection $connection, ?string $decryptedPassword = null): self
    {
        $sshTunnelDto = null;
        if ($connection->use_ssh_tunnel && $connection->sshTunnel) {
            $sshTunnelDto = SshTunnelDTO::fromModel($connection->sshTunnel);
        }

        return new self(
            id: $connection->id,
            workspaceId: $connection->workspace_id,
            name: $connection->name,
            driver: $connection->driver,
            host: $connection->host,
            port: $connection->port,
            databaseName: $connection->database_name,
            username: $connection->username,
            password: $decryptedPassword ?? $connection->encrypted_password,
            sslOptions: $connection->ssl_options ?? [],
            isReadOnly: (bool) ($connection->is_read_only ?? false),
            useSshTunnel: (bool) ($connection->use_ssh_tunnel ?? false),
            environment: (string) ($connection->environment ?? 'development'),
            colorTag: $connection->color_tag,
            options: $connection->options ?? [],
            sshTunnel: $sshTunnelDto,
        );
    }
}
