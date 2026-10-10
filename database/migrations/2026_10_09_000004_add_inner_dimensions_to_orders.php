<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vnitřní rozměry urny v cm, když má kremace konkrétní přání (nízká široká,
     * vysoká úzká…). Nepovinné – jinak rozměry k objemu volí dílna.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('inner_width_cm', 5, 1)->nullable()->after('volume_ml');
            $table->decimal('inner_height_cm', 5, 1)->nullable()->after('inner_width_cm');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['inner_width_cm', 'inner_height_cm']);
        });
    }
};
