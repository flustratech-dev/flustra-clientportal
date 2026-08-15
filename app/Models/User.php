<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'account_type',
        'active_link_id',
        'status',
        'google_id',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // =====================================================================
    // Relasi
    // =====================================================================

    public function partnerLinks(): HasMany
    {
        return $this->hasMany(PartnerLink::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // =====================================================================
    // Peran & akses
    // =====================================================================

    /**
     * Peran yang sedang dipakai. Inilah satu-satunya sumber id mitra yang boleh
     * dipercaya — jangan pernah mengambil id mitra dari request.
     */
    public function activeLink(): ?PartnerLink
    {
        if ($this->active_link_id) {
            $link = $this->partnerLinks()
                ->where('id', $this->active_link_id)
                ->where('status', 'verified')
                ->first();

            if ($link) {
                return $link;
            }
        }

        // Belum pernah memilih, atau peran aktifnya sudah dicabut: pakai peran
        // terverifikasi pertama yang dia punya.
        return $this->partnerLinks()->where('status', 'verified')->orderBy('id')->first();
    }

    /** Semua peran terverifikasi, untuk pemilih peran di header. */
    public function verifiedLinks()
    {
        return $this->partnerLinks()->where('status', 'verified')->orderBy('id')->get();
    }

    public function isUmum(): bool
    {
        return $this->account_type === 'umum';
    }

    public function isPelanggan(): bool
    {
        return $this->account_type === 'pelanggan';
    }

    public function isVendor(): bool
    {
        return $this->account_type === 'vendor';
    }

    /** Sudah terverifikasi sebagai mitra apa pun. */
    public function isMitra(): bool
    {
        return in_array($this->account_type, ['pelanggan', 'vendor'], true);
    }

    /** Ada klaim yang masih menunggu keputusan staf. */
    public function hasPendingClaim(): bool
    {
        return $this->partnerLinks()->where('status', 'pending')->exists();
    }

    public function getAccountTypeLabelAttribute(): string
    {
        return match ($this->account_type) {
            'pelanggan' => 'Pelanggan',
            'vendor'    => 'Vendor',
            default     => 'Umum',
        };
    }

    /**
     * Kelas utilitas lengkap, bukan nama warna. Tailwind memindai berkas sumber
     * sebagai teks, jadi kelas yang dirangkai saat runtime tidak pernah
     * ter-generate.
     */
    public function getAccountTypeColorAttribute(): string
    {
        return match ($this->account_type) {
            'pelanggan' => 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400',
            'vendor'    => 'bg-violet-50 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400',
            default     => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400',
        };
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar && str_starts_with($this->avatar, 'http')) {
            return $this->avatar;
        }

        if ($this->avatar && Storage::disk('public')->exists($this->avatar)) {
            return asset('storage/'.$this->avatar);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name ?: 'Pengguna').'&color=fff&background=0284c7';
    }
}
