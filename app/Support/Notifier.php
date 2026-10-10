<?php

namespace App\Support;

use App\Mail\OrderUpdateMail;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * E-mailová upozornění. Dílna dostává novinky od kremace a naopak –
 * autor akce upozornění nedostane. Nedoručený e-mail nesmí shodit
 * uložení zakázky, proto se chyba jen zaloguje. Každý příjemce dostane
 * e-mail ve svém jazyce (users.locale); adresa mimo uživatele česky.
 * Zakázka s vypnutými upozorněními (orders.notify – zpětný zápis) nic neposílá.
 */
final class Notifier
{
    public static function newOrder(Order $order, User $author): void
    {
        self::send(self::adminRecipients($author), $order, ['new'], $order->note);
    }

    public static function message(Order $order, OrderMessage $message, User $author): void
    {
        $recipients = $author->isAdmin() ? self::clientRecipients($author) : self::adminRecipients($author);

        self::send($recipients, $order, ['message', $author->name], $message->body, $message->photos()->count());
    }

    public static function statusChanged(Order $order, User $author, ?string $text = null): void
    {
        self::send(self::clientRecipients($author), $order, ['status'], $text);
    }

    // ── Interní ─────────────────────────────────────────────────────────────

    /** @return array<string, string> e-mail => jazyk */
    private static function adminRecipients(User $author): array
    {
        $configured = config('kremacia.notify_admin_emails');

        $recipients = $configured
            // Adresa, která patří uživateli, dostane jeho jazyk
            ? array_merge(array_fill_keys($configured, config('app.locale')),
                User::whereIn('email', $configured)->pluck('locale', 'email')->all())
            : User::admins()->active()->pluck('locale', 'email')->all();

        unset($recipients[$author->email]);

        return $recipients;
    }

    /** @return array<string, string> e-mail => jazyk */
    private static function clientRecipients(User $author): array
    {
        return User::clients()->active()->where('id', '!=', $author->id)->pluck('locale', 'email')->all();
    }

    private static function send(array $recipients, Order $order, array $event, ?string $text = null, int $photos = 0): void
    {
        if (! $order->notify) {
            return;
        }

        foreach ($recipients as $email => $locale) {
            try {
                Mail::to($email)->locale($locale ?: config('app.locale'))
                    ->send(new OrderUpdateMail($order, $event, $text, $photos));
            } catch (\Throwable $e) {
                Log::error('Upozornění se nepodařilo odeslat: ' . $e->getMessage(), [
                    'order_id' => $order->id,
                    'email'    => $email,
                ]);
            }
        }
    }
}
