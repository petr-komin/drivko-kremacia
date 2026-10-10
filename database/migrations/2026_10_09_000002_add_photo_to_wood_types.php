<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ukázka dřeva, aby kremace při zadání viděla, co volí;
        // výchozí dřevina je u nové zakázky předvybraná
        Schema::table('wood_types', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('name');
            $table->boolean('is_default')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('wood_types', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'is_default']);
        });
    }
};
