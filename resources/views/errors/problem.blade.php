@extends('layouts.app')
@section('title', 'Informasi aplikasi')
@section('content')
<section class="success-panel panel"><div class="eyebrow">INFORMASI APLIKASI · {{ $code }}</div><h1>Periksa sebentar, ya.</h1><p>{{ $message }}</p><a class="button button-primary" href="{{ route('home') }}">Kembali ke halaman awal →</a></section>
@endsection
