<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

// Založení účtu z příkazové řádky – hlavně první admin na serveru.
Artisan::command('user:create {email} {name} {--admin : Účet dílny místo kremace}', function (string $email, string $name) {
    if (User::where('email', $email)->exists()) {
        $this->error("Účet {$email} už existuje.");

        return 1;
    }

    $user = User::create([
        'email' => $email,
        'name'  => $name,
        'role'  => $this->option('admin') ? User::ROLE_ADMIN : User::ROLE_CLIENT,
    ]);

    $this->info("Založeno: {$email}");
    $this->line('Přístupový odkaz: ' . User::accessUrl($user->issueAccessToken()));

    return 0;
})->purpose('Založí uživatele a vypíše přístupový odkaz');

// Nový odkaz pro existující účet – když se ztratí (starý přestane platit).
Artisan::command('user:link {email}', function (string $email) {
    $user = User::where('email', $email)->first();

    if (! $user) {
        $this->error("Účet {$email} neexistuje.");

        return 1;
    }

    $this->line('Přístupový odkaz: ' . User::accessUrl($user->issueAccessToken()));
    $this->comment('Předchozí odkaz přestal platit a zařízení přihlášená přes něj se odhlásila.');

    return 0;
})->purpose('Vydá nový přístupový odkaz a starý zneplatní');
