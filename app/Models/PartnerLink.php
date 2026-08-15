<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ikatan antara satu akun portal dan satu mitra di ERP.
 *
 * Selama `status` belum 'verified' dan `erp_partner_id` masih null, tidak ada
 * data mitra apa pun yang bisa dibuka. Itu inti model aksesnya: pendaftaran
 * terbuka untuk siapa saja, tapi datanya baru terbuka setelah manusia
 * memeriksa buktinya.
 */
class PartnerLink extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified' && $this->erp_partner_id !== null;
    }

    public function getPartnerTypeLabelAttribute(): string
    {
        return $this->partner_type === 'customer' ? 'Pelanggan' : 'Vendor';
    }

    /** Tipe akun portal yang sepadan dengan partner_type di ERP. */
    public function getAccountTypeAttribute(): string
    {
        return $this->partner_type === 'customer' ? 'pelanggan' : 'vendor';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'  => 'Menunggu Verifikasi',
            'verified' => 'Terverifikasi',
            'rejected' => 'Ditolak',
            'revoked'  => 'Dicabut',
            default    => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'  => 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400',
            'verified' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400',
            'rejected' => 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400',
            'revoked'  => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400',
            default    => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400',
        };
    }

    public function getEvidenceTypeLabelAttribute(): string
    {
        return match ($this->evidence_type) {
            'invoice_number' => 'Nomor Invoice',
            'po_number'      => 'Nomor Purchase Order',
            'invite_code'    => 'Kode Undangan',
            'npwp'           => 'NPWP',
            default          => $this->evidence_type,
        };
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }
}
