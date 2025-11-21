<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained('websites');
            $table->string('record_type'); // A, AAAA, CNAME, MX, TXT
            $table->string('name');
            $table->string('value');
            $table->integer('ttl')->default(300);
            $table->integer('priority')->nullable();
            $table->string('cloudflare_id')->nullable();
            $table->timestamps();
            
            $table->index(['website_id', 'record_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};