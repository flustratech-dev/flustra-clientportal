<?php

namespace App\Http\Controllers\Layanan\Umum;

use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Form pertanyaan di halaman Bantuan.
 *
 * Sengaja **tidak** terhubung ke `flustra-helpdesk`. Helpdesk melayani
 * pelanggan produk SaaS; portal ini melayani mitra kantor. Keduanya melayani
 * audiens berbeda dan tidak pernah bertemu — keputusan pemilik produk, dan
 * menautkannya hanya akan membuat dua daftar pengguna saling tercampur.
 *
 * Jawabannya kembali lewat webhook `submission.status_changed`, jadi penanya
 * membacanya di halaman Riwayat yang sama dengan pengajuan lainnya.
 */
class PertanyaanController extends LayananUmumController
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'subject.required' => 'Tulis pokok pertanyaannya secara singkat.',
            'message.required' => 'Jelaskan pertanyaan Anda.',
        ]);

        $user = $this->user();
        $link = $user->activeLink();

        $submission = Submission::create([
            'user_id'          => $user->id,
            'partner_link_id'  => $link?->id,
            'type'             => 'inquiry',
            'reference_number' => Submission::generateReference(),
            'title'            => $data['subject'],
            'summary'          => \Illuminate\Support\Str::limit($data['message'], 120),
            'erp_module'       => 'portal_inquiries',
            'payload'          => [
                'subject'      => $data['subject'],
                'message'      => $data['message'],
                'partner_type' => $link?->partner_type,
            ],
            'status'         => 'submitted',
            'submitted_at'   => now(),
            'last_status_at' => now(),
            'sync_state'     => 'pending',
        ]);

        $submission->histories()->create([
            'to_status'  => 'submitted',
            'note'       => 'Pertanyaan Anda diterima dan sedang diteruskan ke tim kami.',
            'actor_type' => 'portal',
            'actor_name' => $user->name,
            'created_at' => now(),
        ]);

        ActivityLog::log('inquiry_submitted', 'Mengirim pertanyaan: '.$data['subject']);

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('riwayat.show', $submission)->with(
            'success',
            'Pertanyaan Anda terkirim dengan nomor '.$submission->reference_number
                .'. Jawabannya akan muncul di halaman ini.'
        );
    }
}
