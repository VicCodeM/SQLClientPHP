<?php

namespace App\Exceptions\Vault;

class EncryptionException extends VaultException
{
    public static function failedToEncrypt(string $reason): self
    {
        return new self("No fue posible cifrar las credenciales de forma segura: {$reason}");
    }
}
