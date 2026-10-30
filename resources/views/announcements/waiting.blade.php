@extends('layouts.announcement', ['available' => false, 'preview' => false])
@section('content')
<section class="waiting-screen">
    <span class="waiting-symbol" aria-hidden="true">✳</span>
    <p class="eyebrow">PENGUMUMAN HASIL</p>
    <h1>Pengumuman<br>belum tersedia.</h1>
    <p>Hasil akan ditampilkan setelah pemilihan selesai dan panitia mengaktifkan pengumuman.</p>
    <a class="refresh-button" href="{{ route('announcement') }}">Periksa pengumuman <span aria-hidden="true">↻</span></a>
    <p class="waiting-help">Halaman ini memeriksa ketersediaan pengumuman secara otomatis.</p>
</section>
@endsection
