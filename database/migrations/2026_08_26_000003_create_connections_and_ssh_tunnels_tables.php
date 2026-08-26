<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ssh_tunnels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(22);
            $table->string('username');
            $table->string('auth_type')->default('password'); // password, private_key
            $table->text('encrypted_credentials');
            $table->text('passphrase')->nullable();
            $table->timestamps();
        });

        Schema::create('connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('group_id')->nullable()->constrained('connection_groups')->nullOnDelete();
            $table->foreignUuid('ssh_tunnel_id')->nullable()->constrained('ssh_tunnels')->nullOnDelete();
            $table->string('name');
            $table->string('driver'); // pgsql, mysql, sqlite, sqlcipher, sqlsrv
            $table->string('host')->nullable(); // nullable for sqlite/sqlcipher
            $table->integer('port')->nullable();
            $table->string('database_name');
            $table->string('username')->nullable();
            $table->text('encrypted_password')->nullable();
            $table->json('ssl_options')->nullable();
            $table->boolean('is_read_only')->default(false);
            $table->boolean('use_ssh_tunnel')->default(false);
            $table->string('environment')->default('development'); // development, staging, production
            $table->string('color_tag', 20)->nullable();
            $table->json('options')->nullable(); // cipher keys, pragmas, search_paths, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connections');
        Schema::dropIfExists('ssh_tunnels');
    }
};
