@extends('layouts.app')
@section('title', 'Ringkasan pemilihan')
@section('content')
<div data-turnout data-progress-url="{{ route('teacher.progress', ['teacherKey' => $teacherKey]) }}" data-current-status="{{ $setting->status }}">
<div class="page-heading"><div><div class="eyebrow">RUANG GURU</div><h1>{{ $setting->status === 'preparation' ? 'Siapkan pilihan terbaik.' : ($setting->status === 'open' ? 'Pantau partisipasi siswa.' : 'Pemilihan telah selesai.') }}</h1><p>{{ $setting->electionLabel() }}{{ $setting->osis_period ? ' · '.$setting->osis_period : '' }}. Kelola jalannya pemilihan dari satu tempat.</p></div><span class="badge {{ $setting->status === 'open' ? 'badge-open' : 'badge-neutral' }}">{{ ['preparation' => 'Tahap persiapan', 'open' => 'Pemilihan dibuka', 'closed' => 'Pemilihan ditutup'][$setting->status] }}</span></div>
<div class="stats-grid stats-four">
    <div class="stat-card"><span>Siswa terdaftar</span><strong data-metric="registered">{{ number_format($turnout['registered'], 0, ',', '.') }}</strong><a href="{{ route('teacher.students', ['teacherKey' => $teacherKey]) }}">Lihat data siswa →</a></div>
    <div class="stat-card"><span>Sudah memilih</span><strong data-metric="participated">{{ number_format($turnout['participated'], 0, ',', '.') }}</strong><a href="{{ route('teacher.students', ['teacherKey' => $teacherKey, 'participation' => 'voted']) }}">Lihat partisipasi →</a></div>
    <div class="stat-card"><span>Belum memilih</span><strong data-metric="remaining">{{ number_format($turnout['remaining'], 0, ',', '.') }}</strong><a href="{{ route('teacher.students', ['teacherKey' => $teacherKey, 'participation' => 'pending']) }}">Lihat siswa tersisa →</a></div>
    <div class="stat-card accent-stat"><span>Partisipasi</span><strong data-metric="percentage">{{ number_format($turnout['percentage'], 1, ',', '.') }}%</strong><small>Persentase siswa yang memilih</small></div>
</div>
@if($scheduleNotice)<div class="alert alert-warning" role="status">{{ $scheduleNotice }}</div>@endif
<div class="dashboard-grid">
<section class="panel"><h2>{{ $setting->status === 'preparation' ? 'Pemeriksaan kesiapan' : 'Status pemilihan' }}</h2>
@if($setting->status === 'preparation')
    <ul class="readiness-list">@foreach($readiness as $label => $ready)<li><span class="readiness-icon {{ $ready ? 'ready' : '' }}" aria-hidden="true">{{ $ready ? '✓' : '○' }}</span><span>{{ $label }}</span><small>{{ $ready ? 'Siap' : 'Belum siap' }}</small></li>@endforeach</ul>
    <div class="heading-actions"><a class="button button-secondary" href="{{ route('teacher.students', ['teacherKey' => $teacherKey]) }}">Siapkan siswa</a><a class="button button-secondary" href="{{ route('teacher.candidates', ['teacherKey' => $teacherKey]) }}">Kelola {{ $candidateCount }} calon</a></div>
    <p class="field-help">Lengkapi data siswa dan minimal dua calon beserta fotonya. Suara dan partisipasi harus kosong; bila tidak, hubungi petugas teknis.</p>
@elseif($setting->status === 'open')
    <div class="notice-box">Pemilihan sedang berlangsung. Data siswa dan calon dikunci. Perolehan suara sementara dapat dipantau guru di bawah; rekap akhir tersedia setelah pemilihan ditutup.</div><p>Pastikan setiap pergantian siswa kembali ke halaman awal. Progres partisipasi diperbarui otomatis setiap 10 detik.</p>
@else
    <div class="notice-box">Pemilihan telah selesai. Seluruh pilihan individu tetap rahasia.</div><a class="button button-primary" href="{{ route('teacher.results', ['teacherKey' => $teacherKey]) }}">Lihat rekap hasil →</a>
