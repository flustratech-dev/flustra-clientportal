<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu langkah pada timeline pengajuan. Baris ini tidak pernah diubah setelah
 * ditulis, jadi tidak butuh updated_at.
 */
class SubmissionStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** Siapa yang pengguna lihat sebagai pelaku, bukan istilah teknis. */
    public function getActorLabelAttribute(): string
    {
        return match ($this->actor_type) {
            'portal' => $this->actor_name ?: 'Anda',
            'erp'    => 'Tim Flustra',
            default  => 'Sistem',
        };
    }

    public function getToStatusLabelAttribute(): string
    {
        return match ($this->to_status) {
            'draft'        => 'Draf dibuat',
            'submitted'    => 'Terkirim',
            'received'     => 'Diterima sistem',
            'under_review' => 'Sedang diproses',
            'approved'     => 'Disetujui',
            'rejected'     => 'Ditolak',
            'cancelled'    => 'Dibatalkan',
            default        => $this->to_status,
        };
    }

    public function getDotColorAttribute(): string
    {
        return match ($this->to_status) {
            'approved'  => 'bg-emerald-500',
            'rejected'  => 'bg-red-500',
            'cancelled' => 'bg-slate-400',
            'under_review' => 'bg-amber-500',
            default     => 'bg-blue-500',
        };
    }
}
