@extends('layouts.app')
@section('title', 'Data siswa')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">RUANG GURU</div><h1>Data siswa.</h1><p>Siapkan daftar pemilih dan pantau partisipasi tanpa melihat pilihan individu.</p></div>
    <span class="badge badge-neutral">{{ number_format($registered, 0, ',', '.') }} siswa terdaftar</span>
</div>
<div class="dashboard-grid student-import-grid">
    <section class="panel control-panel">
        <h2>Impor siswa dari CSV</h2>
        @if($setting->status === 'preparation')
            <p>Periksa pratinjau terlebih dahulu. Data siswa baru disimpan setelah Anda menekan Simpan impor pada langkah berikutnya.</p>
            <form method="post" action="{{ route('teacher.students.import', ['teacherKey' => $teacherKey]) }}" enctype="multipart/form-data" data-submit-once>
                @csrf
                <label for="student-file">Berkas siswa <span>(.csv)</span></label>
                <input id="student-file" name="file" type="file" accept=".csv,text/csv" required aria-invalid="{{ $errors->has('file') ? 'true' : 'false' }}" aria-describedby="student-file-help{{ $errors->has('file') ? ' student-file-error' : '' }}">
                <p id="student-file-help" class="field-help">CSV UTF-8, pemisah koma. Maksimal 10 MB dan 20.000 siswa per berkas.</p>
                @error('file')<p id="student-file-error" class="field-error">{{ $message }}</p>@enderror
                <button class="button button-primary button-full" type="submit">Periksa dan pratinjau <span aria-hidden="true">&rarr;</span></button>
                <p class="submission-status" role="status" hidden>Sedang memeriksa berkas CSV. Belum ada siswa yang disimpan.</p>
            </form>
        @else
            <div class="notice-box">Data siswa sudah dikunci karena pemilihan {{ $setting->status === 'open' ? 'sedang berlangsung' : 'telah ditutup' }}. Impor tidak tersedia.</div>
        @endif
    </section>
    <section class="panel control-panel">
        <h2>Siapkan berkas siswa</h2>
        <p>Unduh template kosong, lalu isi satu siswa pada setiap baris. Pertahankan urutan header <strong>nis,nama,kelas</strong>.</p>
        <a class="button button-secondary" href="{{ route('teacher.students.template', ['teacherKey' => $teacherKey]) }}">Unduh template CSV <span aria-hidden="true">&darr;</span></a>
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
    <div class="section-heading"><h2>Siswa terdaftar</h2><span class="badge badge-neutral">{{ number_format($students->total(), 0, ',', '.') }} hasil</span></div>
    <form class="student-filters" method="get" action="{{ route('teacher.students', ['teacherKey' => $teacherKey]) }}" role="search" aria-label="Cari dan filter siswa">
        <div class="student-filter-field">
            <label for="student-search">Cari NIS atau nama</label>
            <input id="student-search" type="search" name="q" value="{{ $filters['q'] }}" maxlength="120" placeholder="Contoh: 001 atau Alya" aria-invalid="{{ $errors->has('q') ? 'true' : 'false' }}" aria-describedby="student-search-help{{ $errors->has('q') ? ' student-search-error' : '' }}">
            @error('q')<p id="student-search-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="student-filter-field">
            <label for="student-class">Kelas</label>
            <select id="student-class" name="class" aria-invalid="{{ $errors->has('class') ? 'true' : 'false' }}" @if($errors->has('class')) aria-describedby="student-class-error" @endif><option value="">Semua kelas</option>@foreach($classes as $class)<option value="{{ $class }}" @selected($filters['class'] === $class)>{{ $class }}</option>@endforeach</select>
            @error('class')<p id="student-class-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="student-filter-field">
            <label for="student-participation">Status partisipasi</label>
            <select id="student-participation" name="participation" aria-invalid="{{ $errors->has('participation') ? 'true' : 'false' }}" @if($errors->has('participation')) aria-describedby="student-participation-error" @endif><option value="all" @selected($filters['participation'] === 'all')>Semua siswa</option><option value="voted" @selected($filters['participation'] === 'voted')>Sudah memilih</option><option value="pending" @selected($filters['participation'] === 'pending')>Belum memilih</option></select>
            @error('participation')<p id="student-participation-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="student-filter-actions"><button type="submit" class="button button-primary">Tampilkan</button><a class="button button-secondary" href="{{ route('teacher.students', ['teacherKey' => $teacherKey]) }}">Reset filter</a></div>
        <p id="student-search-help" class="field-help student-filter-help">Pencarian nama tidak membedakan huruf besar dan kecil.</p>
    </form>
    @if($students->isEmpty())
        <div class="student-empty-state"><h3>{{ $registered === 0 ? 'Belum ada data siswa.' : 'Siswa tidak ditemukan.' }}</h3><p>{{ $registered === 0 ? 'Unduh template, isi data siswa, lalu unggah berkas CSV di atas.' : 'Tidak ada siswa yang sesuai dengan pencarian atau halaman ini. Coba ubah atau reset filter.' }}</p></div>
    @else
        <div class="table-scroll">
            <table class="results-table students-table">
                <caption class="sr-only">Daftar siswa yang terdaftar untuk pemilihan beserta status partisipasi</caption>
                <thead><tr><th scope="col">NIS</th><th scope="col">Nama siswa</th><th scope="col">Kelas</th><th scope="col">Partisipasi</th></tr></thead>
                <tbody>@foreach($students as $student)<tr><td>{{ $student->nis }}</td><td>{{ $student->name }}</td><td>{{ $student->class_name }}</td><td><span class="badge {{ $student->participation_exists ? 'badge-open' : 'badge-neutral' }}">{{ $student->participation_exists ? 'Sudah memilih' : 'Belum memilih' }}</span></td></tr>@endforeach</tbody>
            </table>
        </div>
    @endif
    @if($students->hasPages())
        <nav class="form-bottom" aria-label="Halaman daftar siswa">
            <p class="field-help">Halaman {{ $students->currentPage() }} dari {{ $students->lastPage() }} &middot; 50 siswa per halaman</p>
            <div class="student-pagination">
                @if($students->onFirstPage())<span class="field-help">Sebelumnya</span>@else<a class="button button-secondary" href="{{ $students->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
                @if($students->hasMorePages())<a class="button button-secondary" href="{{ $students->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span class="field-help">Berikutnya</span>@endif
            </div>
        </nav>
    @endif
    <p class="field-help student-privacy-note">Status hanya menunjukkan apakah siswa sudah menggunakan hak suara. Pilihan calon setiap siswa tetap rahasia.</p>
</section>
@endsection