@endif
@if($setting->opens_at && $setting->closes_at)<div class="schedule-card"><span>Jadwal pemilihan · WIB</span><strong>{{ $setting->opens_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} — {{ $setting->closes_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</strong></div>@else<p class="field-help">@if($setting->status === 'preparation')Tanpa jadwal otomatis. <a class="inline-link" href="{{ route('teacher.settings', ['teacherKey' => $teacherKey]) }}">Atur jadwal dan identitas sekolah →</a>@else Pemilihan menggunakan kontrol manual. @endif</p>@endif
</section>
<section class="panel control-panel"><h2>{{ $setting->status === 'preparation' ? 'Buka pemilihan' : ($setting->status === 'open' ? 'Tutup pemilihan' : 'Arsipkan hasil') }}</h2>
@if($setting->status !== 'closed')
    <p>{{ $setting->status === 'preparation' ? 'Setelah dibuka, data siswa dan calon tidak dapat diubah. Pastikan backup awal sudah dibuat. Jika ada jadwal, tombol ini membuka pemilihan lebih awal.' : 'Tutup pemilihan untuk melihat rekap. Guru dapat membuka kembali bagi siswa yang belum memilih; seluruh suara yang sudah masuk tetap tersimpan.' }}</p>
    <form method="post" action="{{ route($setting->status === 'preparation' ? 'teacher.open' : 'teacher.close', ['teacherKey' => $teacherKey]) }}" data-submit-once>@csrf
        <label for="confirmation">Ketik {{ $setting->status === 'preparation' ? 'BUKA' : 'TUTUP' }} untuk konfirmasi</label><input id="confirmation" name="confirmation" type="text" required pattern="{{ $setting->status === 'preparation' ? 'BUKA' : 'TUTUP' }}" autocomplete="off" placeholder="{{ $setting->status === 'preparation' ? 'BUKA' : 'TUTUP' }}" aria-describedby="confirmation-error" @if($errors->has('confirmation')) aria-invalid="true" @endif>
        @include('components.field-error', ['name' => 'confirmation'])
        <button type="submit" class="button {{ $setting->status === 'preparation' ? 'button-primary' : 'button-danger' }} button-full" @disabled($setting->status === 'preparation' && in_array(false, $readiness, true))>{{ $setting->status === 'preparation' ? 'Buka pemilihan' : 'Tutup pemilihan' }}</button>
    </form>
@else
    <p>Unduh laporan hasil. Buat backup database final melalui terminal server dan simpan salinannya pada media terpisah.</p><div class="heading-actions"><a class="button button-primary" href="{{ route('teacher.report.pdf', ['teacherKey' => $teacherKey]) }}">Unduh PDF ↓</a><a class="button button-secondary" href="{{ route('teacher.export', ['teacherKey' => $teacherKey]) }}">Unduh CSV ↓</a></div>
@endif
</section>
</div>
@if($setting->status === 'closed')@include('components.reopen-election')@endif
@include('components.vote-tally')
<section class="panel turnout-panel"><div class="section-heading"><h2>Partisipasi per kelas</h2><span class="badge badge-neutral">Pilihan individu tetap rahasia</span></div>
    <progress class="turnout-progress" data-overall-progress value="{{ $turnout['percentage'] }}" max="100" aria-label="Persentase partisipasi seluruh siswa">{{ $turnout['percentage'] }}%</progress>
    <p class="field-help" data-progress-message role="status">Data saat halaman dimuat.@if($setting->status === 'open') Diperbarui setiap 10 detik selama pemilihan berlangsung.@endif</p>
    <div class="table-scroll"><table class="results-table students-table turnout-table"><thead><tr><th scope="col">Kelas</th><th scope="col">Terdaftar</th><th scope="col">Sudah memilih</th><th scope="col">Belum memilih</th><th scope="col">Partisipasi</th></tr></thead><tbody data-class-progress>@forelse($turnout['classes'] as $class)<tr><th scope="row">{{ $class['class_name'] }}</th><td>{{ $class['registered'] }}</td><td>{{ $class['participated'] }}</td><td>{{ $class['remaining'] }}</td><td>{{ number_format($class['percentage'], 1, ',', '.') }}%</td></tr>@empty<tr><td colspan="5">Belum ada data siswa. Impor siswa untuk melihat progres per kelas.</td></tr>@endforelse</tbody></table></div>
</section>
</div>
@endsection
