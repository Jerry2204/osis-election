@extends('layouts.app')
@section('title', 'Pilih calonmu')
@section('content')
@include('components.steps', ['step' => 2])
<div class="page-heading"><div><div class="eyebrow">KENALI, LALU TENTUKAN</div><h1>Siapa pilihanmu?</h1><p>Baca visi dan misinya. Pilih satu calon yang kamu percaya.</p></div><div class="session-chip">Sisa waktu <strong data-countdown="{{ max(0, $context['expires_at'] - now()->timestamp) }}">05:00</strong></div></div>
<form action="{{ route('voting.choose') }}" method="post" data-submit-once data-candidate-form>
    @csrf<input type="hidden" name="flow_token" value="{{ $context['token'] }}">
    <div class="candidate-grid">
        @foreach($candidates as $candidate)
        <label class="candidate-card selectable">
            <div class="choice-row"><span class="choice-default">Pilih calon ini</span><span class="choice-selected">✓ Terpilih</span><input type="radio" name="candidate_id" value="{{ $candidate->id }}" data-candidate-name="{{ $candidate->name }}" data-candidate-number="{{ str_pad($candidate->number, 2, '0', STR_PAD_LEFT) }}" required @checked(($context['candidate_id'] ?? null) === $candidate->id) aria-label="Pilih {{ $candidate->name }}"></div>
            @include('components.candidate')
        </label>
        @endforeach
    </div>
    <div class="action-bar selection-action"><div><p class="selection-summary" data-selection-summary aria-live="polite">Pilih satu calon untuk melanjutkan.</p><p>Pilihan masih bisa diubah sebelum dikirim.</p></div><button class="button button-primary" type="submit">Lanjut konfirmasi <span aria-hidden="true">→</span></button></div>
</form>
<form action="{{ route('voting.cancel') }}" method="post" class="cancel-form">@csrf<button class="text-button" type="submit">Batalkan dan kembali ke awal</button></form>
@endsection
