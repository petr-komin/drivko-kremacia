<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Velikost urny se už nevybírá z číselníku – kremace zadá přesný objem v ml. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Nullable jen kvůli starším zakázkám s velikostí bez objemu; nové ho mají povinný
            $table->unsignedInteger('volume_ml')->nullable()->after('wood_type_id');
        });

        DB::table('orders')->update([
            'volume_ml' => DB::table('urn_sizes')->select('volume_ml')->whereColumn('urn_sizes.id', 'orders.urn_size_id'),
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('urn_size_id');
        });

        Schema::dropIfExists('urn_sizes');
    }

    public function down(): void
    {
        Schema::create('urn_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('volume_ml')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('volume_ml');
            $table->foreignId('urn_size_id')->nullable()->constrained()->restrictOnDelete();
        });
    }
};
