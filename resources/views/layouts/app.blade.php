<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>@yield('title', $schoolSetting->electionLabel()) · {{ $schoolSetting->schoolLabel() }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v={{ filemtime(public_path('assets/app.css')) }}">
    <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke isi halaman</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="{{ isset($teacherKey) ? route('teacher.dashboard', ['teacherKey' => $teacherKey]) : route('home') }}" aria-label="{{ $schoolSetting->schoolLabel() }}, halaman awal">
            @if($schoolSetting->logo_path)<img class="school-logo" src="{{ route('school.logo') }}" width="45" height="45" alt="Logo {{ $schoolSetting->schoolLabel() }}">@else<span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="m8 16 6 6L25 10" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>@endif
            <span class="brand-copy">@if($schoolSetting->school_name){{ $schoolSetting->school_name }}@else suara<span class="brand-light">osis</span>@endif<small>{{ $schoolSetting->electionLabel() }}{{ $schoolSetting->osis_period ? ' · '.$schoolSetting->osis_period : '' }}</small></span>
        </a>
        @isset($teacherKey)
            <span class="badge badge-neutral">Ruang guru</span>
        @else
            <span class="header-note"><span class="local-dot"></span> Pemilihan di sekolah</span>
        @endisset
    </div>
</header>
@isset($teacherKey)
<nav class="teacher-nav" aria-label="Menu guru"><div class="container nav-inner">
    <a class="{{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}" href="{{ route('teacher.dashboard', ['teacherKey' => $teacherKey]) }}">Ringkasan</a>
    <a class="{{ request()->routeIs('teacher.students*') ? 'active' : '' }}" href="{{ route('teacher.students', ['teacherKey' => $teacherKey]) }}">Data siswa</a>
    <a class="{{ request()->routeIs('teacher.candidates*') ? 'active' : '' }}" href="{{ route('teacher.candidates', ['teacherKey' => $teacherKey]) }}">Kelola calon</a>
    <a class="{{ request()->routeIs('teacher.results') ? 'active' : '' }}" href="{{ route('teacher.results', ['teacherKey' => $teacherKey]) }}">Rekap hasil</a>
    <a class="{{ request()->routeIs('teacher.settings*') ? 'active' : '' }}" href="{{ route('teacher.settings', ['teacherKey' => $teacherKey]) }}">Pengaturan</a>
</div></nav>
@endisset
<main id="main" class="container main-content">
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if(($notice ?? session('notice')) && !(request()->routeIs('home') && $schoolSetting->status === 'open'))<div class="alert alert-warning" role="alert">{{ $notice ?? session('notice') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-warning" role="alert" tabindex="-1" id="error-summary"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->messages() as $field => $messages)@php($target = match($field) { 'file' => 'student-file', 'logo' => 'school-logo', 'q' => 'student-search', 'class' => 'student-class', 'participation' => 'student-participation', default => str_replace('_', '-', $field) })@foreach($messages as $error)<li>@if(!in_array($field, ['election', 'import_token']))<a href="#{{ $target }}">{{ $error }}</a>@else{{ $error }}@endif</li>@endforeach @endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="site-footer"><div class="container footer-inner"><span>Suara OSIS <span aria-hidden="true">/</span> Dari kita, untuk kita.</span><span>Jujur memilih. Bersama bertumbuh.</span></div></footer>
</body>
</html>
