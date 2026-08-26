<?php

namespace App\Exceptions\Query;

class ReadOnlyViolationException extends QueryExecutionException
{
    public static function destructiveOperationBlocked(string $statementType): self
    {
        return new self("Operación destructiva '{$statementType}' bloqueada: Esta conexión se encuentra configurada en modo 'Solo Lectura' (Read-Only).");
    }
}
