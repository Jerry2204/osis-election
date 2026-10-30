<?php

namespace App\Http\Controllers;

use App\Models\ElectionSetting;
use App\Models\Student;
use App\Services\StudentImportService;
use Illuminate\Http\Request;

class TeacherStudentController extends Controller
{
    public function index(Request $request)
    {
        return view('teacher.students', [
            'teacherKey' => $request->route('teacherKey'),
            'setting' => ElectionSetting::current(),
            'students' => Student::query()->orderBy('class_name')->orderBy('name')->orderBy('nis')->paginate(50),
        ]);
    }

    public function import(Request $request, StudentImportService $service)
    {
        abort_unless(ElectionSetting::current()->status === 'preparation', 403, 'Data siswa sudah dikunci setelah pemilihan dibuka.');
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.required' => 'Pilih berkas CSV siswa terlebih dahulu.',
            'file.extensions' => 'Berkas siswa harus menggunakan ekstensi .csv.',
            'file.mimes' => 'Berkas siswa harus berupa CSV UTF-8 dengan pemisah koma.',
            'file.max' => 'Ukuran berkas CSV maksimal 10 MB.',
        ]);
        $count = $service->import($request->file('file')->getPathname());
        return redirect()->route('teacher.students', ['teacherKey' => $request->route('teacherKey')])
            ->with('status', number_format($count, 0, ',', '.').' siswa berhasil diimpor. Angka nol di awal NIS dipertahankan.');
    }

    public function template()
    {
        return response()->download(base_path('docs/template-siswa.csv'), 'template-siswa.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
