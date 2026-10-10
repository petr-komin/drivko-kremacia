<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Urna nemusí mít otisk tlapky; může mít text. Když má obojí,
     * kremace volí rozložení (Order::LAYOUTS).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('with_paw')->default(true)->after('inner_height_cm');
            $table->text('urn_text')->nullable()->after('with_paw');
            $table->string('layout', 20)->nullable()->after('urn_text');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['with_paw', 'urn_text', 'layout']);
        });
    }
};
