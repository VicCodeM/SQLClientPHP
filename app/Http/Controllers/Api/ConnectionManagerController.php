<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ConnectionConfigDTO;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Connection;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Database\DatabaseDriverManager;
use App\Services\Vault\Contracts\EncryptedVaultContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectionManagerController extends Controller
{
    public function __construct(
        protected EncryptedVaultContract $vault,
        protected DatabaseDriverManager $driverManager,
    ) {}

    /**
     * List all connections.
     */
    public function index(Request $request): JsonResponse
    {
        $connections = Connection::query()
            ->with(['workspace:id,name', 'group:id,name,color'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $connections,
        ]);
    }

    /**
     * Store and encrypt a new database connection.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', 'in:pgsql,mysql,sqlite,sqlcipher,sqlsrv'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string'],
            'environment' => ['nullable', 'in:development,staging,production'],
            'is_read_only' => ['nullable', 'boolean'],
            'color_tag' => ['nullable', 'string', 'max:20'],
        ]);

        /** @var User $user */
        $user = $request->user() ?? User::query()->firstOrFail();
        /** @var Workspace $workspace */
        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'workspace-principal'],
            ['owner_id' => $user->id, 'name' => 'Workspace Principal']
        );

        $encryptedPassword = null;
        if (!empty($validated['password'])) {
            $encryptedPassword = $this->vault->encrypt($validated['password']);
        }

        $connection = Connection::create([
            'workspace_id' => $workspace->id,
            'name' => $validated['name'],
            'driver' => $validated['driver'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? null,
            'database_name' => $validated['database_name'],
            'username' => $validated['username'] ?? null,
            'encrypted_password' => $encryptedPassword,
            'environment' => $validated['environment'] ?? 'development',
            'is_read_only' => $validated['is_read_only'] ?? false,
            'color_tag' => $validated['color_tag'] ?? '#3b82f6',
        ]);

        AuditLog::create([
            'workspace_id' => $workspace->id,
            'connection_id' => $connection->id,
            'user_id' => $user->id,
            'action' => 'CONNECTION_CREATED',
            'details' => ['name' => $connection->name, 'driver' => $connection->driver, 'database' => $connection->database_name],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Conexión creada con éxito.',
            'data' => $connection,
        ], 201);
    }

    /**
     * Test connection credentials without saving.
     */
    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'in:pgsql,mysql,sqlite,sqlcipher,sqlsrv'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer'],
            'database_name' => ['required', 'string'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        $config = new ConnectionConfigDTO(
            id: 'test-temp',
            workspaceId: 'test-ws',
            name: 'Test Connection',
            driver: $validated['driver'],
            host: $validated['host'] ?? null,
            port: $validated['port'] ?? null,
            databaseName: $validated['database_name'],
            username: $validated['username'] ?? null,
            password: $validated['password'] ?? null,
        );

        $driver = $this->driverManager->driver($config);
        $startTime = microtime(true);

        try {
            $driver->connect($config);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success' => true,
                'message' => "¡Conexión exitosa! Latencia: {$latencyMs} ms.",
                'latency_ms' => $latencyMs,
            ]);
        } catch (\Throwable $e) {
            $rawMsg = $e->getMessage();
            $cleanMsg = mb_convert_encoding($rawMsg, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');

            return response()->json([
                'success' => false,
                'message' => 'Fallo al conectar: '.$cleanMsg,
            ], 422);
        } finally {
            $driver->disconnect();
        }
    }

    /**
     * Delete a connection.
     */
    public function destroy(Connection $connection): JsonResponse
    {
        $connection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conexión eliminada correctamente.',
        ]);
    }
}
