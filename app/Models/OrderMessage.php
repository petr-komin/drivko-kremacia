<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMessage extends Model
{
    protected $fillable = ['order_id', 'user_id', 'body', 'is_system', 'meta'];

    protected $casts = [
        'is_system' => 'boolean',
        'meta'      => 'array',
    ];

    /**
     * Systémová zpráva ve vlákně. Ukládá se událost (meta) a k ní česká
     * záloha do body; zobrazuje se přeložená podle jazyka čtenáře (text()).
     */
    public static function system(Order $order, string $event, array $params = []): self
    {
        $message = new self(['is_system' => true, 'meta' => ['event' => $event] + $params]);
        $message->order()->associate($order);
        $message->body = app('translator')->get(self::EVENTS[$event], $message->eventParams('cs'), 'cs');
        $message->save();

        return $message;
    }

    private const EVENTS = [
        'status' => 'Stav změněn na „:status“.',
        'edited' => ':name upravil(a) zadání zakázky.',
    ];

    /** Text v aktuálním jazyce. */
    public function text(): ?string
    {
        $event = $this->meta['event'] ?? null;

        return isset(self::EVENTS[$event]) ? __(self::EVENTS[$event], $this->eventParams()) : $this->body;
    }

    private function eventParams(?string $locale = null): array
    {
        $params = $this->meta;
        unset($params['event']);

        if (isset($params['status'])) {
            $params['status'] = __(Order::STATUSES[$params['status']][0] ?? $params['status'], [], $locale);
        }

        return $params;
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function photos()
    {
        return $this->hasMany(OrderPhoto::class)->orderBy('id');
    }
}
