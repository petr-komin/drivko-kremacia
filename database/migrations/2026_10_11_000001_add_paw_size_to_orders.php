<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rozměr tlapky (cm) – nahrazuje fotku tlapky s pravítkem.
     * Dosavadní zakázky měly rozměr na fotce (paw_size_in_photo), chybějící rozměr u nich nehlásit.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('paw_width_cm', 4, 1)->nullable()->after('with_paw');
            $table->decimal('paw_height_cm', 4, 1)->nullable()->after('paw_width_cm');
            $table->boolean('paw_size_in_photo')->default(false)->after('paw_height_cm');
        });

        DB::table('orders')->update(['paw_size_in_photo' => true]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['paw_width_cm', 'paw_height_cm', 'paw_size_in_photo']));
    }
};
