@extends('layouts.app')
@section('title', $candidate->exists ? 'Edit calon' : 'Tambah calon')
@section('content')
<a class="back-link" href="{{ route('teacher.candidates', ['teacherKey' => $teacherKey]) }}">← Kembali ke daftar calon</a>
<div class="page-heading"><div><div class="eyebrow">RUANG GURU / CALON</div><h1>{{ $candidate->exists ? 'Rapikan profil calon.' : 'Kenalkan calon baru.' }}</h1><p>Isi informasi singkat dan jelas agar siswa mudah mengenali calon.</p></div></div>
<form class="panel candidate-form" method="post" enctype="multipart/form-data" action="{{ $candidate->exists ? route('teacher.candidates.update', ['teacherKey' => $teacherKey, 'candidate' => $candidate]) : route('teacher.candidates.store', ['teacherKey' => $teacherKey]) }}" data-submit-once>
@csrf @if($candidate->exists) @method('PATCH') @endif
<div class="form-columns">
<div>
    <label for="number">Nomor urut <span>*</span></label>
    <input id="number" name="number" type="number" min="1" max="999" value="{{ old('number', $candidate->number) }}" required @error('number') aria-invalid="true" aria-describedby="number-error" @enderror>
    @include('components.field-error', ['name' => 'number'])
    <label for="name">Nama calon <span>*</span></label>
    <input id="name" name="name" maxlength="120" value="{{ old('name', $candidate->name) }}" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
    @include('components.field-error', ['name' => 'name'])
    <label for="photo">Foto calon {{ $candidate->exists ? '' : '*' }}</label>
    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" @required(! $candidate->exists) aria-describedby="photo-help{{ $errors->has('photo') ? ' photo-error' : '' }}" @error('photo') aria-invalid="true" @enderror>
    <p id="photo-help" class="field-help">JPEG atau PNG, maksimal 2 MB dan 4000 × 4000 piksel. {{ $candidate->exists ? 'Kosongkan jika foto tetap sama.' : 'Gunakan foto yang wajahnya terlihat jelas.' }}</p>
    @include('components.field-error', ['name' => 'photo'])
    @if($candidate->exists)<img class="form-photo" src="{{ route('candidate.photo', $candidate) }}" width="160" height="160" alt="Foto saat ini: {{ $candidate->name }}">@endif
</div>
<div>
    <label for="vision">Visi singkat <span>*</span></label>
    <textarea id="vision" name="vision" rows="4" maxlength="1500" required placeholder="Apa yang ingin diwujudkan untuk sekolah?" @error('vision') aria-invalid="true" aria-describedby="vision-error" @enderror>{{ old('vision', $candidate->vision) }}</textarea>
    @include('components.field-error', ['name' => 'vision'])
    <label for="mission">Misi singkat <span>*</span></label>
    <textarea id="mission" name="mission" rows="7" maxlength="3000" required placeholder="Tuliskan langkah atau program utama, satu per baris." @error('mission') aria-invalid="true" aria-describedby="mission-error" @enderror>{{ old('mission', $candidate->mission) }}</textarea>
    @include('components.field-error', ['name' => 'mission'])
    <p class="field-help">Visi dan misi ditampilkan sebagai teks biasa. Tidak perlu format HTML.</p>
</div>
</div>
<div class="form-bottom"><p class="field-help">* Wajib diisi. Data terkunci setelah pemilihan dibuka.</p><button class="button button-primary" type="submit">Simpan calon <span aria-hidden="true">→</span></button></div>
</form>
@endsection