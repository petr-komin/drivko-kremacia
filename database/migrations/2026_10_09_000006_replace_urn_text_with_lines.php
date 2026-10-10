<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Text na urnu po řádcích, každý s vlastní velikostí písma: [{"text": "Bobík", "size": "L"}, …] */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('urn_lines')->nullable()->after('with_paw');
        });

        DB::table('orders')->whereNotNull('urn_text')->orderBy('id')->each(function ($order) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $order->urn_text))));
            DB::table('orders')->where('id', $order->id)->update([
                'urn_lines' => $lines ? json_encode(array_map(fn ($t) => ['text' => $t, 'size' => 'M'], $lines)) : null,
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('urn_text');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('urn_text')->nullable()->after('with_paw');
        });

        DB::table('orders')->whereNotNull('urn_lines')->orderBy('id')->each(function ($order) {
            DB::table('orders')->where('id', $order->id)->update([
                'urn_text' => implode("\n", array_column(json_decode($order->urn_lines, true) ?: [], 'text')) ?: null,
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('urn_lines');
        });
    }
};
