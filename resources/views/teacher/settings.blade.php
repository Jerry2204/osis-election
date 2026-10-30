@extends('layouts.app')
@section('title', 'Pengaturan pemilihan')
@section('content')
<div class="page-heading"><div><div class="eyebrow">RUANG GURU / PENGATURAN</div><h1>Siapkan identitas kegiatan.</h1><p>Atur identitas sekolah, jadwal pemilihan, dan penandatangan laporan.</p></div><span class="badge badge-neutral">Waktu Indonesia Barat · WIB</span></div>
@if($scheduleNotice)<div class="alert alert-warning" role="alert">{{ $scheduleNotice }}</div>@endif
@if($setting->status !== 'preparation')<div class="notice-box">Identitas sekolah dan jadwal sudah dikunci. Nama penandatangan dan tempat laporan tetap dapat diperbarui.</div>@endif
<form method="post" enctype="multipart/form-data" action="{{ route('teacher.settings', ['teacherKey' => $teacherKey]) }}" data-submit-once>
    @csrf @method('PATCH')
    <section class="panel settings-panel">
        <div class="section-heading"><h2>Identitas sekolah</h2><span class="badge badge-neutral">{{ $setting->status === 'preparation' ? 'Dapat diubah' : 'Dikunci' }}</span></div>
        <div class="form-columns">
            <div>
                <label for="school-name">Nama sekolah</label>
                <input id="school-name" name="school_name" maxlength="120" value="{{ old('school_name', $setting->school_name) }}" placeholder="Contoh: SMA Negeri 1" @disabled($setting->status !== 'preparation') @error('school_name') aria-invalid="true" aria-describedby="school-name-error" @enderror>
                @error('school_name')<p id="school-name-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                <label for="election-title">Judul pemilihan</label>
                <input id="election-title" name="election_title" maxlength="160" value="{{ old('election_title', $setting->election_title) }}" placeholder="Pemilihan Ketua OSIS" @disabled($setting->status !== 'preparation') @error('election_title') aria-invalid="true" aria-describedby="election-title-error" @enderror>
                @error('election_title')<p id="election-title-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                <label for="osis-period">Periode OSIS</label>
                <input id="osis-period" name="osis_period" maxlength="40" value="{{ old('osis_period', $setting->osis_period) }}" placeholder="Contoh: 2026/2027" @disabled($setting->status !== 'preparation') @error('osis_period') aria-invalid="true" aria-describedby="osis-period-error" @enderror>
                @error('osis_period')<p id="osis-period-error" class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="school-logo">Logo sekolah</label>
                <input id="school-logo" name="logo" type="file" accept="image/png,image/jpeg" @disabled($setting->status !== 'preparation') aria-describedby="logo-help @error('logo') logo-error @enderror" @error('logo') aria-invalid="true" @enderror>
                <p id="logo-help" class="field-help">PNG atau JPEG, maksimal 2 MB dan 4000 × 4000 piksel. Logo disimpan di luar folder publik.</p>
                @error('logo')<p id="logo-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                @if($setting->logo_path)
                    <img class="form-photo school-logo-preview" src="{{ route('school.logo') }}" width="120" height="120" alt="Logo {{ $setting->schoolLabel() }}">
                    @if($setting->status === 'preparation')<label class="checkbox-label" for="remove-logo"><input id="remove-logo" type="checkbox" name="remove_logo" value="1"> Hapus logo saat ini</label>@endif
                @endif
                <p class="field-help">Jika nama sekolah dan judul dikosongkan, aplikasi tetap memakai Suara OSIS dan Pemilihan Ketua OSIS.</p>
            </div>
        </div>
    </section>
    <section class="panel settings-panel">
        <div class="section-heading"><h2>Jadwal pemilihan</h2><span class="badge badge-neutral">WIB · UTC+7</span></div>
        <p class="field-help">Isi kedua waktu untuk membuka dan menutup otomatis. Kosongkan keduanya untuk menggunakan tombol buka dan tutup manual. Pembukaan otomatis menunggu seluruh pemeriksaan kesiapan lengkap.</p>
        <div class="form-columns">
            <div><label for="opens-at">Tanggal dan jam buka (WIB)</label><input id="opens-at" name="opens_at" type="datetime-local" value="{{ old('opens_at', $setting->opens_at?->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i')) }}" @disabled($setting->status !== 'preparation') @error('opens_at') aria-invalid="true" aria-describedby="opens-at-error" @enderror>@error('opens_at')<p id="opens-at-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
            <div><label for="closes-at">Tanggal dan jam tutup (WIB)</label><input id="closes-at" name="closes_at" type="datetime-local" value="{{ old('closes_at', $setting->closes_at?->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i')) }}" @disabled($setting->status !== 'preparation') @error('closes_at') aria-invalid="true" aria-describedby="closes-at-error" @enderror>@error('closes_at')<p id="closes-at-error" class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        </div>
        <p class="field-help">Tombol Buka dapat memulai pemilihan lebih awal; jadwal tutup tetap berlaku. Pemilihan yang sudah ditutup tidak dapat dibuka kembali.</p>
    </section>
    <section class="panel settings-panel">
        <h2>Penandatangan laporan</h2>
        <p class="field-help">Nama dan tempat berikut tampil pada laporan hasil yang siap dicetak. Kolom yang kosong menyediakan tempat untuk diisi dengan tulisan tangan.</p>
        <div class="form-columns">
            <div>
                <label for="report-place">Tempat laporan</label><input id="report-place" name="report_place" maxlength="120" value="{{ old('report_place', $setting->report_place) }}" placeholder="Contoh: Jakarta" @error('report_place') aria-invalid="true" aria-describedby="report-place-error" @enderror>@error('report_place')<p id="report-place-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                <label for="committee-chair">Ketua panitia</label><input id="committee-chair" name="committee_chair" maxlength="120" value="{{ old('committee_chair', $setting->committee_chair) }}" @error('committee_chair') aria-invalid="true" aria-describedby="committee-chair-error" @enderror>@error('committee_chair')<p id="committee-chair-error" class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="osis-advisor">Pembina OSIS</label><input id="osis-advisor" name="osis_advisor" maxlength="120" value="{{ old('osis_advisor', $setting->osis_advisor) }}" @error('osis_advisor') aria-invalid="true" aria-describedby="osis-advisor-error" @enderror>@error('osis_advisor')<p id="osis-advisor-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                <label for="principal-name">Kepala sekolah</label><input id="principal-name" name="principal_name" maxlength="120" value="{{ old('principal_name', $setting->principal_name) }}" @error('principal_name') aria-invalid="true" aria-describedby="principal-name-error" @enderror>@error('principal_name')<p id="principal-name-error" class="field-error" role="alert">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>
    <div class="form-bottom"><p class="field-help">Pengaturan jadwal mengikuti jam server laptop ini.</p><button class="button button-primary" type="submit">Simpan pengaturan <span aria-hidden="true">→</span></button></div>
</form>
@endsection
