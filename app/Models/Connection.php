<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connection extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'group_id',
        'ssh_tunnel_id',
        'name',
        'driver',
        'host',
        'port',
        'database_name',
        'username',
        'encrypted_password',
        'ssl_options',
        'is_read_only',
        'use_ssh_tunnel',
        'environment',
        'color_tag',
        'options',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'encrypted_password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'is_read_only' => 'boolean',
            'use_ssh_tunnel' => 'boolean',
            'ssl_options' => 'array',
            'options' => 'array',
            'encrypted_password' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<ConnectionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ConnectionGroup::class, 'group_id');
    }

    /**
     * @return BelongsTo<SshTunnel, $this>
     */
    public function sshTunnel(): BelongsTo
    {
        return $this->belongsTo(SshTunnel::class, 'ssh_tunnel_id');
    }

    /**
     * @return HasMany<QueryHistory, $this>
     */
    public function queryHistories(): HasMany
    {
        return $this->hasMany(QueryHistory::class);
    }

    /**
     * @return HasMany<SavedQuery, $this>
     */
    public function savedQueries(): HasMany
    {
        return $this->hasMany(SavedQuery::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
