<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\ElectionSetting;
use App\Services\ElectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class TeacherCandidateController extends Controller
{
    public function index(Request $request)
    {
        return view('teacher.candidates', [
            'teacherKey' => $request->route('teacherKey'),
            'setting' => ElectionSetting::current(),
            'candidates' => Candidate::query()->orderBy('number')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->ensureEditable();
        return view('teacher.candidate-form', ['candidate' => new Candidate, 'teacherKey' => $request->route('teacherKey')]);
    }

    public function edit(Request $request, string $teacherKey, Candidate $candidate)
    {
        $this->ensureEditable();
        return view('teacher.candidate-form', compact('candidate', 'teacherKey'));
    }

    public function store(Request $request, ElectionService $service)
    {
        return $this->save($request, new Candidate, $service);
    }

    public function update(Request $request, string $teacherKey, Candidate $candidate, ElectionService $service)
    {
        return $this->save($request, $candidate, $service);
    }

    private function save(Request $request, Candidate $candidate, ElectionService $service)
    {
        $this->ensureEditable();
        $newPath = null;
        $oldPath = null;
        try {
            DB::transaction(function () use ($request, $candidate, $service, &$newPath, &$oldPath) {
                $service->requirePreparation();
                if ($candidate->exists) {
                    $candidate = Candidate::query()->findOrFail($candidate->id);
                }
                $oldPath = $candidate->photo_path;
                $data = $request->validate([
                    'number' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('candidates', 'number')->ignore($candidate->id)],
                    'name' => ['required', 'string', 'max:120'],
                    'vision' => ['required', 'string', 'max:1500'],
                    'mission' => ['required', 'string', 'max:3000'],
                    'photo' => [$candidate->exists ? 'nullable' : 'required', 'image', 'mimes:jpeg,jpg,png', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
                ], [
                    'photo.required' => 'Unggah foto calon.',
                    'photo.image' => 'Foto harus berupa gambar JPEG atau PNG.',
                    'photo.mimes' => 'Foto harus berformat JPEG atau PNG.',
                    'photo.max' => 'Ukuran foto maksimal 2 MB.',
                    'photo.dimensions' => 'Dimensi foto maksimal 4000 × 4000 piksel.',
                    'number.unique' => 'Nomor urut ini sudah digunakan calon lain.',
                ]);
                unset($data['photo']);
                if ($request->hasFile('photo')) {
                    $newPath = $request->file('photo')->store('', 'photos');
                    $data['photo_path'] = $newPath;
                }
                $candidate->fill($data)->save();
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('photos')->delete($newPath);
            }
            throw $exception;
        }
        if ($newPath && $oldPath) {
            Storage::disk('photos')->delete($oldPath);
        }
        return redirect()->route('teacher.candidates', ['teacherKey' => $request->route('teacherKey')])->with('status', 'Data calon berhasil disimpan.');
    }

    public function destroy(Request $request, string $teacherKey, Candidate $candidate, ElectionService $service)
    {
        $path = DB::transaction(function () use ($candidate, $service) {
            $service->requirePreparation();
            $candidate = Candidate::query()->findOrFail($candidate->id);
            $path = $candidate->photo_path;
            $candidate->delete();
            return $path;
        });
        Storage::disk('photos')->delete($path);
        return redirect()->route('teacher.candidates', ['teacherKey' => $teacherKey])->with('status', 'Calon berhasil dihapus.');
    }

    private function ensureEditable(): void
    {
        abort_unless(ElectionSetting::current()->status === 'preparation', 403, 'Data calon sudah dikunci setelah pemilihan dibuka.');
    }
}
