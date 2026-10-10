<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Čeština / slovenština: jazyk uživatele, slovenský název dřeva a systémové
     * zprávy ve vlákně jako klíč překladu (event + params), aby se zobrazily
     * v jazyce čtenáře. Text v `body` zůstává jako česká záloha.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('cs')->after('role');
        });

        Schema::table('wood_types', function (Blueprint $table) {
            $table->string('name_sk', 80)->nullable()->after('name');
        });

        Schema::table('order_messages', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('is_system');
        });

        $sk = ['Olše' => 'Jelša', 'Dub' => 'Dub', 'Buk' => 'Buk', 'Jasan' => 'Jaseň', 'Ořech' => 'Orech',
               'Smrk' => 'Smrek', 'Třešeň' => 'Čerešňa', 'Javor' => 'Javor', 'Lípa' => 'Lipa', 'Bříza' => 'Breza'];
        foreach ($sk as $cs => $name) {
            DB::table('wood_types')->where('name', $cs)->whereNull('name_sk')->update(['name_sk' => $name]);
        }

        // Dosavadní systémové zprávy převést na klíč, ať se taky přeloží
        $statuses = ['Nová' => 'new', 'Ve výrobě' => 'in_production', 'Hotovo' => 'finished', 'Předáno' => 'handed_over', 'Zrušeno' => 'cancelled'];
        DB::table('order_messages')->where('is_system', true)->orderBy('id')->each(function ($m) use ($statuses) {
            $meta = null;
            if (preg_match('/^Stav změněn na „(.+)“\.$/u', (string) $m->body, $x) && isset($statuses[$x[1]])) {
                $meta = ['event' => 'status', 'status' => $statuses[$x[1]]];
            } elseif (preg_match('/^(.+) upravil\(a\) zadání zakázky\.$/u', (string) $m->body, $x)) {
                $meta = ['event' => 'edited', 'name' => $x[1]];
            }
            if ($meta) {
                DB::table('order_messages')->where('id', $m->id)->update(['meta' => json_encode($meta)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_messages', fn (Blueprint $table) => $table->dropColumn('meta'));
        Schema::table('wood_types', fn (Blueprint $table) => $table->dropColumn('name_sk'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
