<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Jednotné upozornění k zakázce – nová zakázka, zpráva, změna stavu.
 * Nadpis se skládá až při odeslání, aby byl v jazyce příjemce (Mail::to()->locale()).
 */
class OrderUpdateMail extends Mailable
{
    /** @param array{0: string, 1?: string} $event  ['new'] | ['message', jméno autora] | ['status'] */
    public function __construct(
        public Order $order,
        public array $event,
        public ?string $text = null,
        public int $photos = 0,
    ) {}

    public function headline(): string
    {
        return match ($this->event[0]) {
            'new'     => __('nová zakázka'),
            'message' => __('nová zpráva od :name', ['name' => $this->event[1] ?? '']),
            'status'  => __('stav: :status', ['status' => $this->order->status_label]),
        };
    }

    /** Text zprávy + počet přiložených fotek. */
    public function body(): ?string
    {
        $photos = $this->photos
            ? '(+ ' . trans_choice('{1} :count fotka|[2,4] :count fotky|[5,*] :count fotek', $this->photos) . ')'
            : '';

        return trim(($this->text ?? '') . "\n\n" . $photos) ?: null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->order->number} {$this->order->pet_name}: {$this->headline()}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-update', with: [
            'headline' => $this->headline(),
            'bodyText' => $this->body(),
        ]);
    }
}
