@extends('layouts.app')
@section('title', 'Data siswa')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">RUANG GURU</div><h1>Data siswa.</h1><p>Daftarkan siswa sekaligus melalui berkas CSV sebelum pemilihan dibuka.</p></div>
    <span class="badge badge-neutral">{{ number_format($students->total(), 0, ',', '.') }} siswa terdaftar</span>
</div>
<div class="dashboard-grid student-import-grid">
    <section class="panel control-panel">
        <h2>Impor siswa dari CSV</h2>
        @if($setting->status === 'preparation')
            <p>Data baru ditambahkan ke daftar siswa. Jika ada isian salah atau NIS duplikat, seluruh impor dibatalkan.</p>
            <form method="post" action="{{ route('teacher.students.import', ['teacherKey' => $teacherKey]) }}" enctype="multipart/form-data" data-submit-once>
                @csrf
                <label for="student-file">Berkas siswa <span>(.csv)</span></label>
                <input id="student-file" name="file" type="file" accept=".csv,text/csv" required aria-describedby="student-file-help">
                <p id="student-file-help" class="field-help">CSV UTF-8, pemisah koma. Maksimal 10 MB dan 20.000 siswa per berkas.</p>
                <button class="button button-primary button-full" type="submit">Impor siswa <span aria-hidden="true">↑</span></button>
                <p class="submission-status" role="status" hidden>Sedang memeriksa dan mengimpor data siswa…</p>
            </form>
        @else
            <div class="notice-box">Data siswa sudah dikunci karena pemilihan {{ $setting->status === 'open' ? 'sedang berlangsung' : 'telah ditutup' }}. Impor tidak tersedia.</div>
        @endif
    </section>
    <section class="panel control-panel">
        <h2>Siapkan berkas siswa</h2>
        <p>Unduh template kosong, lalu isi satu siswa pada setiap baris. Pertahankan urutan header <strong>nis,nama,kelas</strong>.</p>
        <a class="button button-secondary" href="{{ route('teacher.students.template', ['teacherKey' => $teacherKey]) }}">Unduh template CSV <span aria-hidden="true">↓</span></a>
        <div class="table-scroll">
            <table class="results-table students-table">
                <caption class="sr-only">Contoh pengisian CSV, bukan data siswa resmi</caption>
                <thead><tr><th scope="col">nis</th><th scope="col">nama</th><th scope="col">kelas</th></tr></thead>
                <tbody><tr><td>001001</td><td>Alya Putri</td><td>X A</td></tr><tr><td>001002</td><td>Bima Pratama</td><td>X B</td></tr></tbody>
            </table>
        </div>
        <p class="field-help">Contoh di atas hanya menunjukkan format. NIS wajib berupa angka, maksimal 32 karakter; nama maksimal 120 karakter dan kelas 60 karakter. Di Excel, atur kolom NIS sebagai Text sebelum mengisi agar angka nol di awal tetap ada. Simpan sebagai CSV UTF-8 dengan pemisah koma.</p>
    </section>
</div>
<section class="panel">
    <div class="section-heading"><h2>Siswa terdaftar</h2><span class="badge badge-neutral">{{ number_format($students->total(), 0, ',', '.') }} siswa</span></div>
    @if($students->isEmpty())
        <p class="field-help">{{ $students->total() === 0 ? 'Belum ada data siswa. Unduh template, isi data siswa, lalu unggah berkas CSV di atas.' : 'Tidak ada siswa pada halaman ini.' }}</p>
    @else
        <div class="table-scroll">
            <table class="results-table students-table">
                <caption class="sr-only">Daftar siswa yang terdaftar untuk pemilihan</caption>
                <thead><tr><th scope="col">NIS</th><th scope="col">Nama siswa</th><th scope="col">Kelas</th></tr></thead>
                <tbody>@foreach($students as $student)<tr><td>{{ $student->nis }}</td><td>{{ $student->name }}</td><td>{{ $student->class_name }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    @endif
    @if($students->hasPages())
        <nav class="form-bottom" aria-label="Halaman daftar siswa">
            <p class="field-help">Halaman {{ $students->currentPage() }} dari {{ $students->lastPage() }} · 50 siswa per halaman</p>
            <div class="student-pagination">
                @if($students->onFirstPage())<span class="field-help">Sebelumnya</span>@else<a class="button button-secondary" href="{{ $students->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
                @if($students->hasMorePages())<a class="button button-secondary" href="{{ $students->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span class="field-help">Berikutnya</span>@endif
            </div>
        </nav>
    @endif
</section>
@endsection
