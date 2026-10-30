@extends('layouts.app')
@section('title', 'Ringkasan pemilihan')
@section('content')
<div class="page-heading"><div><div class="eyebrow">RUANG GURU</div><h1>Siapkan pilihan terbaik.</h1><p>Kelola persiapan dan jalannya pemilihan dari satu tempat.</p></div><span class="badge {{ $setting->status === 'open' ? 'badge-open' : 'badge-neutral' }}">{{ ['preparation' => 'Tahap persiapan', 'open' => 'Pemilihan dibuka', 'closed' => 'Pemilihan ditutup'][$setting->status] }}</span></div>
<div class="stats-grid"><div class="stat-card"><span>Siswa terdaftar</span><strong>{{ number_format($registered, 0, ',', '.') }}</strong><a href="{{ route('teacher.students', ['teacherKey' => $teacherKey]) }}">Lihat data siswa →</a></div><div class="stat-card"><span>Calon ketua OSIS</span><strong>{{ $candidateCount }}</strong><a href="{{ route('teacher.candidates', ['teacherKey' => $teacherKey]) }}">Lihat daftar calon →</a></div><div class="stat-card"><span>Sudah memilih</span><strong>{{ number_format($participated, 0, ',', '.') }}</strong><small>Perolehan suara dibuka setelah selesai</small></div></div>
<div class="dashboard-grid"><section class="panel"><h2>{{ $setting->status === 'preparation' ? 'Pemeriksaan kesiapan' : 'Status pemilihan' }}</h2>
@if($setting->status === 'preparation')
    <ul class="readiness-list">@foreach($readiness as $label => $ready)<li><span class="readiness-icon {{ $ready ? 'ready' : '' }}" aria-hidden="true">{{ $ready ? '✓' : '○' }}</span><span>{{ $label }}</span><small>{{ $ready ? 'Siap' : 'Belum siap' }}</small></li>@endforeach</ul>
    <p class="field-help">Impor siswa melalui menu Data siswa. Lengkapi calon melalui menu Kelola calon.</p>
@elseif($setting->status === 'open')
    <div class="notice-box">Pemilihan sedang berlangsung. Data siswa dan calon dikunci. Rekap per calon belum tersedia.</div><p>Siswa dapat membuka halaman pemilihan dari ketiga laptop. Pastikan setiap pergantian siswa kembali ke halaman awal.</p>
@else
    <div class="notice-box">Pemilihan telah selesai. Pengiriman suara dan perubahan calon tidak tersedia.</div><a class="button button-primary" href="{{ route('teacher.results', ['teacherKey' => $teacherKey]) }}">Lihat rekap hasil →</a>
@endif
</section><section class="panel control-panel"><h2>{{ $setting->status === 'preparation' ? 'Buka pemilihan' : ($setting->status === 'open' ? 'Tutup pemilihan' : 'Arsipkan hasil') }}</h2>
@if($setting->status !== 'closed')
    <p>{{ $setting->status === 'preparation' ? 'Setelah dibuka, data siswa dan calon tidak dapat diubah. Pastikan backup awal sudah dibuat.' : 'Setelah ditutup, pemilihan tidak dapat dibuka kembali. Pastikan seluruh siswa sudah mendapat kesempatan.' }}</p>
    <form method="post" action="{{ route($setting->status === 'preparation' ? 'teacher.open' : 'teacher.close', ['teacherKey' => $teacherKey]) }}" data-submit-once>@csrf
        <label for="confirmation">Ketik {{ $setting->status === 'preparation' ? 'BUKA' : 'TUTUP' }} untuk konfirmasi</label><input id="confirmation" name="confirmation" type="text" required pattern="{{ $setting->status === 'preparation' ? 'BUKA' : 'TUTUP' }}" autocomplete="off" placeholder="{{ $setting->status === 'preparation' ? 'BUKA' : 'TUTUP' }}">
        <button type="submit" class="button {{ $setting->status === 'preparation' ? 'button-primary' : 'button-danger' }} button-full" @disabled($setting->status === 'preparation' && in_array(false, $readiness, true))>{{ $setting->status === 'preparation' ? 'Buka pemilihan' : 'Tutup pemilihan' }}</button>
    </form>
@else
    <p>Unduh rekap dan buat backup database final melalui laptop server. Simpan salinannya pada media terpisah.</p><a class="button button-secondary" href="{{ route('teacher.export', ['teacherKey' => $teacherKey]) }}">Unduh rekap CSV ↓</a>
@endif
</section></div>
@endsection
