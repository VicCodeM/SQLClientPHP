<?php

namespace App\Services\Database\Drivers;

use App\DTOs\ConnectionConfigDTO;
use PDO;
use PDOException;

class SQLCipherDriver extends SQLiteDriver
{
    /**
     * {@inheritdoc}
     */
    public function connect(ConnectionConfigDTO $config): PDO
    {
        $pdo = parent::connect($config);

        $passphrase = $config->password;
        if (!empty($passphrase)) {
            // Escapar comillas simples para la directiva PRAGMA
            $escapedKey = str_replace("'", "''", $passphrase);
            $pdo->exec("PRAGMA key = '{$escapedKey}';");
        }

        if (!empty($config->options['cipher_compatibility'])) {
            $comp = (int) $config->options['cipher_compatibility'];
            $pdo->exec("PRAGMA cipher_compatibility = {$comp};");
        }

        if (!empty($config->options['cipher_page_size'])) {
            $pageSize = (int) $config->options['cipher_page_size'];
            $pdo->exec("PRAGMA cipher_page_size = {$pageSize};");
        }

        if (!empty($config->options['kdf_iter'])) {
            $kdfIter = (int) $config->options['kdf_iter'];
            $pdo->exec("PRAGMA kdf_iter = {$kdfIter};");
        }

        try {
            // Verificación inmediata de autenticación y descifrado de la base de datos
            $pdo->query('SELECT count(*) FROM sqlite_master;');
        } catch (PDOException $e) {
            throw new PDOException('La clave de descifrado SQLCipher es incorrecta o el archivo de base de datos está dañado.', 0, $e);
        }

        return $pdo;
    }
}
