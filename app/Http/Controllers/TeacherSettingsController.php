<?php

namespace App\Http\Controllers;

use App\Models\ElectionSetting;
use App\Services\ElectionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TeacherSettingsController extends Controller
{
    private const IDENTITY_FIELDS = ['school_name', 'election_title', 'osis_period', 'opens_at', 'closes_at', 'logo', 'remove_logo'];
    private const REPORT_FIELDS = ['report_place', 'committee_chair', 'osis_advisor', 'principal_name'];

    public function index(Request $request, ElectionService $service)
    {
        $setting = $service->reconcile();
        return view('teacher.settings', [
            'teacherKey' => $request->route('teacherKey'),
            'setting' => $setting,
            'scheduleNotice' => $service->scheduleNotice($setting),
        ]);
    }

    public function update(Request $request, ElectionService $service)
    {
        $setting = $service->reconcile();
        $identityRequested = array_intersect(self::IDENTITY_FIELDS, array_keys($request->all())) !== [];
        if ($setting->status !== 'preparation' && $identityRequested) {
            throw ValidationException::withMessages(['election' => 'Identitas sekolah dan jadwal sudah dikunci setelah pemilihan dibuka. Data penandatangan laporan tetap dapat diubah.']);
        }

        $rules = [
            'report_place' => ['nullable', 'string', 'max:120'],
            'committee_chair' => ['nullable', 'string', 'max:120'],
            'osis_advisor' => ['nullable', 'string', 'max:120'],
            'principal_name' => ['nullable', 'string', 'max:120'],
        ];
        if ($setting->status === 'preparation') {
            $rules += [
                'school_name' => ['nullable', 'string', 'max:120'],
                'election_title' => ['nullable', 'string', 'max:160'],
                'osis_period' => ['nullable', 'string', 'max:40'],
                'logo' => ['nullable', 'image', 'mimes:png,jpeg,jpg', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
                'remove_logo' => ['sometimes', 'boolean'],
                'opens_at' => ['nullable', 'required_with:closes_at', 'date_format:Y-m-d\TH:i'],
                'closes_at' => ['nullable', 'required_with:opens_at', 'date_format:Y-m-d\TH:i', 'after:opens_at'],
            ];
        }
        $data = $request->validate($rules, [
            'logo.image' => 'Logo harus berupa gambar PNG atau JPEG.',
            'logo.mimes' => 'Logo harus berformat PNG atau JPEG.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'logo.dimensions' => 'Dimensi logo maksimal 4000 × 4000 piksel.',
            'opens_at.required_with' => 'Isi waktu buka dan tutup bersama, atau kosongkan keduanya untuk pengaturan manual.',
            'closes_at.required_with' => 'Isi waktu buka dan tutup bersama, atau kosongkan keduanya untuk pengaturan manual.',
            'opens_at.date_format' => 'Format waktu buka tidak valid.',
            'closes_at.date_format' => 'Format waktu tutup tidak valid.',
            'closes_at.after' => 'Waktu tutup harus setelah waktu buka.',
        ], [
            'school_name' => 'Nama sekolah', 'election_title' => 'Judul pemilihan', 'osis_period' => 'Periode OSIS',
            'report_place' => 'Tempat laporan', 'committee_chair' => 'Ketua panitia',
            'osis_advisor' => 'Pembina OSIS', 'principal_name' => 'Kepala sekolah',
        ]);

        foreach (['opens_at', 'closes_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field]
                    ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data[$field], 'Asia/Jakarta')->utc()
                    : null;
            }
        }
        if (! empty($data['opens_at'])) {
            $scheduleChanged = ! $setting->opens_at || ! $setting->closes_at ||
                ! $setting->opens_at->equalTo($data['opens_at']) || ! $setting->closes_at->equalTo($data['closes_at']);
            if ($scheduleChanged && $data['opens_at']->lessThanOrEqualTo(now()->utc())) {
                throw ValidationException::withMessages(['opens_at' => 'Waktu buka untuk jadwal baru atau yang diubah harus setelah waktu server saat ini.']);
            }
        }
        foreach (array_merge(['school_name', 'election_title', 'osis_period'], self::REPORT_FIELDS) as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = trim((string) $data[$field]);
            }
        }
        // Uploaded files and controls are never mass-assigned to the model.
        unset($data['logo'], $data['remove_logo']);
        $newLogo = null;
        $oldLogo = null;
        $logoChanged = false;
        try {
            DB::transaction(function () use ($request, $service, $identityRequested, $data, &$newLogo, &$oldLogo, &$logoChanged) {
                $setting = $identityRequested ? $service->requirePreparation() : ElectionSetting::locked();
                $oldLogo = $setting->logo_path;
                if ($request->hasFile('logo')) {
                    $newLogo = $request->file('logo')->store('school-logos', 'local');
                    $data['logo_path'] = $newLogo;
                    $logoChanged = true;
                } elseif ($request->boolean('remove_logo')) {
                    $data['logo_path'] = null;
                    $logoChanged = true;
                }
                $setting->fill($data)->save();
            });
        } catch (Throwable $exception) {
            if ($newLogo) {
                Storage::disk('local')->delete($newLogo);
            }
            throw $exception;
        }
        if ($logoChanged && $oldLogo) {
            Storage::disk('local')->delete($oldLogo);
        }
        $service->reconcile();
        return redirect()->route('teacher.settings', ['teacherKey' => $request->route('teacherKey')])
            ->with('status', 'Pengaturan pemilihan berhasil disimpan.');
    }

    public function logo()
    {
        $path = ElectionSetting::current()->logo_path;
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);
        return response()->file($disk->path($path));
    }
}
