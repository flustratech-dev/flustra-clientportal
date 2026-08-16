<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Setelan portal, key/value. Meniru `system_settings` di flustra-erp.
 *
 * Dibaca di setiap render halaman (banner pemberitahuan), jadi seluruh isinya
 * dicache sebagai satu array — bukan satu query per kunci.
 */
class PortalSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public const CACHE_KEY = 'portal_settings';

    /** @return array<string, string|null> */
    public static function semua(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::pluck('value', 'key')->all());
    }

    public static function ambil(string $key, mixed $bawaan = null): mixed
    {
        return self::semua()[$key] ?? $bawaan;
    }

    public static function simpan(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => (string) $value]);

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Simpan beberapa kunci sekaligus, lalu buang cache sekali saja.
     *
     * @param  array<string, mixed>  $pasangan
     */
    public static function simpanBanyak(array $pasangan): void
    {
        foreach ($pasangan as $key => $value) {
            self::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
