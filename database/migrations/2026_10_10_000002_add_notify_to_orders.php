<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Vypnutá upozornění k zakázce – zpětný zápis starších zakázek bez spamu. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('notify')->default(true)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('notify'));
    }
};
