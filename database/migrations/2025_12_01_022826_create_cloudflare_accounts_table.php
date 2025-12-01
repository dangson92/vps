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
        Schema::create('cloudflare_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Account name for identification
            $table->string('email'); // Cloudflare account email
            $table->text('api_key'); // Cloudflare API key (encrypted)
            $table->boolean('is_default')->default(false); // Default account for new websites
            $table->timestamps();
        });

        // Add cloudflare_account_id to websites table
        Schema::table('websites', function (Blueprint $table) {
            $table->foreignId('cloudflare_account_id')->nullable()->after('id')->constrained('cloudflare_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['cloudflare_account_id']);
            $table->dropColumn('cloudflare_account_id');
        });

        Schema::dropIfExists('cloudflare_accounts');
    }
};
