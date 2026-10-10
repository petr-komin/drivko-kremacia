<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Zaplaceno – samostatný údaj mimo stavy výroby (platba může přijít kdykoli). */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('paid_at'));
    }
};
