@extends('layouts.app')
@section('title', 'Konfirmasi pilihan')
@section('content')
@include('components.steps', ['step' => 3])
<div class="page-heading"><div><div class="eyebrow">SATU LANGKAH LAGI</div><h1>Sudah yakin?</h1><p>Pastikan calon di bawah ini adalah pilihanmu.</p></div><div class="session-chip">Sisa waktu <strong data-countdown="{{ max(0, $context['expires_at'] - now()->timestamp) }}">05:00</strong></div></div>
<div class="confirmation-layout">
    <article class="candidate-card confirmation-candidate">@include('components.candidate')</article>
    <section class="panel confirmation-panel"><span class="large-check" aria-hidden="true">✓</span><h2>Kamu akan memilih<br>{{ $candidate->name }}</h2><p>Calon nomor urut <strong>{{ str_pad($candidate->number, 2, '0', STR_PAD_LEFT) }}</strong></p><div class="notice-box">Setelah dikirim, suara tidak dapat diubah. Pilihanmu disimpan secara rahasia.</div>
        <form action="{{ route('voting.store') }}" method="post" data-submit-once data-vote-form>@csrf<input type="hidden" name="flow_token" value="{{ $context['token'] }}"><button class="button button-primary button-full" type="submit">Ya, kirim suara <span aria-hidden="true">✓</span></button><p class="submission-status" role="status" hidden>Mengirim suara. Tunggu hingga ada konfirmasi.</p></form>
        <a class="button button-secondary button-full" href="{{ route('voting.candidates') }}">Kembali, ubah pilihan</a>
    </section>
</div>
<form action="{{ route('voting.cancel') }}" method="post" class="cancel-form">@csrf<button class="text-button" type="submit">Batalkan pemilihan</button></form>
@endsection
