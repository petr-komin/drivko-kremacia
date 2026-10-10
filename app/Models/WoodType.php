<?php

namespace App\Models;

use App\Support\Photos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WoodType extends Model
{
    protected $fillable = ['name', 'name_sk', 'active', 'sort_order'];

    protected $casts = [
        'active'     => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Název v jazyce prostředí; bez slovenského názvu česky. */
    public function getLabelAttribute(): string
    {
        return app()->getLocale() === 'sk' && $this->name_sk ? $this->name_sk : $this->name;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Výchozí může být jen jedna – nastavení ji ostatním odebere. */
    public function makeDefault(): void
    {
        static::query()->update(['is_default' => DB::raw('id = ' . (int) $this->id)]);
        $this->is_default = true;
        $this->syncOriginalAttribute('is_default');
    }

    public static function defaultId(): ?int
    {
        return static::active()->where('is_default', true)->value('id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // ── Fotka dřeva ──────────────────────────────────────────────────────────

    /**
     * Uloží ukázkovou fotku (nahradí předchozí). Vrací false, když z ní nejde
     * udělat náhled – fotka pak nemá smysl, protože se zobrazuje jen náhled.
     */
    public function replacePhoto(UploadedFile $file): bool
    {
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $path = $file->storeAs('woods', Str::uuid() . '.' . $ext, Photos::DISK);

        if (! Photos::generate($path)) {
            Photos::delete($path);

            return false;
        }

        $this->removePhoto();
        $this->forceFill(['photo_path' => $path])->save();

        return true;
    }

    public function removePhoto(): void
    {
        if ($this->photo_path) {
            Photos::delete($this->photo_path);
            $this->forceFill(['photo_path' => null])->save();
        }
    }

    /** Adresa náhledu; ?v= kvůli cache prohlížeče po výměně fotky. */
    public function photoUrl(string $variant = 'thumb'): ?string
    {
        return $this->photo_path
            ? route('woods.photo', [$this, $variant, 'v' => substr(md5($this->photo_path), 0, 8)])
            : null;
    }
}
