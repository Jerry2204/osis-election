<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentImportPreviewService
{
    private const SESSION_KEY = 'student_import';
    private const DIRECTORY = 'student-imports';
    public const LIFETIME_SECONDS = 1800;

    public function stage(UploadedFile $file, Session $session): void
    {
        $this->discard($session);
        $this->purgeExpired();
        $token = bin2hex(random_bytes(32));
        $disk = Storage::disk('local');
        $file->storeAs(self::DIRECTORY, $token.'.csv', 'local');
        try {
            $inspection = app(StudentImportService::class)->inspect($disk->path($this->csvPath($token)));
            unset($inspection['rows'], $inspection['row_lines']);
            $name = mb_convert_encoding($file->getClientOriginalName(), 'UTF-8', 'UTF-8');
            $name = mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name))), 0, 255);
            $metadata = [
                'token' => $token,
                'name' => $name,
                'expires_at' => now()->timestamp + self::LIFETIME_SECONDS,
                'session_hash' => hash('sha256', $session->getId()),
                'inspection' => $inspection,
            ];
            if (! $disk->put($this->metadataPath($token), json_encode($metadata, JSON_THROW_ON_ERROR))) {
                throw ValidationException::withMessages(['file' => 'Pratinjau tidak dapat disimpan. Silakan unggah ulang.']);
            }
            $session->put(self::SESSION_KEY, ['token' => $token]);
        } catch (Throwable $exception) {
            $this->deleteToken($token);
            throw $exception;
        }
    }

    public function current(Session $session): array
    {
        $token = $session->get(self::SESSION_KEY.'.token');
        $disk = Storage::disk('local');
        if (! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            throw ValidationException::withMessages(['file' => 'Pratinjau impor tidak tersedia. Silakan unggah CSV terlebih dahulu.']);
        }
        $metadata = $disk->exists($this->metadataPath($token))
            ? json_decode($disk->get($this->metadataPath($token)), true)
            : null;
        if (is_array($metadata) && isset($metadata['session_hash']) &&
            ! hash_equals($metadata['session_hash'], hash('sha256', $session->getId()))) {
            // A token copied to another session must not invalidate its owner's staged import.
            $session->forget(self::SESSION_KEY);
            throw ValidationException::withMessages(['file' => 'Pratinjau impor tidak berlaku untuk sesi ini. Silakan unggah CSV kembali.']);
        }
        if (! is_array($metadata) || ! isset($metadata['session_hash'], $metadata['expires_at'], $metadata['inspection']) ||
            (int) $metadata['expires_at'] <= now()->timestamp || ! $disk->exists($this->csvPath($token))) {
            $this->discard($session);
            throw ValidationException::withMessages(['file' => 'Pratinjau impor sudah kedaluwarsa atau tidak berlaku. Silakan unggah CSV kembali.']);
        }
        return $metadata;
    }

    public function confirm(Session $session, string $token): int
    {
        $metadata = $this->current($session);
        if (! hash_equals($metadata['token'], $token)) {
            throw ValidationException::withMessages(['file' => 'Pratinjau impor tidak sesuai. Silakan gunakan pratinjau terbaru.']);
        }
        // Every authorized confirmation re-reads and revalidates the private CSV. Browser fields never supply rows.
        try {
            return app(StudentImportService::class)->import(Storage::disk('local')->path($this->csvPath($token)));
        } finally {
            $this->discard($session);
        }
    }

    public function discard(Session $session): void
    {
        $token = $session->get(self::SESSION_KEY.'.token');
        if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            $disk = Storage::disk('local');
            $metadata = $disk->exists($this->metadataPath($token))
                ? json_decode($disk->get($this->metadataPath($token)), true)
                : null;
            if (is_array($metadata) && is_string($metadata['session_hash'] ?? null) &&
                hash_equals($metadata['session_hash'], hash('sha256', $session->getId()))) {
                $this->deleteToken($token);
            }
        }
        $session->forget(self::SESSION_KEY);
    }

    public function purgeExpired(): int
    {
        $disk = Storage::disk('local');
        $deleted = 0;
        foreach ($disk->files(self::DIRECTORY) as $file) {
            if (! preg_match('~/([a-f0-9]{64})\.json\z~', $file, $matches)) {
                continue;
            }
            $metadata = json_decode($disk->get($file), true);
            if (! is_array($metadata) || (int) ($metadata['expires_at'] ?? 0) <= now()->timestamp) {
                $this->deleteToken($matches[1]);
                $deleted++;
            }
        }
        // Recover abandoned uploads interrupted before their metadata was written.
        foreach ($disk->files(self::DIRECTORY) as $file) {
            if (preg_match('~/([a-f0-9]{64})\.csv\z~', $file, $matches) &&
                ! $disk->exists($this->metadataPath($matches[1])) &&
                $disk->lastModified($file) <= now()->timestamp - self::LIFETIME_SECONDS) {
                $disk->delete($file);
                $deleted++;
            }
        }
        return $deleted;
    }

    private function deleteToken(string $token): void
    {
        Storage::disk('local')->delete([$this->csvPath($token), $this->metadataPath($token)]);
    }

    private function csvPath(string $token): string
    {
        return self::DIRECTORY.'/'.$token.'.csv';
    }

    private function metadataPath(string $token): string
    {
        return self::DIRECTORY.'/'.$token.'.json';
    }
}
