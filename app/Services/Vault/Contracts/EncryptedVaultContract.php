<?php

namespace App\Services\Vault\Contracts;

use App\DTOs\ConnectionConfigDTO;
use App\Models\Connection;
use App\Models\SshTunnel;

interface EncryptedVaultContract
{
    /**
     * Encrypt a sensitive plaintext string with authenticated AES-256-GCM.
     */
    public function encrypt(string $plainText, ?string $additionalData = null): string;

    /**
     * Decrypt an authenticated AES-256-GCM ciphertext payload.
     */
    public function decrypt(string $payload, ?string $additionalData = null): string;

    /**
     * Resolve and return fully decrypted ConnectionConfigDTO from Connection model.
     */
    public function resolveConnectionConfig(Connection $connection): ConnectionConfigDTO;

    /**
     * Securely store or update a connection with encrypted credentials.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function storeConnection(array $attributes): Connection;

    /**
     * Securely store or update an SSH Tunnel with encrypted credentials.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function storeSshTunnel(array $attributes): SshTunnel;
}
