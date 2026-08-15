@extends('layouts.public')
@section('title', 'Lupa Kata Sandi')

@section('content')
<div class="max-w-md mx-auto">
    <div class="erp-card space-y-4">
        <div>
            <h1 class="text-lg font-bold text-slate-800 dark:text-white">Lupa kata sandi?</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                Masukkan alamat email yang Anda pakai mendaftar. Kami kirimkan tautan untuk membuat kata sandi baru.
            </p>
        </div>

        @if(session('success'))
            <div class="p-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40">
                <p class="text-xs text-emerald-700 dark:text-emerald-400 leading-relaxed">{{ session('success') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="p-3 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/40">
                <ul class="text-xs text-red-700 dark:text-red-400 space-y-1">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="erp-label">Alamat email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="erp-input"
                       placeholder="nama@perusahaan.co.id">
            </div>

            <button type="submit" class="btn-primary w-full justify-center">Kirim Tautan</button>
        </form>

        <p class="text-center text-[11px] text-slate-500">
            Ingat kata sandinya? <a href="{{ route('login') }}" class="text-blue-500 hover:underline font-semibold">Masuk</a>
        </p>
    </div>
</div>
@endsection
