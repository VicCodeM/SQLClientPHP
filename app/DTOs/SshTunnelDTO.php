<?php

namespace App\DTOs;

use App\Models\SshTunnel;

readonly class SshTunnelDTO
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $name,
        public string $host,
        public int $port,
        public string $username,
        public string $authType, // 'password' | 'private_key'
        public string $credentials,
        public ?string $passphrase = null,
    ) {}

    /**
     * Create DTO from Eloquent SshTunnel Model.
     */
    public static function fromModel(SshTunnel $tunnel, ?string $decryptedCredentials = null, ?string $decryptedPassphrase = null): self
    {
        return new self(
            id: $tunnel->id,
            workspaceId: $tunnel->workspace_id,
            name: $tunnel->name,
            host: $tunnel->host,
            port: $tunnel->port,
            username: $tunnel->username,
            authType: $tunnel->auth_type,
            credentials: $decryptedCredentials ?? $tunnel->encrypted_credentials,
            passphrase: $decryptedPassphrase ?? $tunnel->passphrase,
        );
    }
}
