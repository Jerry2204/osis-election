@extends('layouts.app')
@section('title', 'Pratinjau impor siswa')
@section('content')
<a class="back-link" href="{{ route('teacher.students', ['teacherKey' => $teacherKey]) }}">&larr; Kembali ke data siswa</a>
<div class="page-heading">
    <div><div class="eyebrow">RUANG GURU / IMPOR SISWA</div><h1>Periksa sebelum disimpan.</h1><p>Berkas {{ $preview['name'] }} &middot; Pratinjau berlaku sampai {{ \Illuminate\Support\Carbon::createFromTimestamp($preview['expires_at'])->timezone('Asia/Jakarta')->format('H:i') }} WIB.</p></div>
    <span class="badge badge-neutral">Belum ada data yang disimpan</span>
</div>
<div class="stats-grid">
    <div class="stat-card"><span>Baris siswa</span><strong>{{ number_format($inspection['records'], 0, ',', '.') }}</strong><small>Tidak termasuk header dan baris kosong</small></div>
    <div class="stat-card"><span>Baris valid</span><strong>{{ number_format($inspection['valid_count'], 0, ',', '.') }}</strong><small>Lolos pemeriksaan format dan NIS</small></div>
    <div class="stat-card"><span>Baris perlu diperbaiki</span><strong>{{ number_format($inspection['invalid_count'], 0, ',', '.') }}</strong><small>Seluruh impor harus bebas kesalahan</small></div>
</div>
@if($inspection['error_count'])
    <div class="alert alert-warning" role="alert">
        <strong>Impor belum dapat disimpan.</strong> Perbaiki berkas sumber, lalu unggah kembali. Data lama tidak akan ditimpa.
        <ul>@foreach($inspection['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
        @if($inspection['error_count'] > count($inspection['errors']))<p>Menampilkan {{ count($inspection['errors']) }} dari {{ number_format($inspection['error_count'], 0, ',', '.') }} masalah yang ditemukan.</p>@endif
    </div>
@else
    <div class="alert alert-success" role="status">Seluruh baris lolos pemeriksaan. Periksa contoh data di bawah sebelum menyimpan. NIS dan kesiapan pemilihan akan diperiksa kembali saat konfirmasi.</div>
@endif
<section class="panel">
    <div class="section-heading"><h2>Pratinjau data</h2><span class="badge badge-neutral">{{ count($inspection['sample']) }} baris pertama</span></div>
    @if($inspection['sample'])
        <div class="table-scroll"><table class="results-table students-table">
            <caption class="sr-only">Contoh data siswa dari CSV yang akan diimpor</caption>
            <thead><tr><th scope="col">Baris</th><th scope="col">NIS</th><th scope="col">Nama siswa</th><th scope="col">Kelas</th><th scope="col">Pemeriksaan</th></tr></thead>
            <tbody>@foreach($inspection['sample'] as $row)<tr><td>{{ $row['line'] }}</td><td>{{ $row['nis'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['class_name'] }}</td><td>{{ $row['error'] ?? 'Valid' }}</td></tr>@endforeach</tbody>
        </table></div>
    @else
        <p class="field-help">Tidak ada baris siswa untuk ditampilkan.</p>
    @endif
    <p class="field-help">Pratinjau menampilkan maksimal 20 baris pertama. Seluruh berkas telah diperiksa, termasuk NIS yang sudah terdaftar. Batas 10 MB dan 20.000 siswa tetap berlaku.</p>
    <div class="form-bottom">
        <form method="post" action="{{ route('teacher.students.cancel', ['teacherKey' => $teacherKey]) }}" data-submit-once>@csrf<button type="submit" class="button button-secondary">Batalkan impor</button></form>
        <form method="post" action="{{ route('teacher.students.confirm', ['teacherKey' => $teacherKey]) }}" data-submit-once>
            @csrf<input type="hidden" name="import_token" value="{{ $preview['token'] }}">
            <button type="submit" class="button button-primary" @disabled(! $inspection['can_import'])>Simpan impor <span aria-hidden="true">&check;</span></button>
            <p class="submission-status" role="status" hidden>Sedang memeriksa ulang dan menyimpan seluruh data siswa…</p>
        </form>
    </div>
</section>
@endsection
