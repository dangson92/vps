<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vps_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address');
            $table->string('ssh_user')->default('root');
            $table->integer('ssh_port')->default(22);
            $table->text('ssh_key_path')->nullable();
            $table->string('worker_key')->unique();
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->json('specs')->nullable(); // CPU, RAM, Disk info
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_servers');
    }
};