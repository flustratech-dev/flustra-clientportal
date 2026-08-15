<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSubmissionToErp;
use App\Models\ActivityLog;
use App\Models\PartnerLink;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Klaim identitas mitra — pintu dari akun 'umum' menjadi 'pelanggan'/'vendor'.
 *
 * Klaim disimpan dulu di portal, lalu diteruskan ke ERP lewat antrean
 * (SyncSubmissionToErp). Urutannya penting: pengguna tidak boleh kehilangan
 * pengajuannya hanya karena ERP sedang mati saat dia menekan "Kirim".
 *
 * Keputusannya sendiri diambil staf di dalam ERP; portal menunggu kabarnya
 * lewat webhook `claim.verified` / `claim.rejected`.
 */
class PartnerClaimController extends Controller
{
    public function create()
    {
        $user = Auth::user();

        // Satu klaim berjalan per jenis mitra sudah cukup; mengizinkan banyak
        // klaim serentak hanya membuat antrean staf penuh duplikat.
        $existing = $user->partnerLinks()
            ->whereIn('status', ['pending', 'verified'])
            ->get()
            ->keyBy('partner_type');

        return view('portal.mitra.create', compact('existing'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // Satu-satunya tempat verifikasi email diwajibkan. Login tidak
        // dihadang — itu janji portal ini — tapi klaim mitra adalah titik di
        // mana identitas mulai berarti: yang dibuka setelahnya adalah data
        // keuangan sebuah perusahaan.
        if (! $user->email_verified_at) {
            return back()->withInput()->withErrors([
                'partner_type' => 'Verifikasi alamat email Anda terlebih dahulu. Tautannya bisa dikirim ulang dari Beranda.',
            ]);
        }

        $data = $request->validate([
            'partner_type'   => ['required', 'in:customer,vendor'],
            'company_name'   => ['required', 'string', 'max:255'],
            'npwp'           => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'billing_email'  => ['nullable', 'email', 'max:255'],
            'address'        => ['nullable', 'string', 'max:1000'],
            'evidence_type'  => ['required', 'in:invoice_number,po_number,invite_code,npwp'],
            'evidence_value' => ['required', 'string', 'max:255'],
            'evidence_file'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('portal.max_upload_kb')],
        ], [
            'evidence_value.required' => 'Bukti wajib diisi agar tim kami bisa mencocokkan data Anda.',
        ]);

        $sudahAda = $user->partnerLinks()
            ->where('partner_type', $data['partner_type'])
            ->whereIn('status', ['pending', 'verified'])
            ->exists();

        if ($sudahAda) {
            return back()->withInput()->withErrors([
                'partner_type' => 'Anda sudah punya pengajuan atau verifikasi aktif untuk jenis mitra ini.',
            ]);
        }

        $path = $request->hasFile('evidence_file')
            ? $request->file('evidence_file')->store('bukti-klaim', 'private')
            : null;

        [$link, $submission] = DB::transaction(function () use ($user, $data, $path) {
            $link = PartnerLink::create([
                'user_id'            => $user->id,
                'partner_type'       => $data['partner_type'],
                'company_name'       => $data['company_name'],
                'npwp'               => $data['npwp'] ?? null,
                'contact_person'     => $data['contact_person'] ?? null,
                'phone'              => $data['phone'] ?? null,
                'billing_email'      => $data['billing_email'] ?? null,
                'address'            => $data['address'] ?? null,
                'evidence_type'      => $data['evidence_type'],
                'evidence_value'     => $data['evidence_value'],
                'evidence_file_path' => $path,
                'status'             => 'pending',
            ]);

            $submission = Submission::create([
                'user_id'          => $user->id,
                'partner_link_id'  => $link->id,
                'type'             => 'partner_claim',
                'reference_number' => Submission::generateReference(),
                'title'            => 'Pengajuan kerja sama sebagai '.$link->partner_type_label,
                'summary'          => $data['company_name'],
                'payload'          => $data + ['evidence_file_path' => $path],
                'status'           => 'submitted',
                'submitted_at'     => now(),
                'last_status_at'   => now(),
                'sync_state'       => 'pending',
            ]);

            $submission->histories()->create([
                'to_status'  => 'submitted',
                'note'       => 'Pengajuan dikirim dan menunggu diteruskan ke tim kami.',
                'actor_type' => 'portal',
                'actor_name' => $user->name,
                'created_at' => now(),
            ]);

            return [$link, $submission];
        });

        ActivityLog::log('partner_claim_submitted',
            'Mengajukan verifikasi sebagai '.$link->partner_type_label.' untuk '.$data['company_name'].'.');

        // Di luar transaksi dengan sengaja. Job yang diantre di dalam transaksi
        // bisa mulai dijalankan worker sebelum COMMIT selesai, lalu tidak
        // menemukan barisnya. Di sini pengajuannya sudah pasti tersimpan.
        //
        // Kegagalan ERP tidak boleh menggagalkan aksi pengguna: kalau ERP mati,
        // job ini mengulang sesuai backoff-nya, dan pengguna tetap melihat
        // pengajuannya di halaman Riwayat.
        SyncSubmissionToErp::dispatch($submission->id);

        return redirect()->route('riwayat.show', $submission)
            ->with('success', 'Pengajuan terkirim dengan nomor '.$submission->reference_number.'. Kami akan mengabari Anda setelah diperiksa.');
    }
}
