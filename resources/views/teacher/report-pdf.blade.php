<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap hasil - {{ $setting->schoolLabel() }}</title>
    <style>
        @page { margin: 38px 40px 50px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 9px; line-height: 1.5; color: #173e34; }
        h1, h2, p { margin: 0; }
        h1 { font-size: 18px; line-height: 1.35; margin: 4px 0 5px; }
        h2 { font-size: 11px; margin: 19px 0 8px; page-break-after: avoid; }
        .muted { color: #5c6c61; }
        .eyebrow { font-size: 8px; letter-spacing: 1px; text-transform: uppercase; }
        .header { width: 100%; border-collapse: collapse; padding-bottom: 15px; border-bottom: 2px solid #173e34; }
        .header td { vertical-align: middle; }
        .logo-cell { width: 66px; }
        .logo { max-width: 54px; max-height: 66px; }
        .school { font-size: 13px; font-weight: bold; }
        .period { font-size: 9px; margin-top: 4px; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta td:first-child { width: 110px; color: #5c6c61; }
        .stats { width: 100%; table-layout: fixed; border-collapse: collapse; margin: 15px 0 6px; }
        .stats td { border: 1px solid #d6dfd1; padding: 9px 10px; vertical-align: top; background: #f1f5e9; }
        .stats strong { display: block; font-size: 19px; line-height: 1.4; }
        .stats span { font-size: 8px; }
        .notice { padding: 9px 11px; margin: 9px 0; border: 1px solid #cad9bc; background: #f1f5e9; }
        .warning { background: #fff4dc; border-color: #d7b876; color: #714c15; }
        .data-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .data-table thead { display: table-header-group; }
        .data-table th { font-size: 8px; padding: 8px; background: #173e34; color: #fff; font-weight: normal; text-align: left; }
        .data-table td { padding: 8px; border-bottom: 1px solid #dfe5da; vertical-align: top; word-wrap: break-word; }
        .data-table tr { page-break-inside: avoid; }
        .data-table tbody tr:nth-child(even) td { background: #f7f9f3; }
        .number { text-align: right !important; }
        .center { text-align: center !important; }
        .report-note { margin-top: 8px; font-size: 8px; }
        .signature-block { margin-top: 24px; page-break-inside: avoid; }
        .signature-date { margin-bottom: 10px; text-align: right; }
        .signatures { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .signatures td { width: 33.33%; text-align: center; vertical-align: top; padding: 0 10px; word-wrap: break-word; }
        .signature-name { padding-top: 56px; font-weight: bold; }
        .signature-line { border-top: 1px solid #6e7b70; margin: 4px 0; }
    </style>
</head>
<body>
    <table class="header"><tr>
        @if($logoDataUri)<td class="logo-cell"><img class="logo" src="{{ $logoDataUri }}" alt="Logo sekolah"></td>@endif
        <td><p class="school">{{ $setting->schoolLabel() }}</p><p class="eyebrow">Rekap akhir pemilihan</p><h1>{{ $setting->electionLabel() }}</h1>@if($setting->osis_period)<p class="period">Periode OSIS {{ $setting->osis_period }}</p>@endif</td>
    </tr></table>

    <table class="meta">
        <tr><td>Waktu dibuka</td><td>{{ $setting->opened_at ? $setting->opened_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB' : 'Tidak tersedia' }}</td></tr>
        <tr><td>Waktu ditutup</td><td>{{ $setting->closed_at ? $setting->closed_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB' : 'Tidak tersedia' }}</td></tr>
        <tr><td>Laporan dibuat</td><td>{{ $generatedAt->format('d/m/Y H:i:s') }} WIB</td></tr>
    </table>

    <table class="stats"><tr>
        <td><span>Siswa terdaftar</span><strong>{{ number_format($results['registered'], 0, ',', '.') }}</strong></td>
        <td><span>Sudah memilih</span><strong>{{ number_format($results['participated'], 0, ',', '.') }}</strong></td>
        <td><span>Belum memilih</span><strong>{{ number_format($results['remaining'], 0, ',', '.') }}</strong></td>
        <td><span>Total suara</span><strong>{{ number_format($results['votes'], 0, ',', '.') }}</strong></td>
    </tr></table>
    <p class="report-note">Partisipasi keseluruhan: <strong>{{ number_format($turnout['percentage'], 1, ',', '.') }}%</strong> dari siswa terdaftar.</p>
    @if($results['consistent'])
        <div class="notice">Pemeriksaan konsistensi: jumlah partisipasi sesuai dengan total suara.</div>
    @else
        <div class="notice warning"><strong>Perlu pemeriksaan.</strong> Jumlah partisipasi berbeda dari total suara. Verifikasi data sebelum mengumumkan hasil.</div>
    @endif

    <h2>Perolehan suara calon</h2>
    <table class="data-table">
        <thead><tr><th class="center" style="width: 13%">Nomor</th><th style="width: 49%">Nama calon</th><th class="number" style="width: 19%">Suara</th><th class="number" style="width: 19%">Persentase</th></tr></thead>
        <tbody>@foreach($results['candidates'] as $candidate)<tr>
            <td class="center">{{ str_pad($candidate->number, 2, '0', STR_PAD_LEFT) }}</td><td>{{ $candidate->name }}</td><td class="number">{{ number_format($candidate->votes_count, 0, ',', '.') }}</td><td class="number">{{ number_format($results['votes'] ? $candidate->votes_count / $results['votes'] * 100 : 0, 1, ',', '.') }}%</td>
        </tr>@endforeach</tbody>
    </table>
    @if($results['candidates']->isEmpty())<p class="report-note muted">Tidak ada calon terdaftar.</p>@endif
    @if(count($tiedLeaders) > 1)<p class="report-note">Perolehan suara tertinggi sama untuk {{ implode(', ', $tiedLeaders) }}. Penetapan hasil mengikuti ketentuan panitia.</p>@endif
    <p class="report-note muted">Persentase dihitung dari total suara; pembulatan dapat membuat jumlah persentase berbeda dari 100%.</p>

    <h2>Partisipasi per kelas</h2>
    <table class="data-table">
        <thead><tr><th style="width: 28%">Kelas</th><th class="number" style="width: 18%">Terdaftar</th><th class="number" style="width: 18%">Memilih</th><th class="number" style="width: 18%">Tersisa</th><th class="number" style="width: 18%">Partisipasi</th></tr></thead>
        <tbody>@foreach($turnout['classes'] as $class)<tr>
            <td>{{ $class['class_name'] }}</td><td class="number">{{ number_format($class['registered'], 0, ',', '.') }}</td><td class="number">{{ number_format($class['participated'], 0, ',', '.') }}</td><td class="number">{{ number_format($class['remaining'], 0, ',', '.') }}</td><td class="number">{{ number_format($class['percentage'], 1, ',', '.') }}%</td>
        </tr>@endforeach</tbody>
    </table>
    @if(count($turnout['classes']) === 0)<p class="report-note muted">Belum ada data kelas.</p>@endif
    <p class="report-note muted">Laporan ini tidak memuat NIS atau pilihan individu siswa.</p>

    <div class="signature-block">
        <p class="signature-date">{{ $setting->report_place ?: '................................' }}, {{ $generatedAt->format('d/m/Y') }}</p>
        <table class="signatures"><tr>
            @foreach(['committee_chair' => 'Ketua Panitia', 'osis_advisor' => 'Pembina OSIS', 'principal_name' => 'Kepala Sekolah'] as $field => $role)
                <td><p>{{ $role }}</p><p class="signature-name">{{ $setting->{$field} ?: '................................' }}</p><div class="signature-line"></div></td>
            @endforeach
        </tr></table>
    </div>
</body>
</html>
