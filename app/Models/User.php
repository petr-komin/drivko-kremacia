<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role', 'locale', 'active'])]
#[Hidden(['password', 'remember_token', 'access_token_hash', 'access_token_encrypted'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CLIENT = 'client';

    /** Popisky jsou klíče překladu – zobrazovat přes __(). */
    public const ROLES = [
        self::ROLE_ADMIN  => 'Dílna (admin)',
        self::ROLE_CLIENT => 'Kremace',
    ];

    /** Jazyk prostředí i e-mailů, které uživatel dostává. */
    public function preferredLocale(): string
    {
        return array_key_exists($this->locale, config('kremacia.locales')) ? $this->locale : config('app.locale');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'access_token_created_at' => 'datetime',
            'access_token_encrypted' => 'encrypted',
        ];
    }

    /**
     * Laravel odmítá trvalé přihlášení (remember cookie) u účtu bez hesla –
     * účet jen s odkazem by se po zavření prohlížeče odhlásil. Prázdný řetězec
     * cookie projde a přihlášení heslem dál selže (hasher prázdný hash odmítne).
     */
    public function getAuthPassword(): string
    {
        return $this->password ?? '';
    }

    // ── Přístupový odkaz ─────────────────────────────────────────────────────

    /**
     * Vydá nový přístupový odkaz a vrátí token. Přihlášení hledá podle hashe,
     * token je navíc uložený zašifrovaně (APP_KEY), aby ho dílna mohla znovu
     * zobrazit. Starý odkaz tím přestane platit a všechna zařízení, která se
     * přes něj přihlásila, se odhlásí.
     */
    public function issueAccessToken(): string
    {
        $token = Str::random(48);

        $this->forceFill([
            'access_token_hash'       => self::hashToken($token),
            'access_token_encrypted'  => $token,
            'access_token_created_at' => now(),
        ])->save();

        $this->logoutEverywhere();

        return $token;
    }

    public function revokeAccessToken(): void
    {
        $this->forceFill([
            'access_token_hash'       => null,
            'access_token_encrypted'  => null,
            'access_token_created_at' => null,
        ])->save();
        $this->logoutEverywhere();
    }

    public function hasAccessLink(): bool
    {
        return $this->access_token_hash !== null;
    }

    /** Platný odkaz k opětovnému zobrazení; null u odkazů vydaných před jeho ukládáním. */
    public function accessLink(): ?string
    {
        return $this->hasAccessLink() && $this->access_token_encrypted
            ? self::accessUrl($this->access_token_encrypted)
            : null;
    }

    public static function findByAccessToken(string $token): ?self
    {
        return static::active()->where('access_token_hash', self::hashToken($token))->first();
    }

    public static function accessUrl(string $token): string
    {
        return route('access', $token);
    }

    /** Zneplatní trvalá přihlášení (remember cookie) i běžící session. */
    public function logoutEverywhere(): void
    {
        $this->setRememberToken(Str::random(60));
        $this->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $this->id)->delete();
        }
    }

    private static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeClients($query)
    {
        return $query->where('role', self::ROLE_CLIENT);
    }
}
