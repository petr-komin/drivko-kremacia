<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ceník u dřeva (do 1500 ml včetně / nad 1500 ml, v eurech) a cena uložená
     * u zakázky – pozdější změna ceníku nepřepíše cenu už zadaných zakázek.
     */
    public function up(): void
    {
        Schema::table('wood_types', function (Blueprint $table) {
            $table->decimal('price_small_eur', 8, 2)->nullable()->after('name_sk');
            $table->decimal('price_large_eur', 8, 2)->nullable()->after('price_small_eur');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('price_eur', 8, 2)->nullable()->after('layout');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('price_eur'));
        Schema::table('wood_types', fn (Blueprint $table) => $table->dropColumn(['price_small_eur', 'price_large_eur']));
    }
};
