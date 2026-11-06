<?php

namespace App\Services\Upload;

use App\Enums\SourceFileStatus;
use App\Models\SourceFile;
use App\Models\UploadSession;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class FileAssemblyService
{
    public function __construct(protected UploadSessionService $sessions) {}

    public function assemble(UploadSession $session): SourceFile
    {
        $source = SourceFile::findOrFail($session->source_file_id);
        $finalPath = Storage::disk('geodata')->path($source->storage_path);
        if ($source->status === SourceFileStatus::READY && is_file($finalPath)) {
            $session->update(['status' => 'assembled']);
            File::deleteDirectory($this->sessions->directory($session));

            return $source;
        }
        $this->sessions->ensureDiskSpace($session->file_size);
        File::ensureDirectoryExists(dirname($finalPath), 0700);
        $temporary = $finalPath.'.assembling';
        $output = fopen($temporary, 'wb');
        if ($output === false) {
            throw new \RuntimeException('Unable to create assembly output.');
        }
        $hash = hash_init('sha256');
        $bytes = 0;
        try {
            for ($index = 0; $index < $session->total_chunks; $index++) {
                $path = $this->sessions->directory($session).'/'.$index.'.part';
                if (! is_file($path) || ! isset($session->chunks[$index])
                    || ! hash_equals($session->chunks[$index], hash_file('sha256', $path))) {
                    throw new \RuntimeException('Missing or corrupted upload chunk.');
                }
                $input = fopen($path, 'rb');
                if ($input === false) {
                    throw new \RuntimeException('Unable to read upload chunk.');
                }
                try {
                    while (! feof($input)) {
                        $buffer = fread($input, 1024 * 1024);
                        if ($buffer === false) {
                            throw new \RuntimeException('Unable to read upload chunk.');
                        }
                        $length = strlen($buffer);
                        $offset = 0;
                        while ($offset < $length) {
                            $written = fwrite($output, substr($buffer, $offset));
                            if ($written === false || $written === 0) {
                                throw new \RuntimeException('Unable to write assembled file.');
                            }
                            $offset += $written;
                        }
                        $bytes += $length;
                        if ($bytes > $session->file_size) {
                            throw new \RuntimeException('Assembled file exceeds the declared size.');
                        }
                        hash_update($hash, $buffer);
                    }
                } finally {
                    fclose($input);
                }
            }
            if ($bytes !== $session->file_size || ! fflush($output)) {
                throw new \RuntimeException('Assembled file size does not match the upload session.');
            }
            fclose($output);
            $output = null;
            app(UploadFileValidator::class)->validate($temporary, pathinfo($session->file_name, PATHINFO_EXTENSION));
            if (! rename($temporary, $finalPath)) {
                throw new \RuntimeException('Unable to commit assembled file.');
            }
            $source->update([
                'file_size' => $bytes, 'checksum_sha256' => hash_final($hash),
                'mime_type' => (new \finfo(FILEINFO_MIME_TYPE))->file($finalPath) ?: 'application/octet-stream',
                'status' => SourceFileStatus::READY,
            ]);
            $session->update(['status' => 'assembled']);
            File::deleteDirectory($this->sessions->directory($session));

            return $source;
        } finally {
            if (is_resource($output)) {
                fclose($output);
            }
            // Retain chunks on error; retries always start from an empty output.
            File::delete($temporary);
        }
    }
}
