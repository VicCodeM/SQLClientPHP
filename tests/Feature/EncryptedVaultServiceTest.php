<?php

use App\DTOs\ConnectionConfigDTO;
use App\Exceptions\Vault\DecryptionException;
use App\Models\Connection;
use App\Models\SshTunnel;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Vault\Contracts\EncryptedVaultContract;

test('it encrypts and decrypts sensitive data using authenticated AES-256-GCM', function () {
    /** @var EncryptedVaultContract $vault */
    $vault = app(EncryptedVaultContract::class);

    $originalSecret = 'My_Super_Secret_Postgres_Passphrase_!@#$2026';
    $encrypted = $vault->encrypt($originalSecret);

    expect($encrypted)->not->toBe($originalSecret);
    expect($encrypted)->toBeString();

    $decrypted = $vault->decrypt($encrypted);
    expect($decrypted)->toBe($originalSecret);
});

test('it validates additional authenticated data AAD for tampering prevention', function () {
    /** @var EncryptedVaultContract $vault */
    $vault = app(EncryptedVaultContract::class);

    $secret = 'Confidential_Database_Password';
    $aad = 'tenant-workspace-uuid-1234';

    $encrypted = $vault->encrypt($secret, $aad);

    // Decrypting with identical AAD succeeds
    expect($vault->decrypt($encrypted, $aad))->toBe($secret);

    // Decrypting with wrong AAD fails integrity check
    expect(fn () => $vault->decrypt($encrypted, 'wrong-tenant-uuid'))
        ->toThrow(DecryptionException::class);
});

test('it throws DecryptionException when ciphertext or tag is tampered with', function () {
    /** @var EncryptedVaultContract $vault */
    $vault = app(EncryptedVaultContract::class);

    $encrypted = $vault->encrypt('Sensitive_Token');
    $decoded = json_decode((string) base64_decode($encrypted, true), true);

    // Corrupt the binary data
    $decoded['data'] = base64_encode('corrupted_payload');
    $corruptedPayload = base64_encode((string) json_encode($decoded));

    expect(fn () => $vault->decrypt($corruptedPayload))
        ->toThrow(DecryptionException::class);
});

test('it resolves ConnectionConfigDTO from Connection model with decrypted credentials and SSH Tunnel', function () {
    /** @var EncryptedVaultContract $vault */
    $vault = app(EncryptedVaultContract::class);

    $user = User::create([
        'name' => 'SecOps DBA',
        'email' => 'secops@example.com',
        'password' => 'password123',
    ]);

    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => 'Fintech Secure Workspace',
        'slug' => 'fintech-secure',
    ]);

    $sshTunnel = SshTunnel::create([
        'workspace_id' => $workspace->id,
        'name' => 'AWS Bastion Tunnel',
        'host' => 'bastion.fintech.internal',
        'port' => 22022,
        'username' => 'ec2-user',
        'auth_type' => 'private_key',
        'encrypted_credentials' => '-----BEGIN RSA PRIVATE KEY-----\nMIIEowIBAAKCAQEA0...\n-----END RSA PRIVATE KEY-----',
        'passphrase' => 'bastionKeyPassphrase123',
    ]);

    $connection = Connection::create([
        'workspace_id' => $workspace->id,
        'ssh_tunnel_id' => $sshTunnel->id,
        'name' => 'Postgres Core Banking DB',
        'driver' => 'pgsql',
        'host' => '10.0.5.100',
        'port' => 5432,
        'database_name' => 'core_banking',
        'username' => 'app_banking_user',
        'encrypted_password' => 'BankMasterPass_2026!',
        'use_ssh_tunnel' => true,
        'is_read_only' => true,
        'environment' => 'production',
        'ssl_options' => ['sslmode' => 'verify-full', 'sslrootcert' => '/certs/ca.pem'],
    ]);

    $dto = $vault->resolveConnectionConfig($connection);

    expect($dto)->toBeInstanceOf(ConnectionConfigDTO::class);
    expect($dto->name)->toBe('Postgres Core Banking DB');
    expect($dto->driver)->toBe('pgsql');
    expect($dto->password)->toBe('BankMasterPass_2026!');
    expect($dto->isReadOnly)->toBeTrue();
    expect($dto->environment)->toBe('production');
    expect($dto->sslOptions['sslmode'])->toBe('verify-full');
    expect($dto->useSshTunnel)->toBeTrue();
    expect($dto->sshTunnel)->not->toBeNull();
    expect($dto->sshTunnel?->username)->toBe('ec2-user');
    expect($dto->sshTunnel?->credentials)->toContain('RSA PRIVATE KEY');
    expect($dto->sshTunnel?->passphrase)->toBe('bastionKeyPassphrase123');
});
