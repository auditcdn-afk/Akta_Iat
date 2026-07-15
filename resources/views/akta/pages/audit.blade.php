@extends('akta.layouts.app')

@section('title', 'Audit - SIMPAS-IAT')
@section('page_title', 'Audit')
@section('page_description', 'Plan audit yang siap dikerjakan — klik Mulai Audit untuk memulai')

@section('content')
<section class="space-y-5">
    @include('akta.pages.audit.plan-list')

    {{-- ── Section Pemeriksaan (muncul setelah plan dipilih) ── --}}
    <div id="pemeriksaanSection" class="hidden space-y-4">
        @include('akta.pages.audit.pemeriksaan-header')
        @include('akta.pages.audit.tabs-nav')

        {{-- Panel: Pemeriksaan Kas --}}
        @include('akta.pages.audit.tab-kas')

        {{-- Panel: SMH --}}
        @include('akta.pages.audit.tab-smh')

        {{-- Modal: Tambah Manual SMH --}}
        @include('akta.pages.audit.modal-smh-manual')

        {{-- Panel: Perlengkapan di luar SMH --}}
        @include('akta.pages.audit.tab-perlengkapan')

        {{-- Panel: Plafon --}}
        @include('akta.pages.audit.tab-plafon')

        {{-- Panel: Materai --}}
        @include('akta.pages.audit.tab-materai')

        {{-- Panel: BPKB Onhand --}}
        @include('akta.pages.audit.tab-bpkb')

        {{-- Panel: BPKB Inproses --}}
        @include('akta.pages.audit.tab-bpkb-inproses')

        {{-- Panel: Kwitansi Gantung --}}
        @include('akta.pages.audit.tab-kwitansi')

        {{-- Panel: Piutang Reguler --}}
        @include('akta.pages.audit.tab-piutang-reguler')

        {{-- Panel: Piutang CDN --}}
        @include('akta.pages.audit.tab-piutang-cdn')

        {{-- Panel: Cek Fisik --}}
        @include('akta.pages.audit.tab-cek-fisik')

        {{-- Panel: TTP Gantung --}}
        @include('akta.pages.audit.tab-ttp-gantung')

        {{-- Panel: MT --}}
        @include('akta.pages.audit.tab-mt')

        {{-- Panel: HGP & AHM Oils --}}
        @include('akta.pages.audit.tab-hgp')

        {{-- Panel: HGA (Accessories) --}}
        @include('akta.pages.audit.tab-hga')

        {{-- Panel: SMH Tarikan --}}
        @include('akta.pages.audit.tab-smh-tarikan')

        {{-- Panel: Grading --}}
        @include('akta.pages.audit.tab-grading')

        {{-- Panel: PICA --}}
        @include('akta.pages.audit.tab-pica')

        {{-- Panel: Lampiran --}}
        @include('akta.pages.audit.tab-lampiran')

        {{-- Panel: Rekomendasi --}}
        @include('akta.pages.audit.tab-rekomendasi')

        {{-- Panel: BU Performance --}}
        @include('akta.pages.audit.tab-bu-performance')

        {{-- Panel: Bank --}}
        @include('akta.pages.audit.tab-bank')
    </div>
</section>

{{-- Modal Detail Plan --}}
@include('akta.pages.audit.modal-detail-plan')

{{-- Modal: PICA per item grading (di luar semua panel agar fixed benar-benar ke viewport) --}}
@include('akta.pages.audit.modal-grading-pica')

@endsection

@include('akta.pages.audit.modal-isi-step')

@push('scripts')
@vite('resources/js/akta-audit.js')
@endpush
