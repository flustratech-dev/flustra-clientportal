<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PartnerLink;
use App\Models\SubmissionAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan berkas bukti klaim kepada staf ERP.
 *
 * Rute ini tanpa sesi dengan sengaja: staf memeriksa klaim di flustra-erp
 * (aplikasi lain, database lain) dan tidak punya — serta tidak boleh punya —
 * akun portal. Kuncinya adalah tanda tangan pada URL, yang dibuat saat klaim
 * dikirim dan kedaluwarsa sendiri setelah `portal.evidence_url_days` hari.
 *
 * Berkasnya sendiri tetap di disk privat dan tidak pernah bisa diambil lewat
 * URL langsung. Ini satu-satunya jalan keluarnya.
 */
class EvidenceController extends Controller
{
    public function claim(Request $request, PartnerLink $link): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Tautan bukti sudah kedaluwarsa.');
        abort_unless($link->evidence_file_path, 404);

        $disk = Storage::disk('private');

        abort_unless($disk->exists($link->evidence_file_path), 404);

        // Inline supaya staf bisa langsung melihat foto/PDF-nya tanpa mengunduh.
        return $disk->response(
            $link->evidence_file_path,
            'bukti-klaim-'.$link->id.'.'.pathinfo($link->evidence_file_path, PATHINFO_EXTENSION),
            ['Content-Disposition' => 'inline'],
        );
    }

    /**
     * Lampiran pengajuan (mis. foto barang retur).
     *
     * Dipakai ketika endpoint ERP-nya tidak punya tempat untuk berkas — saat
     * ini `sales_returns`. Tautannya disisipkan ke catatan yang dibaca staf.
     */
    public function attachment(Request $request, SubmissionAttachment $attachment): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Tautan berkas sudah kedaluwarsa.');

        $disk = Storage::disk($attachment->disk ?: 'private');

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response(
            $attachment->path,
            $attachment->original_name ?: basename($attachment->path),
            ['Content-Disposition' => 'inline'],
        );
    }
}
