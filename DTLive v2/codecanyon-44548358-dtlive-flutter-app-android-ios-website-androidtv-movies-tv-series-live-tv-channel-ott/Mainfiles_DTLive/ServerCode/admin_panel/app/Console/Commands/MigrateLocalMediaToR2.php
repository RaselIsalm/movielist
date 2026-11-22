<?php

namespace App\Console\Commands;

use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateLocalMediaToR2 extends Command
{
    protected $signature = 'media:migrate-r2
        {--dry-run : Audit local media without uploading or changing the database}
        {--manifest= : JSON manifest path (defaults to storage/app/r2-migration-manifest.json)}';

    protected $description = 'Copy local video media to the configured S3-compatible R2 disk and verify every object';

    private const GROUPS = [
        'storage_type' => ['thumbnail', 'landscape'],
        'video_storage_type' => ['video_320', 'video_480', 'video_720', 'video_1080'],
        'trailer_storage_type' => ['trailer_url'],
        'subtitle_storage_type' => ['subtitle_1', 'subtitle_2', 'subtitle_3'],
    ];

    public function handle(): int
    {
        $source = Storage::disk('public');
        $target = Storage::disk('s3');
        $dryRun = (bool) $this->option('dry-run');
        $manifest = [];
        $summary = [
            'videos' => 0,
            'objects_checked' => 0,
            'objects_uploaded' => 0,
            'objects_verified' => 0,
            'objects_missing' => 0,
            'objects_failed' => 0,
            'database_rows_updated' => 0,
        ];

        Video::query()->orderBy('id')->chunkById(100, function ($videos) use (
            $source,
            $target,
            $dryRun,
            &$manifest,
            &$summary
        ): void {
            foreach ($videos as $video) {
                $summary['videos']++;
                $updates = [];
                $row = [
                    'id' => $video->id,
                    'title' => $video->name,
                    'status' => (int) $video->status,
                    'objects' => [],
                    'storage_updates' => [],
                ];

                foreach (self::GROUPS as $storageColumn => $fields) {
                    $storageType = (int) ($video->{$storageColumn} ?? 1);
                    $groupReady = true;
                    $groupChanged = false;

                    foreach ($fields as $field) {
                        $value = trim((string) ($video->{$field} ?? ''));
                        if ($value === '' || $this->isRemoteUrl($value) || $storageType !== 1) {
                            continue;
                        }

                        $summary['objects_checked']++;
                        $path = 'content/' . ltrim($value, '/');
                        $object = [
                            'field' => $field,
                            'path' => $path,
                            'status' => 'pending',
                        ];
                        if (!$source->exists($path)) {
                            $summary['objects_missing']++;
                            $object['status'] = 'missing_local';
                            $groupReady = false;
                            $row['objects'][] = $object;
                            continue;
                        }

                        try {
                            $localSize = (int) $source->size($path);
                            $localHash = $this->hashStream($source->readStream($path));
                            $object['bytes'] = $localSize;
                            $object['sha256'] = $localHash;

                            if ($dryRun) {
                                $object['status'] = 'would_upload';
                                $groupChanged = true;
                                $row['objects'][] = $object;
                                continue;
                            }

                            $stream = $source->readStream($path);
                            if ($stream === false || !$target->put($path, $stream, ['visibility' => 'private'])) {
                                if (is_resource($stream)) {
                                    fclose($stream);
                                }
                                throw new \RuntimeException('R2 upload returned false.');
                            }
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                            $summary['objects_uploaded']++;

                            $remoteSize = (int) $target->size($path);
                            $remoteHash = $this->hashStream($target->readStream($path));
                            if ($remoteSize !== $localSize || !hash_equals($localHash, $remoteHash)) {
                                throw new \RuntimeException('R2 size or checksum verification failed.');
                            }
                            $summary['objects_verified']++;
                            $object['status'] = 'verified';
                            $object['remote_bytes'] = $remoteSize;
                            $object['remote_sha256'] = $remoteHash;
                            $groupChanged = true;
                        } catch (Throwable $exception) {
                            $summary['objects_failed']++;
                            $object['status'] = 'failed';
                            $object['error'] = $exception->getMessage();
                            $groupReady = false;
                        }
                        $row['objects'][] = $object;
                    }

                    if ($groupReady && $groupChanged) {
                        $updates[$storageColumn] = 2;
                        $row['storage_updates'][$storageColumn] = 2;
                    }
                }

                if (!$dryRun && $updates !== []) {
                    DB::transaction(function () use ($video, $updates): void {
                        Video::query()->whereKey($video->id)->update($updates);
                    });
                    $summary['database_rows_updated']++;
                }
                $manifest[] = $row;
            }
        });

        $manifestPath = $this->option('manifest')
            ? (string) $this->option('manifest')
            : storage_path('app/r2-migration-manifest.json');
        if (!str_starts_with($manifestPath, DIRECTORY_SEPARATOR)) {
            $manifestPath = base_path($manifestPath);
        }
        if (!is_dir(dirname($manifestPath))) {
            mkdir(dirname($manifestPath), 0750, true);
        }
        file_put_contents($manifestPath, json_encode([
            'generated_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'summary' => $summary,
            'videos' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $this->table(['metric', 'value'], collect($summary)->map(fn ($value, $key) => [$key, $value])->values()->all());
        $this->line('Manifest: ' . $manifestPath);
        if ($dryRun) {
            $this->comment('Dry run only: no objects or database rows were changed.');
        }

        return $summary['objects_failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function hashStream($stream): string
    {
        if (!is_resource($stream)) {
            throw new \RuntimeException('Unable to open media stream for checksum verification.');
        }
        $context = hash_init('sha256');
        while (!feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                fclose($stream);
                throw new \RuntimeException('Unable to read media stream for checksum verification.');
            }
            if ($chunk !== '') {
                hash_update($context, $chunk);
            }
        }
        fclose($stream);
        return hash_final($context);
    }

    private function isRemoteUrl(string $value): bool
    {
        return (bool) preg_match('/^https?:\/\//i', $value);
    }
}
