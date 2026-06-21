@extends('akta.layouts.app')

@section('title', $title.' - AKTA IAT')
@section('page_title', $title)
@section('page_description', $description)

@section('content')
<section class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
    <div class="max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-wide text-blue-400">
            Modul AKTA IAT
        </p>

        <h2 class="mt-3 text-2xl font-bold">
            {{ $title }}
        </h2>

        <p class="mt-3 text-sm leading-6 text-slate-400">
            {{ $description }}
        </p>

        <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950 p-4">
            <p class="text-sm text-slate-300">
                Halaman ini sudah masuk ke struktur multi-page Blade. Isi detail modul akan kita migrasikan dari
                <span class="font-semibold text-slate-100">dashboard.html</span>
                secara bertahap.
            </p>
        </div>
    </div>
</section>
@endsection