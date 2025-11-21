<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_tokens')) {
            Schema::create('admin_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('token_hash', 128)->unique();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->boolean('revoked')->default(false);
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();
                $table->index(['revoked']);
                $table->index(['expires_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_tokens')) {
            Schema::dropIfExists('admin_tokens');
        }
    }
};