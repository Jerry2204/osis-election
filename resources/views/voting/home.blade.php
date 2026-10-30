@extends('layouts.app')
@section('title', 'Gunakan hak suaramu')
@section('content')
<div class="home-grid">
    <section class="welcome-section">
        <div class="eyebrow"><span class="tiny-line"></span> SATU SISWA, SATU SUARA</div>
        <h1>Suaramu,<br>masa depan <span class="highlight">kita.</span></h1>
        <p class="lead">Perubahan dimulai dari pilihanmu. Kenali calonnya,<br class="desktop-break"> tentukan pilihan, dan ikut bentuk masa depan sekolah.</p>
        @if($setting->school_name || $setting->osis_period || $setting->election_title)<p class="event-identity">{{ $setting->electionLabel() }}{{ $setting->osis_period ? ' · '.$setting->osis_period : '' }}</p>@endif
        @include('components.steps', ['step' => 1])
        <div class="entry-card">
            <div class="card-heading"><span class="small-icon" aria-hidden="true">↗</span><div><h2>Mulai memilih</h2><p>Siapkan Nomor Induk Siswa kamu.</p></div>
                <span class="badge {{ $setting->status === 'open' ? 'badge-open' : 'badge-neutral' }}">{{ ['preparation' => 'Belum dibuka', 'open' => 'Sedang dibuka', 'closed' => 'Sudah ditutup'][$setting->status] }}</span>
            </div>
            @if($setting->status === 'open')
                <form action="{{ route('voting.verify') }}" method="post" data-submit-once>
                    @csrf
                    <label for="nis">Nomor Induk Siswa (NIS)</label>
                    <div class="input-wrap"><span aria-hidden="true">#</span><input id="nis" name="nis" type="text" inputmode="numeric" pattern="[0-9]+" maxlength="32" placeholder="Masukkan NIS kamu" autocomplete="off" spellcheck="false" required autofocus aria-describedby="nis-help{{ ($notice ?? null) ? ' nis-error' : '' }}" @if($notice ?? null) aria-invalid="true" @endif></div>
                    @if($notice ?? null)<p id="nis-error" class="field-error" role="alert">{{ $notice }}</p>@endif
                    <p id="nis-help" class="field-help">Masukkan NIS lengkap, termasuk angka nol di awal.</p>
                    <button type="submit" class="button button-primary button-full">Lanjut pilih calon <span aria-hidden="true">→</span></button>
                </form>
            @else
                <div class="closed-message"><h3>{{ $setting->status === 'closed' ? 'Terima kasih atas partisipasinya.' : 'Sebentar lagi, giliran suaramu.' }}</h3><p>{{ $setting->status === 'closed' ? 'Pemilihan telah selesai. Pengumuman hasil akan disampaikan oleh panitia.' : 'Panitia sedang menyiapkan pemilihan. Silakan ikuti arahan petugas.' }}</p></div>
                <a class="button button-secondary button-full" href="{{ route('home') }}">Periksa status pemilihan <span aria-hidden="true">↻</span></a>
            @endif
            @if($setting->status !== 'closed' && $setting->opens_at && $setting->closes_at)
                @php($scheduleTarget = $setting->status === 'open' ? $setting->closes_at : $setting->opens_at)
                <div class="schedule-card"><span>{{ $setting->status === 'open' ? 'Pemilihan ditutup' : 'Jadwal pemilihan dimulai' }}</span><strong>{{ $scheduleTarget->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</strong><small data-schedule-countdown="{{ max(0, now()->diffInSeconds($scheduleTarget, false)) }}">{{ $setting->status === 'preparation' && $scheduleTarget->isPast() ? 'Menunggu kesiapan panitia.' : 'Ikuti jadwal dan arahan petugas.' }}</small></div>
            @endif
            <span data-election-watch data-status-url="{{ route('election.status') }}" data-current-status="{{ $setting->status }}" class="sr-only" aria-hidden="true"></span>
            <p class="privacy-note"><svg aria-hidden="true" viewBox="0 0 20 20" fill="none"><rect x="4" y="8" width="12" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 8V6a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.5"/></svg>Pilihanmu bersifat rahasia.</p>
        </div>
        <p class="need-help">Ada kendala dengan NIS? <strong>Hubungi petugas di dekatmu.</strong></p>
    </section>
    <aside class="election-poster" aria-label="Pilih dengan hati, untuk sekolah yang lebih baik">
        <div class="poster-top"><span>SUARA KITA<br>BERARTI.</span><span class="poster-star" aria-hidden="true">✳</span></div>
        <div class="ballot-art" aria-hidden="true">
            <svg viewBox="0 0 480 440" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="246" cy="222" r="164" stroke="#c8dfbc" stroke-width="1"/>
                <circle cx="246" cy="222" r="132" stroke="#c8dfbc" stroke-width="1" stroke-dasharray="4 8"/>
                <path d="m98 238 148-43 141 48-143 57-146-62Z" fill="#e5efdc" stroke="#152f2b" stroke-width="3"/>
                <path d="m98 238 146 62v122L98 354V238Z" fill="#c7dda8" stroke="#152f2b" stroke-width="3"/>
                <path d="m244 300 143-57v117l-143 62V300Z" fill="#aac684" stroke="#152f2b" stroke-width="3"/>
                <path d="m176 240 69-21 70 23-70 25-69-27Z" fill="#152f2b"/>
                <g transform="rotate(-13 242 147)"><rect x="176" y="54" width="132" height="180" rx="9" fill="#fffef6" stroke="#152f2b" stroke-width="3"/><path d="M198 81h64M198 91h39" stroke="#c6cdbf" stroke-width="4" stroke-linecap="round"/><rect x="199" y="117" width="86" height="86" rx="43" fill="#dbedb2"/><path d="m221 159 15 15 28-32" stroke="#152f2b" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/></g>
                <path d="m119 115-13-13m30-2-2-20m-31 50-20-2M352 120l16-13m-10 34 24 2M70 288l-12 9" stroke="#152f2b" stroke-width="3" stroke-linecap="round"/>
                <path d="m345 54 4 12 12 4-12 4-4 12-4-12-12-4 12-4 4-12Z" fill="#152f2b"/>
                <circle cx="99" cy="189" r="5" fill="#829765"/><circle cx="382" cy="201" r="4" fill="#829765"/>
            </svg>
        </div>
        <div class="poster-bottom"><span class="poster-pill">PILIH DENGAN HATI</span><h2>Langkah kecilmu.<br>Dampak besar kita.</h2><p>Satu pilihan untuk sekolah<br>yang lebih baik.</p></div>
        <div class="poster-index">OSIS <span>BERSAMA MENENTUKAN ARAH ↗</span></div>
    </aside>
</div>
@endsection
