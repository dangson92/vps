<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained('websites');
            $table->integer('visits')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->bigInteger('bandwidth')->default(0); // bytes
            $table->integer('response_time')->nullable(); // milliseconds
            $table->boolean('is_online')->default(true);
            $table->date('date');
            $table->timestamps();
            
            $table->unique(['website_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_stats');
    }
};