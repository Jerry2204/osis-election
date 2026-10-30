<?php

use App\Http\Controllers\TeacherCandidateController;
use App\Http\Controllers\TeacherElectionController;
use App\Http\Controllers\TeacherStudentController;
use App\Http\Controllers\TeacherSettingsController;
use App\Http\Controllers\TeacherReportController;
use App\Http\Controllers\ElectionStatusController;
use App\Http\Controllers\VoterSessionController;
use App\Http\Controllers\VotingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VoterSessionController::class, 'home'])->name('home')->block();
Route::post('/batalkan', [VoterSessionController::class, 'cancel'])->name('voting.cancel')->block();
Route::get('/berhasil', [VotingController::class, 'success'])->name('voting.success')->block();
Route::get('/foto-calon/{candidate}', [VotingController::class, 'photo'])->name('candidate.photo');
Route::get('/status-pemilihan', [ElectionStatusController::class, 'publicStatus'])->name('election.status');
Route::get('/logo-sekolah', [TeacherSettingsController::class, 'logo'])->name('school.logo');

Route::middleware('election.open')->group(function () {
    Route::post('/verifikasi-nis', [VoterSessionController::class, 'verify'])->middleware('throttle:nis')->name('voting.verify')->block();
    Route::middleware('voting.session')->group(function () {
        Route::get('/calon', [VotingController::class, 'candidates'])->name('voting.candidates')->block();
        Route::post('/pilihan', [VotingController::class, 'choose'])->name('voting.choose')->block();
        Route::get('/konfirmasi', [VotingController::class, 'confirm'])->name('voting.confirm')->block();
        Route::post('/suara', [VotingController::class, 'store'])->name('voting.store')->block();
    });
});

Route::prefix('ruang-guru/{teacherKey}')->where(['teacherKey' => '[a-f0-9]{64}'])->middleware('teacher')->name('teacher.')->group(function () {
    Route::get('/', [TeacherElectionController::class, 'index'])->name('dashboard');
    Route::get('/progres', [ElectionStatusController::class, 'progress'])->name('progress');
    Route::get('/pengaturan', [TeacherSettingsController::class, 'index'])->name('settings');
    Route::patch('/pengaturan', [TeacherSettingsController::class, 'update'])->name('settings.update')->block();
    Route::get('/siswa', [TeacherStudentController::class, 'index'])->name('students');
    Route::get('/siswa/template.csv', [TeacherStudentController::class, 'template'])->name('students.template');
    Route::post('/siswa/impor', [TeacherStudentController::class, 'import'])->name('students.import')->block();
    Route::get('/siswa/impor/pratinjau', [TeacherStudentController::class, 'preview'])->name('students.preview')->block();
    Route::post('/siswa/impor/konfirmasi', [TeacherStudentController::class, 'confirm'])->name('students.confirm')->block();
    Route::post('/siswa/impor/batal', [TeacherStudentController::class, 'cancel'])->name('students.cancel')->block();
    Route::get('/calon', [TeacherCandidateController::class, 'index'])->name('candidates');
    Route::get('/calon/tambah', [TeacherCandidateController::class, 'create'])->name('candidates.create');
    Route::post('/calon', [TeacherCandidateController::class, 'store'])->name('candidates.store');
    Route::get('/calon/{candidate}/edit', [TeacherCandidateController::class, 'edit'])->name('candidates.edit');
    Route::patch('/calon/{candidate}', [TeacherCandidateController::class, 'update'])->name('candidates.update');
    Route::delete('/calon/{candidate}', [TeacherCandidateController::class, 'destroy'])->name('candidates.destroy');
    Route::post('/buka', [TeacherElectionController::class, 'open'])->name('open');
    Route::post('/tutup', [TeacherElectionController::class, 'close'])->name('close');
    Route::get('/rekap', [TeacherElectionController::class, 'results'])->name('results');
    Route::get('/rekap.csv', [TeacherElectionController::class, 'export'])->name('export');
    Route::get('/rekap.pdf', [TeacherReportController::class, 'download'])->name('report.pdf');
});
