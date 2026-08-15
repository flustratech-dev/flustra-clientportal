<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Satu pengajuan yang dibuat pengguna portal.
 *
 * Disimpan di sini SEBELUM dikirim ke ERP. Kalau ERP sedang mati, pengajuan
 * tetap tersimpan dan diantre ulang, bukan hilang — dan pengguna tetap bisa
 * melihat statusnya.
 */
class Submission extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'payload'        => 'array',
        'amount'         => 'decimal:2',
        'submitted_at'   => 'datetime',
        'synced_at'      => 'datetime',
        'last_status_at' => 'datetime',
    ];

    /**
     * Pagar isolasi data.
     *
     * Setiap query otomatis dibatasi ke pengguna yang sedang login. Ini menutup
     * seluruh kelas bug "lupa menambahkan where user_id" sekaligus, alih-alih
     * mengandalkan setiap controller mengingatnya sendiri.
     *
     * Kalau suatu saat perlu query lintas-pengguna (misalnya perintah artisan
     * rekonsiliasi), pakai Submission::withoutGlobalScope('milik_sendiri').
     */
    protected static function booted(): void
    {
        static::addGlobalScope('milik_sendiri', function (Builder $query) {
            if (Auth::hasUser() && Auth::check()) {
                $query->where('submissions.user_id', Auth::id());
            }
        });
    }

    // =====================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partnerLink(): BelongsTo
    {
        return $this->belongsTo(PartnerLink::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SubmissionAttachment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SubmissionStatusHistory::class)->orderBy('created_at');
    }

    // =====================================================================
    // Label & warna
    // =====================================================================

    public const TYPE_LABELS = [
        'partner_claim'        => 'Pengajuan Kerja Sama',
        'payment_confirmation' => 'Konfirmasi Pembayaran',
        'quotation_decision'   => 'Keputusan Penawaran',
        'sales_return'         => 'Pengajuan Retur',
        'contract_ack'         => 'Persetujuan Kontrak',
        'profile_change'       => 'Perubahan Data',
        'vendor_bill'          => 'Pengiriman Tagihan',
        'po_confirmation'      => 'Konfirmasi Purchase Order',
        'shipping_doc'         => 'Dokumen Pengiriman',
        'return_dispute'       => 'Sanggahan Retur',
        'rfq'                  => 'Permintaan Penawaran',
        'job_application'      => 'Lamaran Kerja',
        'inquiry'              => 'Pertanyaan',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'        => 'Draf',
            'submitted'    => 'Terkirim',
            'received'     => 'Diterima Sistem',
            'under_review' => 'Sedang Diproses',
            'approved'     => 'Disetujui',
            'rejected'     => 'Ditolak',
            'cancelled'    => 'Dibatalkan',
            default        => $this->status,
        };
    }

    /** Kelas utilitas lengkap — lihat catatan di User::getAccountTypeColorAttribute. */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft'        => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400',
            'submitted'    => 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400',
            'received'     => 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400',
            'under_review' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400',
            'approved'     => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400',
            'rejected'     => 'bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400',
            'cancelled'    => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-500',
            default        => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['approved', 'rejected', 'cancelled'], true);
    }

    // =====================================================================

    /**
     * Nomor pengajuan berurutan per bulan: PRT-YYYYMM-0001.
     *
     * Dibungkus transaksi + lockForUpdate karena dua pengguna yang menekan
     * "Kirim" pada detik yang sama akan membaca nomor terakhir yang sama dan
     * menabrak unique index.
     */
    public static function generateReference(): string
    {
        return DB::transaction(function () {
            $prefix = 'PRT-'.now()->format('Ym').'-';

            $last = static::withoutGlobalScope('milik_sendiri')
                ->where('reference_number', 'like', $prefix.'%')
                ->orderByDesc('reference_number')
                ->lockForUpdate()
                ->first();

            $seq = $last ? ((int) substr($last->reference_number, -4) + 1) : 1;

            return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Ubah status sekaligus mencatat timeline-nya. Selalu lewat sini, jangan
     * update('status') langsung — kalau tidak, timeline pengguna jadi bolong.
     */
    public function transitionTo(string $status, ?string $note = null, string $actorType = 'system', ?string $actorName = null): void
    {
        $from = $this->status;

        if ($from === $status && $note === null) {
            return;
        }

        $this->forceFill([
            'status'         => $status,
            'status_reason'  => $note,
            'last_status_at' => now(),
        ])->save();

        SubmissionStatusHistory::create([
            'submission_id' => $this->id,
            'from_status'   => $from,
            'to_status'     => $status,
            'note'          => $note,
            'actor_type'    => $actorType,
            'actor_name'    => $actorName,
            'created_at'    => now(),
        ]);
    }
}
