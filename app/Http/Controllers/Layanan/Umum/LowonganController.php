<?php

namespace App\Http\Controllers\Layanan\Umum;

use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lowongan & Lamaran.
 *
 * Mengaktifkan enum `candidates.source = 'Website'` di ERP yang selama ini
 * menganggur: opsinya ada sejak awal, tapi form publiknya tidak pernah dibuat,
 * jadi lamaran selalu masuk lewat email dan diketik ulang HR.
 *
 * Lowongan `is_internal_only` disaring di ERP, bukan di sini. Posisi yang belum
 * diumumkan tidak boleh bocor lewat portal.
 */
class LowonganController extends LayananUmumController
{
    public function index(): View
    {
        $vacancies = $this->tarik(fn () => $this->erp->vacancies(), []);

        // Lamaran yang pernah dikirim, supaya pelamar tidak mengirim dua kali
        // untuk posisi yang sama dan tahu di mana posisinya.
        $lamaran = Submission::where('type', 'job_application')
            ->get()
            ->keyBy(fn (Submission $s) => $s->erp_record_id ?? $s->id);

        $dilamar = Submission::where('type', 'job_application')
            ->get()
            ->pluck('payload.job_vacancy_id')
            ->filter()
            ->all();

        return $this->halaman('layanan.umum.lowongan.index', [
            'vacancies' => $vacancies,
            'lamaran'   => $lamaran,
            'dilamar'   => $dilamar,
        ]);
    }

    public function apply(Request $request, int $vacancy): RedirectResponse
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'resume'    => ['required', 'file', 'mimes:pdf,doc,docx', 'max:'.config('portal.max_upload_kb')],
        ], [
            'resume.required' => 'Lampirkan CV Anda dalam format PDF atau Word.',
        ]);

        $user = $this->user();

        $sudahAda = Submission::where('type', 'job_application')
            ->where('payload->job_vacancy_id', $vacancy)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->exists();

        if ($sudahAda) {
            return back()->with('error', 'Anda sudah melamar untuk posisi ini. Tim HR kami sedang meninjaunya.');
        }

        $file = $request->file('resume');
        $path = $file->store('lamaran', 'private');

        $submission = DB::transaction(function () use ($user, $data, $vacancy, $path, $file) {
            $submission = Submission::create([
                'user_id'          => $user->id,
                'type'             => 'job_application',
                'reference_number' => Submission::generateReference(),
                'title'            => 'Lamaran untuk posisi '.$data['title'],
                'summary'          => $data['full_name'],
                'erp_module'       => 'candidates',
                'erp_reference'    => $data['title'],
                'payload'          => [
                    'job_vacancy_id' => $vacancy,
                    'vacancy_title'  => $data['title'],
                    'full_name'      => $data['full_name'],
                    'email'          => $data['email'],
                    'phone'          => $data['phone'] ?? null,
                ],
                'status'         => 'submitted',
                'submitted_at'   => now(),
                'last_status_at' => now(),
                'sync_state'     => 'pending',
            ]);

            $submission->attachments()->create([
                'disk'          => 'private',
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
            ]);

            $submission->histories()->create([
                'to_status'  => 'submitted',
                'note'       => 'Lamaran Anda diterima dan sedang diteruskan ke tim HR kami.',
                'actor_type' => 'portal',
                'actor_name' => $user->name,
                'created_at' => now(),
            ]);

            return $submission;
        });

        ActivityLog::log('job_application_submitted', 'Melamar untuk posisi '.$data['title'].'.');

        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('umum.lowongan.index')->with(
            'success',
            'Lamaran Anda untuk posisi '.$data['title'].' terkirim dengan nomor '.$submission->reference_number
                .'. Tim HR kami akan menghubungi Anda bila cocok.'
        );
    }
}
