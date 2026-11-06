<?php

namespace App\Services\Upload;

use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadSessionService
{
    public function start(string $userId, string $fileName, int $fileSize, ?string $requestId = null): UploadSession
    {
        $this->validateFileName($fileName);
        if ($fileSize < 1 || $fileSize > (int) config('geodata.max_upload_mb') * 1024 * 1024) {
            throw ValidationException::withMessages(['fileSize' => 'The file exceeds the upload limit or is empty.']);
        }

        $requestId ??= (string) Str::uuid();
        abort_unless(Str::isUuid($requestId), 422, 'Invalid upload request ID.');

        return DB::transaction(function () use ($userId, $fileName, $fileSize, $requestId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $existing = UploadSession::where('user_id', $userId)->where('request_id', $requestId)->first();
            if ($existing) {
                abort_unless($existing->file_name === $fileName && $existing->file_size === $fileSize, 409, 'Upload request ID was used for a different file.');
                abort_if($existing->expires_at->isPast(), 410, 'Upload expired.');

                return $existing;
            }
            $active = UploadSession::where('user_id', $userId)
                ->whereIn('status', ['uploading', 'queued'])
                ->where(fn ($q) => $q->where('expires_at', '>', now())->orWhere('status', 'queued'));
            abort_if((clone $active)->count() >= config('geodata.upload.max_active_sessions'), 429, 'Too many active uploads. Cancel an upload before starting another.');
            abort_if((clone $active)->sum('file_size') + $fileSize > config('geodata.upload.max_inflight_mb') * 1024 * 1024, 413, 'Your active uploads exceed the storage quota.');
            $this->ensureDiskSpace($fileSize * 2);
            $chunkSize = (int) config('geodata.upload.chunk_bytes');

            return UploadSession::create([
                'user_id' => $userId, 'request_id' => $requestId, 'file_name' => $fileName, 'file_size' => $fileSize,
                'chunk_size' => $chunkSize, 'total_chunks' => (int) ceil($fileSize / $chunkSize),
                'chunks' => [], 'status' => 'uploading',
                'expires_at' => now()->addHours(config('geodata.upload.session_hours')),
            ]);
        });
    }

    public function validateFileName(string $fileName): void
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (strlen($fileName) > 255 || preg_match('/[\x00-\x1f\x7f<>:"\/\\\\|?*]/', $fileName)
            || ! in_array($extension, config('geodata.allowed_extensions'), true)) {
            throw ValidationException::withMessages(['fileName' => 'Unsupported format or unsafe file name.']);
        }
    }

    public function owned(string $id, string $userId, bool $lock = false): UploadSession
    {
        abort_unless(Str::isUuid($id), 404);
        $query = UploadSession::whereKey($id)->where('user_id', $userId);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    public function storeChunk(string $id, string $userId, UploadedFile $file, int $index, string $checksum): void
    {
        DB::transaction(function () use ($id, $userId, $file, $index, $checksum) {
            $session = $this->owned($id, $userId, true);
            $this->assertWritable($session);
            abort_if($index < 0 || $index >= $session->total_chunks, 422, 'Chunk index is out of range.');
            $expected = min($session->chunk_size, $session->file_size - $index * $session->chunk_size);
            abort_unless($file->getSize() === $expected, 422, 'Chunk size does not match the upload session.');
            $actualHash = hash_file('sha256', $file->getRealPath());
            abort_unless(hash_equals($checksum, $actualHash), 422, 'Chunk checksum mismatch.');
            $chunks = $session->chunks;
            if (isset($chunks[$index])) {
                abort_unless(hash_equals($chunks[$index], $actualHash), 409, 'A different chunk already exists at this index.');
            }
            $this->ensureDiskSpace($expected);
            $directory = $this->directory($session);
            File::ensureDirectoryExists($directory, 0700);
            $temporary = $directory.'/'.$index.'.'.bin2hex(random_bytes(8)).'.tmp';
            try {
                $file->move($directory, basename($temporary));
                if (! rename($temporary, $directory.'/'.$index.'.part')) {
                    throw new \RuntimeException('Unable to persist upload chunk.');
                }
            } finally {
                File::delete($temporary);
            }
            $chunks[$index] = $actualHash;
            $session->update(['chunks' => $chunks, 'expires_at' => now()->addHours(config('geodata.upload.session_hours'))]);
        });
    }

    public function cancel(string $id, string $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $session = $this->owned($id, $userId, true);
            abort_unless(in_array($session->status, ['uploading', 'cancelled'], true), 409, 'Processing has already started.');
            File::deleteDirectory($this->directory($session));
            $session->update(['status' => 'cancelled', 'chunks' => []]);
        });
    }

    public function assertWritable(UploadSession $session): void
    {
        abort_unless($session->status === 'uploading', 409, 'This upload is no longer writable.');
        abort_if($session->expires_at->isPast(), 410, 'This upload expired. Please select the file again.');
    }

    public function assertComplete(UploadSession $session): void
    {
        $this->assertWritable($session);
        abort_unless(count($session->chunks) === $session->total_chunks, 409, 'Upload is incomplete. Resume the missing chunks.');
        for ($i = 0; $i < $session->total_chunks; $i++) {
            abort_unless(isset($session->chunks[$i]) && is_file($this->directory($session).'/'.$i.'.part'), 409, 'A required chunk is missing.');
        }
    }

    public function directory(UploadSession $session): string
    {
        abort_unless(Str::isUuid($session->id), 404);

        return Storage::disk('local')->path('temp_chunks/'.$session->id);
    }

    public function ensureDiskSpace(int $additionalBytes): void
    {
        $root = Storage::disk('geodata')->path('');
        File::ensureDirectoryExists($root, 0700);
        $free = disk_free_space($root);
        abort_if($free === false || $free < $additionalBytes + config('geodata.upload.min_free_disk_mb') * 1024 * 1024, 507, 'Insufficient storage space.');
    }
}
