<?php

namespace App\Models;

use App\Support\Photos;
use Illuminate\Database\Eloquent\Model;

class OrderPhoto extends Model
{
    public const KIND_PAW = 'paw';
    public const KIND_FINISHED = 'finished';
    public const KIND_MESSAGE = 'message';

    public const KINDS = [
        self::KIND_PAW      => 'Podklad – tlapka',
        self::KIND_FINISHED => 'Hotový kus',
        self::KIND_MESSAGE  => 'Příloha zprávy',
    ];

    protected $fillable = [
        'order_id', 'order_message_id', 'user_id', 'kind',
        'path', 'original_name', 'mime', 'size', 'has_preview',
    ];

    protected $casts = [
        'size'        => 'integer',
        'has_preview' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (OrderPhoto $photo) => Photos::delete($photo->path));
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function url(string $variant = 'large'): string
    {
        return route('photos.show', [$this, $variant]);
    }

    public function downloadUrl(): string
    {
        return route('photos.show', [$this, 'original']);
    }
}
