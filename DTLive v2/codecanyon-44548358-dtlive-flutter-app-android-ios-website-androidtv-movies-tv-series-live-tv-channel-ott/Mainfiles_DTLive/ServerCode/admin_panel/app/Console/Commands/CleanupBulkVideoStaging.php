<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupBulkVideoStaging extends Command
{
    protected $signature = 'media:cleanup-staging
        {--older-than=3600 : Delete inactive staged uploads older than this many seconds}
        {--active-max-age=86400 : Delete queued or processing receipts that have been inactive this long}
        {--dry-run : Report candidates without deleting them}';

    protected $description = 'Remove abandoned bulk-upload staging files after media has been sent to R2';

    public function handle(): int
    {
        $root = storage_path('app/bulk-video-staging');
        if (!is_dir($root)) {
            $this->info('No bulk-upload staging directory exists.');
            return self::SUCCESS;
        }

        $inactiveTtl = max(60, (int) $this->option('older-than'));
        $activeTtl = max($inactiveTtl, (int) $this->option('active-max-age'));
        $now = time();
        $summary = [
            'directories_checked' => 0,
            'directories_removed' => 0,
            'files_removed' => 0,
            'bytes_removed' => 0,
            'directories_kept_active' => 0,
            'directories_kept_recent' => 0,
        ];

        foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $adminDirectory) {
            foreach (glob($adminDirectory . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
                $summary['directories_checked']++;
                $metadata = $this->readMetadata($directory);
                $status = strtolower((string) ($metadata['status'] ?? ''));
                $active = in_array($status, ['uploaded', 'queued', 'processing'], true);
                $lastActivity = $this->lastActivity($directory);
                $age = max(0, $now - $lastActivity);
                $ttl = $active ? $activeTtl : $inactiveTtl;

                if ($age < $ttl) {
                    $summary[$active ? 'directories_kept_active' : 'directories_kept_recent']++;
                    continue;
                }

                $size = $this->directoryBytes($directory);
                if ($this->option('dry-run')) {
                    $this->line(sprintf(
                        'Would remove %s (%s, status=%s, age=%ss)',
                        $directory,
                        $this->formatBytes($size),
                        $status !== '' ? $status : 'orphan',
                        $age
                    ));
                    continue;
                }

                $files = $this->removeDirectory($directory, $metadata);
                $summary['directories_removed']++;
                $summary['files_removed'] += $files;
                $summary['bytes_removed'] += $size;
            }

            if (is_dir($adminDirectory) && count(glob($adminDirectory . DIRECTORY_SEPARATOR . '*') ?: []) === 0) {
                @rmdir($adminDirectory);
            }
        }

        $this->table(
            ['metric', 'value'],
            collect($summary)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );
        if ($this->option('dry-run')) {
            $this->comment('Dry run only: no staging files were deleted.');
        }

        return self::SUCCESS;
    }

    private function readMetadata(string $directory): array
    {
        $path = $directory . DIRECTORY_SEPARATOR . 'meta.json';
        if (!is_file($path)) {
            return [];
        }

        $metadata = json_decode((string) file_get_contents($path), true);
        return is_array($metadata) ? $metadata : [];
    }

    private function lastActivity(string $directory): int
    {
        $last = (int) (filemtime($directory) ?: 0);
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            $last = max($last, (int) (filemtime($path) ?: 0));
        }
        return $last ?: time();
    }

    private function directoryBytes(string $directory): int
    {
        $bytes = 0;
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) {
                $bytes += (int) filesize($path);
            }
        }
        return $bytes;
    }

    private function removeDirectory(string $directory, array $metadata = []): int
    {
        $files = 0;
        $this->cleanupDirectR2($metadata);
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path) || is_link($path)) {
                if (@unlink($path)) {
                    $files++;
                }
            }
        }
        @rmdir($directory);
        return $files;
    }

    private function cleanupDirectR2(array $metadata): void
    {
        $r2 = $metadata['r2'] ?? null;
        if (!is_array($r2) || empty($r2['key'])) {
            return;
        }
        if (($metadata['status'] ?? '') === 'completed' && !empty($metadata['video_id'])) {
            return;
        }

        try {
            $disk = Storage::disk('s3');
            if (!empty($r2['completed'])) {
                $disk->delete($r2['key']);
                if (!empty($metadata['poster_filename'])) {
                    $disk->delete('content/' . $metadata['poster_filename']);
                }
                return;
            }

            if (method_exists($disk, 'getClient') && !empty($r2['upload_id'])) {
                $config = config('filesystems.disks.s3', []);
                $bucket = trim((string) ($config['bucket'] ?? ''));
                if ($bucket !== '') {
                    $disk->getClient()->abortMultipartUpload([
                        'Bucket' => $bucket,
                        'Key' => $r2['key'],
                        'UploadId' => $r2['upload_id'],
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to clean up a stale direct R2 upload.', [
                'key' => $r2['key'],
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / 1024 / 1024, 1) . ' MB';
        }
        return number_format($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }
}
