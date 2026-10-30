<?php

namespace App\Http\Controllers;

use App\Models\ElectionSetting;
use App\Models\Student;
use App\Services\ElectionService;
use App\Services\StudentImportPreviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherStudentController extends Controller
{
    public function index(Request $request)
    {
        app(ElectionService::class)->reconcile();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'class' => ['nullable', 'string', 'max:60'],
            'participation' => ['nullable', Rule::in(['all', 'voted', 'pending'])],
        ]);
        $filters = [
            'q' => trim($filters['q'] ?? ''),
            'class' => $filters['class'] ?? '',
            'participation' => $filters['participation'] ?? 'all',
        ];
        $query = Student::query()->withExists('participation');
        if ($filters['q'] !== '') {
            // A bound LIKE pattern preserves NIS leading zeroes and treats wildcard characters literally.
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function ($query) use ($search) {
                $query->whereRaw("nis LIKE ? ESCAPE '!'", [$search])
                    ->orWhereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", [$search]);
            });
        }
        if ($filters['class'] !== '') {
            $query->where('class_name', $filters['class']);
        }
        if ($filters['participation'] === 'voted') {
            $query->whereHas('participation');
        } elseif ($filters['participation'] === 'pending') {
            $query->whereDoesntHave('participation');
        }
        return view('teacher.students', [
            'teacherKey' => $request->route('teacherKey'),
            'setting' => ElectionSetting::current(),
            'students' => $query->orderBy('class_name')->orderBy('name')->orderBy('nis')->paginate(50)->withQueryString(),
            'registered' => Student::query()->count(),
            'classes' => Student::query()->select('class_name')->distinct()->orderBy('class_name')->pluck('class_name'),
            'filters' => $filters,
        ]);
    }

    public function import(Request $request, StudentImportPreviewService $previews)
    {
        $this->ensureEditable($request, $previews);
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.required' => 'Pilih berkas CSV siswa terlebih dahulu.',
            'file.extensions' => 'Berkas siswa harus menggunakan ekstensi .csv.',
            'file.mimes' => 'Berkas siswa harus berupa CSV UTF-8 dengan pemisah koma.',
            'file.max' => 'Ukuran berkas CSV maksimal 10 MB.',
        ]);
        $previews->stage($request->file('file'), $request->session());
        return redirect()->route('teacher.students.preview', ['teacherKey' => $request->route('teacherKey')]);
    }

    public function preview(Request $request, StudentImportPreviewService $previews)
    {
        $this->ensureEditable($request, $previews);
        try {
            $preview = $previews->current($request->session());
        } catch (ValidationException $exception) {
            return redirect()->route('teacher.students', ['teacherKey' => $request->route('teacherKey')])->withErrors($exception->errors());
        }
        return view('teacher.student-import-preview', [
            'teacherKey' => $request->route('teacherKey'),
            'preview' => $preview,
            'inspection' => $preview['inspection'],
        ]);
    }

    public function confirm(Request $request, StudentImportPreviewService $previews)
    {
        $this->ensureEditable($request, $previews);
        $request->validate(['import_token' => ['required', 'string', 'size:64', 'regex:/\A[a-f0-9]{64}\z/']]);
        try {
            $count = $previews->confirm($request->session(), $request->string('import_token')->toString());
        } catch (ValidationException $exception) {
            return redirect()->route('teacher.students', ['teacherKey' => $request->route('teacherKey')])->withErrors($exception->errors());
        }
        return redirect()->route('teacher.students', ['teacherKey' => $request->route('teacherKey')])
            ->with('status', number_format($count, 0, ',', '.').' siswa berhasil diimpor. Angka nol di awal NIS dipertahankan.');
    }

    public function cancel(Request $request, StudentImportPreviewService $previews)
    {
        $previews->discard($request->session());
        return redirect()->route('teacher.students', ['teacherKey' => $request->route('teacherKey')])
            ->with('status', 'Pratinjau impor dibatalkan. Tidak ada data siswa yang disimpan.');
    }

    public function template()
    {
        return response()->download(base_path('docs/template-siswa.csv'), 'template-siswa.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function ensureEditable(Request $request, StudentImportPreviewService $previews): void
    {
        app(ElectionService::class)->reconcile();
        if (ElectionSetting::current()->status !== 'preparation') {
            $previews->discard($request->session());
            abort(403, 'Data siswa sudah dikunci setelah pemilihan dibuka.');
        }
    }
}
