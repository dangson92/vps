<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained('websites');
            $table->string('path');
            $table->string('filename');
            $table->text('content');
            $table->string('title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();
            
            $table->unique(['website_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};