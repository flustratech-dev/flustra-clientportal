@extends('layouts.app')
@section('title', 'Notifikasi')
@section('page_title', 'Notifikasi')

@section('content')
<div class="space-y-4 max-w-2xl">

    @if($notifications->isNotEmpty())
        <form action="{{ route('notifikasi.read-all') }}" method="POST" class="flex justify-end">
            @csrf
            <button type="submit" class="btn-secondary">Tandai semua terbaca</button>
        </form>
    @endif

    @forelse($notifications as $n)
        <a href="{{ $n->url ?: route('beranda') }}"
           class="erp-card block hover:border-blue-300 dark:hover:border-blue-700 transition-colors {{ $n->read_at ? '' : 'border-blue-200 dark:border-blue-800' }}">
            <div class="flex gap-3">
                <span class="w-2 h-2 rounded-full mt-1.5 shrink-0 {{ $n->read_at ? 'bg-slate-300 dark:bg-slate-600' : $n->icon_color }}"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-slate-800 dark:text-white">{{ $n->title }}</p>
                    @if($n->body)
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">{{ $n->body }}</p>
                    @endif
                    <p class="text-[10px] text-slate-400 mt-1.5">{{ $n->created_at->diffForHumans() }}</p>
                </div>
            </div>
        </a>
    @empty
        <div class="erp-card text-center py-14">
            <svg class="w-11 h-11 mx-auto text-slate-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1h6z"/>
            </svg>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Belum ada notifikasi</p>
            <p class="text-xs text-slate-400 mt-1">Kabar tentang pengajuan Anda akan muncul di sini.</p>
        </div>
    @endforelse

    <div>{{ $notifications->links() }}</div>
</div>
@endsection
