@extends('layouts.app')
@section('title', 'Suara berhasil disimpan')
@section('content')
<section class="success-panel panel"><span class="success-icon" aria-hidden="true">✓</span><div class="eyebrow">SUARAMU SUDAH TERCATAT</div><h1>Terima kasih<br>sudah ikut memilih!</h1><p>Satu suara darimu berarti untuk masa depan sekolah.<br>Silakan beri giliran kepada siswa berikutnya.</p><a class="button button-primary" href="{{ route('home') }}">Siswa berikutnya <span aria-hidden="true">→</span></a><p class="field-help" data-return-home="{{ route('home') }}">Kembali ke halaman awal dalam <span data-return-seconds>5</span> detik.</p></section>
@endsection
