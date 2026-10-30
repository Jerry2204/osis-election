<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Pengumuman hasil · {{ $setting->schoolLabel() }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/announcement.css') }}?v={{ filemtime(public_path('assets/announcement.css')) }}">
    <script src="{{ asset('assets/announcement.js') }}?v={{ filemtime(public_path('assets/announcement.js')) }}" defer></script>
</head>
<body data-announcement data-status-url="{{ route('announcement.status') }}" data-available="{{ ($available ?? false) ? 'true' : 'false' }}" data-preview="{{ ($preview ?? false) ? 'true' : 'false' }}">
<a class="skip-link" href="#announcement-main">Lewati ke hasil pemilihan</a>
<div class="projection-shell">
    <header class="projection-header">
        <div class="school-identity">
            @if($setting->logo_path)<img class="school-emblem" src="{{ route('school.logo') }}" width="64" height="64" alt="Logo {{ $setting->schoolLabel() }}">@else<span class="school-emblem emblem-fallback" aria-hidden="true">✓</span>@endif
            <div><p class="school-name">{{ $setting->schoolLabel() }}</p><p class="school-period">{{ $setting->osis_period ? 'Periode OSIS '.$setting->osis_period : 'Suara kita, masa depan kita.' }}</p></div>
        </div>
        <div class="projection-tools">@if($preview ?? false)<span class="projection-badge preview-badge">Pratinjau panitia</span>@endif<button type="button" class="fullscreen-button" data-fullscreen hidden>Layar penuh <span aria-hidden="true">⛶</span></button></div>
    </header>
    <main id="announcement-main">@yield('content')</main>
    <footer class="projection-footer"><span>Suara OSIS · Jujur memilih. Bersama bertumbuh.</span><span data-connection-status role="status">{{ ($preview ?? false) ? 'Pratinjau ini belum mengaktifkan pengumuman.' : 'Pilihan individu siswa tetap rahasia.' }}</span></footer>
</div>
</body>
</html>
