<?php

namespace App\Services\Vault;

use App\DTOs\ConnectionConfigDTO;
use App\Exceptions\Vault\DecryptionException;
use App\Exceptions\Vault\EncryptionException;
use App\Models\Connection;
use App\Models\SshTunnel;
use App\Services\Vault\Contracts\EncryptedVaultContract;
use Illuminate\Support\Facades\Config;
use JsonException;

class EncryptedVaultService implements EncryptedVaultContract
{
    private const CIPHER_ALGO = 'aes-256-gcm';

    private const GCM_IV_LENGTH = 12;

    private const GCM_TAG_LENGTH = 16;

    /**
     * Derive a 256-bit symmetric encryption key using HKDF.
     */
    private function deriveKey(): string
    {
        /** @var string|null $rawKey */
        $rawKey = Config::get('app.key');

        if (empty($rawKey)) {
            throw EncryptionException::failedToEncrypt('APP_KEY no está configurada en el entorno.');
        }

        if (str_starts_with($rawKey, 'base64:')) {
            $rawKey = base64_decode(substr($rawKey, 7), true);
            if ($rawKey === false) {
                throw EncryptionException::failedToEncrypt('APP_KEY base64 es inválida.');
            }
        }

        return hash_hkdf('sha256', $rawKey, 32, 'sqlclient-vault-gcm-v1');
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt(string $plainText, ?string $additionalData = null): string
    {
        $key = $this->deriveKey();
        $iv = random_bytes(self::GCM_IV_LENGTH);
        $tag = '';

        $cipherRaw = openssl_encrypt(
            $plainText,
            self::CIPHER_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $additionalData ?? '',
            self::GCM_TAG_LENGTH,
        );

        if ($cipherRaw === false) {
            throw EncryptionException::failedToEncrypt('OpenSSL falló al ejecutar cifrado AES-256-GCM.');
        }

        $payload = [
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipherRaw),
        ];

        return base64_encode((string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * {@inheritdoc}
     */
    public function decrypt(string $payload, ?string $additionalData = null): string
    {
        $decodedJson = base64_decode($payload, true);
        if ($decodedJson === false) {
            throw DecryptionException::invalidPayload('Formato base64 corrupto.');
        }

        try {
            /** @var array{v?: int, iv?: string, tag?: string, data?: string} $envelope */
            $envelope = json_decode($decodedJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw DecryptionException::invalidPayload($e->getMessage());
        }

        if (!isset($envelope['iv'], $envelope['tag'], $envelope['data'])) {
            throw DecryptionException::invalidPayload('Estructura de envelope incompleta.');
        }

        $iv = base64_decode($envelope['iv'], true);
        $tag = base64_decode($envelope['tag'], true);
        $cipherData = base64_decode($envelope['data'], true);

        if ($iv === false || $tag === false || $cipherData === false) {
            throw DecryptionException::invalidPayload('Decodificación de componentes binarios falló.');
        }

        $key = $this->deriveKey();

        $plainText = openssl_decrypt(
            $cipherData,
            self::CIPHER_ALGO,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $additionalData ?? '',
        );

        if ($plainText === false) {
            throw DecryptionException::integrityCompromised();
        }

        return $plainText;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveConnectionConfig(Connection $connection): ConnectionConfigDTO
    {
        $decryptedPassword = null;
        if (!empty($connection->encrypted_password)) {
            $decryptedPassword = $connection->encrypted_password;
        }

        return ConnectionConfigDTO::fromModel($connection, $decryptedPassword);
    }

    /**
     * {@inheritdoc}
     */
    public function storeConnection(array $attributes): Connection
    {
        return Connection::create($attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function storeSshTunnel(array $attributes): SshTunnel
    {
        return SshTunnel::create($attributes);
    }
}
