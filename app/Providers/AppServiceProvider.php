<?php

namespace App\Providers;

use App\Mail\Transport\BrevoTransport;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Mail::extend('brevo', fn () => new BrevoTransport((string) config('services.brevo.key')));
    }
}
