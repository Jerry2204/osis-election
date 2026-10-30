@extends('layouts.announcement', ['available' => true])
@section('content')
<section class="announcement-heading">
    <div><p class="eyebrow"><span class="status-dot" aria-hidden="true"></span> HASIL AKHIR PEMILIHAN</p><h1>{{ $setting->electionLabel() }}</h1><p class="heading-caption">Setiap suara berarti. Terima kasih sudah ikut menentukan arah sekolah.</p></div>
    @if($setting->closed_at)<p class="closed-time">Pemilihan ditutup<strong>{{ $setting->closed_at->timezone('Asia/Jakarta')->format('d/m/Y · H:i') }} WIB</strong></p>@endif
</section>
<section class="announcement-totals" aria-label="Ringkasan partisipasi">
    <div><span>Total suara</span><strong>{{ number_format($votes, 0, ',', '.') }}</strong></div>
    <div><span>Sudah memilih</span><strong>{{ number_format($participated, 0, ',', '.') }}<small> / {{ number_format($registered, 0, ',', '.') }} siswa</small></strong></div>
    <div class="turnout-total"><span>Partisipasi</span><strong>{{ number_format($participationPercentage, 1, ',', '.') }}<small>%</small></strong></div>
</section>
@if($leaders->count() > 1)<div class="tie-notice" role="status"><strong>Perolehan suara tertinggi sama.</strong> {{ $leaders->pluck('name')->implode(', ') }} memperoleh {{ number_format($highest, 0, ',', '.') }} suara masing-masing. Penetapan hasil mengikuti ketentuan panitia.</div>@endif
@if($votes === 0)<div class="tie-notice"><strong>Belum ada suara tercatat.</strong> Tidak ada calon dengan perolehan tertinggi.</div>@endif
<section class="results-grid" aria-label="Grafik perolehan suara calon">
@forelse($rankedCandidates as $candidate)
    @php($percentage = $votes ? round($candidate->votes_count / $votes * 100, 1) : 0)
    @php($isLeader = $highest > 0 && (int) $candidate->votes_count === $highest)
    <article class="result-card{{ $isLeader ? ' leading-card' : '' }}">
        <div class="candidate-portrait"><img src="{{ route('candidate.photo', $candidate) }}" width="600" height="400" alt="Foto {{ $candidate->name }}"><span class="candidate-number">{{ str_pad($candidate->number, 2, '0', STR_PAD_LEFT) }}</span>@if($isLeader)<span class="leader-label">{{ $leaders->count() > 1 ? 'Tertinggi bersama' : 'Perolehan tertinggi' }}</span>@endif</div>
        <div class="result-card-body"><p class="candidate-label">CALON KETUA OSIS</p><h2>{{ $candidate->name }}</h2>
            <div class="candidate-result"><strong>{{ number_format($candidate->votes_count, 0, ',', '.') }}<small>suara</small></strong><span>{{ number_format($percentage, 1, ',', '.') }}<small>%</small></span></div>
            <progress class="vote-bar" value="{{ $percentage }}" max="100" aria-label="Perolehan {{ $candidate->name }}: {{ $candidate->votes_count }} suara, {{ number_format($percentage, 1, ',', '.') }} persen">{{ number_format($percentage, 1, ',', '.') }}%</progress>
        </div>
    </article>
@empty
    <div class="no-candidates">Tidak ada calon terdaftar untuk pemilihan ini.</div>
@endforelse
</section>
<p class="rounding-note">Persentase dihitung dari total suara. Pembulatan dapat menyebabkan jumlah persentase berbeda dari 100%.</p>
@endsection
