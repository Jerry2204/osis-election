@extends('layouts.app')
@section('title', 'Kelola calon')
@section('content')
<div class="page-heading"><div><div class="eyebrow">RUANG GURU / CALON</div><h1>Kenalkan para calon.</h1><p>Pratinjau foto, visi, dan misi seperti yang akan dilihat siswa.</p></div>@if($setting->status === 'preparation')<a class="button button-primary" href="{{ route('teacher.candidates.create', ['teacherKey' => $teacherKey]) }}">+ Tambah calon</a>@else<span class="badge badge-neutral">Data calon dikunci</span>@endif</div>
@if($candidates->isEmpty())
<section class="empty-state panel"><span class="large-check" aria-hidden="true">＋</span><h2>Mulai dengan calon pertama.</h2><p>Tambahkan foto, nama, serta visi dan misi calon ketua OSIS.<br>Minimal dua calon diperlukan untuk membuka pemilihan.</p><a class="button button-primary" href="{{ route('teacher.candidates.create', ['teacherKey' => $teacherKey]) }}">Tambah calon pertama →</a></section>
@else
<div class="candidate-grid">@foreach($candidates as $candidate)<article class="candidate-card">@include('components.candidate')
@if($setting->status === 'preparation')<div class="candidate-actions"><a class="button button-secondary" href="{{ route('teacher.candidates.edit', ['teacherKey' => $teacherKey, 'candidate' => $candidate]) }}">Edit calon</a><form method="post" action="{{ route('teacher.candidates.destroy', ['teacherKey' => $teacherKey, 'candidate' => $candidate]) }}" data-confirm="Hapus calon ini? Foto dan data calon akan dihapus." data-submit-once>@csrf @method('DELETE')<button type="submit" class="text-button danger-text">Hapus</button></form></div>@endif
</article>@endforeach</div>
@endif
@endsection
