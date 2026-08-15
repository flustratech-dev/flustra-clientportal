@extends('layouts.public')
@section('title', 'Kata Sandi Baru')

@section('content')
<div class="max-w-md mx-auto">
    <div class="erp-card space-y-4">
        <div>
            <h1 class="text-lg font-bold text-slate-800 dark:text-white">Buat kata sandi baru</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                Minimal 8 karakter. Kami juga memeriksanya terhadap daftar kata sandi yang pernah bocor —
                bukan untuk menyulitkan, tapi karena kata sandi yang sudah beredar bukan lagi rahasia.
            </p>
        </div>

        @if($errors->any())
            <div class="p-3 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
                <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('password.update') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label class="erp-label">Alamat email</label>
                <input type="email" name="email" value="{{ old('email', $email) }}" required class="erp-input">
            </div>

            <div>
                <label class="erp-label">Kata sandi baru</label>
                <input type="password" name="password" required class="erp-input" autocomplete="new-password">
            </div>

            <div>
                <label class="erp-label">Ulangi kata sandi baru</label>
                <input type="password" name="password_confirmation" required class="erp-input" autocomplete="new-password">
            </div>

            <button type="submit" class="btn-primary w-full justify-center">Simpan Kata Sandi</button>
        </form>

        <p class="text-center text-[11px] text-slate-500">
            <a href="{{ route('login') }}" class="text-blue-500 hover:underline font-semibold">Kembali ke halaman masuk</a>
        </p>
    </div>
</div>
@endsection
