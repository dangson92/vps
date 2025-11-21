<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('domain');
            $table->enum('type', ['html', 'wordpress']);
            $table->foreignId('vps_server_id')->constrained('vps_servers');
            $table->string('document_root');
            $table->string('status')->default('pending');
            $table->boolean('ssl_enabled')->default(false);
            $table->dateTime('ssl_expires_at')->nullable();
            $table->json('wordpress_config')->nullable();
            $table->json('nginx_config')->nullable();
            $table->timestamps();
            
            $table->unique(['domain', 'vps_server_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};