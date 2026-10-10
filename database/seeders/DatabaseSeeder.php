<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WoodType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();

        // Výchozí číselníky jen do prázdné databáze – dál se spravují v aplikaci
        if (WoodType::doesntExist()) {
            $woods = ['Olše' => 'Jelša', 'Dub' => 'Dub', 'Buk' => 'Buk', 'Jasan' => 'Jaseň', 'Ořech' => 'Orech'];
            foreach (array_keys($woods) as $i => $name) {
                WoodType::create(['name' => $name, 'name_sk' => $woods[$name], 'sort_order' => $i * 10]);
            }
            WoodType::where('name', 'Olše')->first()->makeDefault();
        }
    }

    /** Admin z .env (ADMIN_EMAIL, ADMIN_PASSWORD). Výchozí heslo neexistuje. */
    private function seedAdmin(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD nejsou vyplněné – admin se nezakládá. Použijte php artisan user:create email jméno --admin (vypíše přístupový odkaz).');

            return;
        }

        User::firstOrCreate(['email' => $email], [
            'name'     => env('ADMIN_NAME', 'Admin'),
            'password' => $password,
            'role'     => User::ROLE_ADMIN,
        ]);
    }
}
