<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;

/**
 * Riwayat & Status — memenuhi janji "pengguna bisa melihat statusnya sendiri
 * kapan saja tanpa bertanya lewat WhatsApp".
 *
 * Tidak ada filter user_id di sini karena Submission punya global scope yang
 * sudah membatasinya. Route model binding pun ikut tersaring, jadi membuka
 * pengajuan milik orang lain menghasilkan 404.
 */
class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Submission::query()->whereNot('status', 'draft');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('reference_number', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('erp_reference', 'like', "%{$q}%");
            });
        }

        $submissions = $query->latest('submitted_at')->paginate(20)->withQueryString();

        return view('portal.history.index', compact('submissions'));
    }

    public function show(Submission $submission)
    {
        $submission->load(['histories', 'attachments']);

        return view('portal.history.show', compact('submission'));
    }
}
