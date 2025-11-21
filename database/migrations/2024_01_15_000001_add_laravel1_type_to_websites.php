<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE websites MODIFY COLUMN type ENUM('html', 'wordpress', 'laravel1') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE websites MODIFY COLUMN type ENUM('html', 'wordpress') NOT NULL");
    }
};
