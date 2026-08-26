<?php

namespace App\Exceptions\Vault;

class DecryptionException extends VaultException
{
    public static function integrityCompromised(): self
    {
        return new self('El paquete cifrado fue alterado o la clave de descifrado no coincide con la firma MAC.');
    }

    public static function invalidPayload(string $reason): self
    {
        return new self("Carga útil no válida para descifrado: {$reason}");
    }
}
