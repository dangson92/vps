<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('folders')) {
            Schema::create('folders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('website_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('name', 255);
                $table->string('slug', 255)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->index(['website_id']);
                $table->index(['parent_id']);
            });
        }

        if (!Schema::hasColumn('pages', 'primary_folder_id')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->unsignedBigInteger('primary_folder_id')->nullable()->after('template_data');
                $table->index(['primary_folder_id']);
            });
        }

        if (!Schema::hasTable('folder_page')) {
            Schema::create('folder_page', function (Blueprint $table) {
                $table->unsignedBigInteger('folder_id');
                $table->unsignedBigInteger('page_id');
                $table->primary(['folder_id', 'page_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('folder_page')) {
            Schema::dropIfExists('folder_page');
        }
        if (Schema::hasColumn('pages', 'primary_folder_id')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->dropIndex(['primary_folder_id']);
                $table->dropColumn('primary_folder_id');
            });
        }
        if (Schema::hasTable('folders')) {
            Schema::dropIfExists('folders');
        }
    }
};