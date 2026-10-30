<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>@yield('title', 'Pemilihan Ketua OSIS') · Suara OSIS</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke isi halaman</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="{{ isset($teacherKey) ? route('teacher.dashboard', ['teacherKey' => $teacherKey]) : route('home') }}" aria-label="Suara OSIS, halaman awal">
            <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="m8 16 6 6L25 10" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <span>suara<span class="brand-light">osis</span><small>PEMILIHAN KETUA OSIS</small></span>
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
</div></nav>
@endisset
<main id="main" class="container main-content">
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if($notice ?? session('notice'))<div class="alert alert-warning" role="alert">{{ $notice ?? session('notice') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-warning" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="site-footer"><div class="container footer-inner"><span>Suara OSIS <span aria-hidden="true">/</span> Dari kita, untuk kita.</span><span>Jujur memilih. Bersama bertumbuh.</span></div></footer>
</body>
</html>
