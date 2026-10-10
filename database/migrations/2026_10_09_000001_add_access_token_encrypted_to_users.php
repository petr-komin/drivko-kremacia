<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Odkaz zašifrovaný APP_KEY, aby ho dílna mohla kdykoli znovu zobrazit.
        // Přihlášení dál hledá podle access_token_hash.
        Schema::table('users', function (Blueprint $table) {
            $table->text('access_token_encrypted')->nullable()->after('access_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('access_token_encrypted');
        });
    }
};
