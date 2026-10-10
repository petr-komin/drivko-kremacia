<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_IN_PRODUCTION = 'in_production';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_HANDED_OVER = 'handed_over';
    public const STATUS_CANCELLED = 'cancelled';

    /** stav => [popisek (klíč překladu), barva Bootstrapu, ikona] v pořadí, jak zakázka prochází výrobou */
    public const STATUSES = [
        self::STATUS_NEW           => ['Nová', 'primary', 'bi-inbox'],
        self::STATUS_IN_PRODUCTION => ['Ve výrobě', 'warning', 'bi-hammer'],
        self::STATUS_FINISHED      => ['Hotovo', 'success', 'bi-check2-circle'],
        self::STATUS_HANDED_OVER   => ['Předáno', 'secondary', 'bi-box-arrow-right'],
        self::STATUS_CANCELLED     => ['Zrušeno', 'danger', 'bi-x-circle'],
    ];

    /** Sloupec s časem, kdy zakázka do stavu vstoupila. */
    private const STATUS_TIMESTAMPS = [
        self::STATUS_IN_PRODUCTION => 'started_at',
        self::STATUS_FINISHED      => 'finished_at',
        self::STATUS_HANDED_OVER   => 'handed_over_at',
        self::STATUS_CANCELLED     => 'cancelled_at',
    ];

    protected $fillable = [
        'case_number', 'pet_name', 'wood_type_id', 'volume_ml', 'inner_width_cm', 'inner_height_cm',
        'with_paw', 'urn_lines', 'layout', 'note', 'notify',
        'status', 'created_by', 'last_activity_at',
    ];

    protected $casts = [
        'volume_ml'        => 'integer',
        'with_paw'         => 'boolean',
        'notify'           => 'boolean',
        'urn_lines'        => 'array',
        'inner_width_cm'   => 'decimal:1',
        'inner_height_cm'  => 'decimal:1',
        'started_at'       => 'datetime',
        'finished_at'      => 'datetime',
        'handed_over_at'   => 'datetime',
        'cancelled_at'     => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    // ── Relace ───────────────────────────────────────────────────────────────

    public function woodType()
    {
        return $this->belongsTo(WoodType::class);
    }

    /** Rozložení tlapky a textu – jen když má urna obojí. Popisky = klíče překladu. */
    public const LAYOUTS = [
        'paw_top'   => 'Tlapka nahoře, text dole',
        'text_top'  => 'Text nahoře, tlapka dole',
        'paw_left'  => 'Tlapka vlevo, text vpravo',
        'text_left' => 'Text vlevo, tlapka vpravo',
    ];

    public const DEFAULT_LAYOUT = 'paw_top';

    /** Velikost písma řádku textu; poměr = výška písma vůči střední (3D náhled, detail). */
    public const TEXT_SIZES = [
        'L' => ['label' => 'Velké', 'ratio' => 1.6],
        'M' => ['label' => 'Střední', 'ratio' => 1.0],
        'S' => ['label' => 'Malé', 'ratio' => 0.65],
    ];

    public const MAX_TEXT_LINES = 6;

    public function hasUrnText(): bool
    {
        return ! empty($this->urn_lines);
    }

    /** Text na urnu jako prostý text (řádky pod sebou) – CSV a místa bez velikostí. */
    public function getUrnTextAttribute(): ?string
    {
        return $this->hasUrnText() ? implode("\n", array_column($this->urn_lines, 'text')) : null;
    }

    /** „Bobík (velké) / 2012–2026 (malé)“ – do CSV. */
    public function urnTextWithSizes(): ?string
    {
        if (! $this->hasUrnText()) {
            return null;
        }

        return implode(' / ', array_map(
            fn ($l) => $l['text'] . ' (' . mb_strtolower(self::textSizeLabel($l['size'])) . ')',
            $this->urn_lines,
        ));
    }

    public static function textSizeLabel(?string $size): string
    {
        return isset(self::TEXT_SIZES[$size]) ? __(self::TEXT_SIZES[$size]['label']) : '';
    }

    public function getLayoutLabelAttribute(): ?string
    {
        return isset(self::LAYOUTS[$this->layout]) ? __(self::LAYOUTS[$this->layout]) : null;
    }

    /** Síla dřeva v cm: stěny a dno; nahoře zanořené víko (do objemu se nepočítá) + horní stěna. */
    public const WALL_CM = 0.8;
    public const LID_PLUG_CM = 1.5;
    public const LID_TOP_CM = 0.8;
    public const TOP_CM = self::LID_PLUG_CM + self::LID_TOP_CM;

    /** Rozsah vnitřních rozměrů (šířka i výška) v cm; z nich plyne i rozsah objemu (5³ až 25³). */
    public const MIN_INNER_CM = 5;
    public const MAX_INNER_CM = 25;
    public const MIN_VOLUME_ML = self::MIN_INNER_CM ** 3;
    public const MAX_VOLUME_ML = self::MAX_INNER_CM ** 3;

    public function hasInnerDimensions(): bool
    {
        return $this->inner_width_cm !== null && $this->inner_height_cm !== null;
    }

    /** „10 × 10 × 15 cm“ (š × h × v); vnější = informativní, podle síly dřeva. */
    public function dimensionsLabel(bool $outer = false): ?string
    {
        if (! $this->hasInnerDimensions()) {
            return null;
        }

        $w = (float) $this->inner_width_cm + ($outer ? 2 * self::WALL_CM : 0);
        $h = (float) $this->inner_height_cm + ($outer ? self::WALL_CM + self::TOP_CM : 0);
        $f = fn (float $n) => rtrim(rtrim(number_format($n, 1, ',', ''), '0'), ',');

        return "{$f($w)} × {$f($w)} × {$f($h)} cm";
    }

    /** Objem urny k zobrazení: 800 ml, 1,5 l. */
    public function getVolumeLabelAttribute(): string
    {
        if (! $this->volume_ml) {
            return '–';
        }

        return $this->volume_ml >= 1000
            ? rtrim(rtrim(number_format($this->volume_ml / 1000, 2, ',', ''), '0'), ',') . ' l'
            : $this->volume_ml . ' ml';
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(OrderMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function photos()
    {
        return $this->hasMany(OrderPhoto::class)->orderBy('id');
    }

    public function pawPhotos()
    {
        return $this->photos()->where('kind', OrderPhoto::KIND_PAW);
    }

    public function finishedPhotos()
    {
        return $this->photos()->where('kind', OrderPhoto::KIND_FINISHED);
    }

    // ── Stav ─────────────────────────────────────────────────────────────────

    /** Interní číslo zakázky, např. U-0042. */
    public function getNumberAttribute(): string
    {
        return 'U-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return isset(self::STATUSES[$this->status]) ? __(self::STATUSES[$this->status][0]) : $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function getStatusIconAttribute(): string
    {
        return self::STATUSES[$this->status][2] ?? 'bi-circle';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_IN_PRODUCTION], true);
    }

    /**
     * Nastaví stav a zapíše čas vstupu do něj. Při návratu do dřívějšího
     * stavu (oprava omylu) se časy pozdějších stavů smažou, aby přehled
     * hotových kusů nepočítal zakázky, které hotové nejsou.
     */
    public function moveTo(string $status): void
    {
        $order = array_keys(self::STATUSES);
        $target = array_search($status, $order, true);

        foreach (self::STATUS_TIMESTAMPS as $s => $column) {
            if ($s === $status) {
                $this->{$column} ??= now();
            } elseif ($s !== self::STATUS_CANCELLED && array_search($s, $order, true) > $target) {
                $this->{$column} = null;
            }
        }

        if ($status !== self::STATUS_CANCELLED) {
            $this->cancelled_at = null;
        }

        $this->status = $status;
        $this->last_activity_at = now();
        $this->save();
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }
}
