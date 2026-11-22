<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateLocalMediaToR2 extends Command
{
    protected $signature = 'media:migrate-r2
        {--dry-run : Audit local media without uploading or changing the database}
        {--delete-local : Delete each local source only after its R2 object passes verification}
        {--manifest= : JSON manifest path (defaults to storage/app/r2-migration-manifest.json)}';

    protected $description = 'Copy every local media object to the configured S3-compatible R2 disk and verify it';

    private const RECORD_GROUPS = [
        ['table' => 'tbl_video', 'type' => 'storage_type', 'folder' => 'content', 'fields' => ['thumbnail', 'landscape']],
        ['table' => 'tbl_video', 'type' => 'video_storage_type', 'folder' => 'content', 'fields' => ['video_320', 'video_480', 'video_720', 'video_1080']],
        ['table' => 'tbl_video', 'type' => 'trailer_storage_type', 'folder' => 'content', 'fields' => ['trailer_url']],
        ['table' => 'tbl_video', 'type' => 'subtitle_storage_type', 'folder' => 'content', 'fields' => ['subtitle_1', 'subtitle_2', 'subtitle_3']],
        ['table' => 'tbl_tv_show', 'type' => 'storage_type', 'folder' => 'content', 'fields' => ['thumbnail', 'landscape']],
        ['table' => 'tbl_tv_show', 'type' => 'trailer_storage_type', 'folder' => 'content', 'fields' => ['trailer_url']],
        ['table' => 'tbl_tv_show_video', 'type' => 'storage_type', 'folder' => 'content', 'fields' => ['thumbnail', 'landscape']],
        ['table' => 'tbl_tv_show_video', 'type' => 'video_storage_type', 'folder' => 'content', 'fields' => ['video_320', 'video_480', 'video_720', 'video_1080']],
        ['table' => 'tbl_tv_show_video', 'type' => 'subtitle_storage_type', 'folder' => 'content', 'fields' => ['subtitle_1', 'subtitle_2', 'subtitle_3']],
        ['table' => 'tbl_shorts', 'type' => 'storage_type', 'folder' => 'content', 'fields' => ['thumbnail']],
        ['table' => 'tbl_shorts', 'type' => 'trailer_storage_type', 'folder' => 'content', 'fields' => ['trailer_url']],
        ['table' => 'tbl_shorts_episode', 'type' => 'storage_type', 'folder' => 'content', 'fields' => ['thumbnail']],
        ['table' => 'tbl_shorts_episode', 'type' => 'video_storage_type', 'folder' => 'content', 'fields' => ['video_320']],
        ['table' => 'tbl_avatar', 'type' => 'storage_type', 'folder' => 'avatar', 'fields' => ['image']],
        ['table' => 'tbl_cast', 'type' => 'storage_type', 'folder' => 'cast', 'fields' => ['image']],
        ['table' => 'tbl_category', 'type' => 'storage_type', 'folder' => 'category', 'fields' => ['image']],
        ['table' => 'tbl_channel', 'type' => 'storage_type', 'folder' => 'channel', 'fields' => ['portrait_img', 'landscape_img']],
        ['table' => 'tbl_language', 'type' => 'storage_type', 'folder' => 'language', 'fields' => ['image']],
        ['table' => 'tbl_notification', 'type' => 'storage_type', 'folder' => 'notification', 'fields' => ['image']],
        ['table' => 'tbl_onboarding_screen', 'type' => 'storage_type', 'folder' => 'app', 'fields' => ['image']],
        ['table' => 'tbl_page', 'type' => 'storage_type', 'folder' => 'page', 'fields' => ['icon']],
        ['table' => 'tbl_producer', 'type' => 'storage_type', 'folder' => 'producer', 'fields' => ['image']],
        ['table' => 'tbl_social_link', 'type' => 'storage_type', 'folder' => 'app', 'fields' => ['image']],
        ['table' => 'tbl_type', 'type' => 'storage_type', 'folder' => 'type', 'fields' => ['icon']],
        ['table' => 'tbl_user', 'type' => 'storage_type', 'folder' => 'user', 'fields' => ['image']],
    ];

    public function handle(): int
    {
        $source = Storage::disk('public');
        $target = Storage::disk('s3');
        $dryRun = (bool) $this->option('dry-run');
        $verified = [];
        $manifest = ['objects' => [], 'records' => [], 'summary' => []];
        $summary = [
            'local_objects_checked' => 0,
            'objects_uploaded' => 0,
            'objects_reused' => 0,
            'objects_verified' => 0,
            'objects_missing' => 0,
            'objects_failed' => 0,
            'local_objects_deleted' => 0,
            'records_scanned' => 0,
            'database_rows_updated' => 0,
            'referenced_media_verified' => 0,
            'referenced_media_missing' => 0,
        ];

        foreach ($source->allFiles() as $path) {
            if ($this->isIgnoredPath($path)) {
                continue;
            }
            $summary['local_objects_checked']++;
            $object = ['path' => $path, 'status' => 'pending'];
            try {
                $localSize = (int) $source->size($path);
                $localHash = $this->hashStream($source->readStream($path));
                $object['bytes'] = $localSize;
                $object['sha256'] = $localHash;

                if ($dryRun) {
                    $object['status'] = 'would_upload';
                    $verified[$path] = true;
                } else {
                    $remoteIsCurrent = false;
                    try {
                        $remoteIsCurrent = $target->exists($path)
                            && (int) $target->size($path) === $localSize;
                    } catch (Throwable) {
                        $remoteIsCurrent = false;
                    }

                    if ($remoteIsCurrent) {
                        $object['status'] = 'reused';
                        $summary['objects_reused']++;
                    } else {
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
                    }
                    $remoteSize = (int) $target->size($path);
                    $remoteHash = $this->hashStream($target->readStream($path));
                    if ($remoteSize !== $localSize || !hash_equals($localHash, $remoteHash)) {
                        throw new \RuntimeException('R2 size or checksum verification failed.');
                    }
                    $object['remote_bytes'] = $remoteSize;
                    $object['remote_sha256'] = $remoteHash;
                    $object['status'] = 'verified';
                    $verified[$path] = true;
                    $summary['objects_verified']++;
                    if ($this->option('delete-local') && $source->delete($path)) {
                        $object['local_deleted'] = true;
                        $summary['local_objects_deleted']++;
                    }
                }
            } catch (Throwable $exception) {
                $summary['objects_failed']++;
                $object['status'] = 'failed';
                $object['error'] = $exception->getMessage();
            }
            $manifest['objects'][] = $object;
        }

        foreach (self::RECORD_GROUPS as $definition) {
            if (!Schema::hasTable($definition['table'])) {
                continue;
            }
            DB::table($definition['table'])->orderBy('id')->chunkById(100, function ($rows) use (
                $definition,
                $verified,
                $dryRun,
                &$manifest,
                &$summary
            ): void {
                foreach ($rows as $row) {
                    $summary['records_scanned']++;
                    $storageType = (int) ($row->{$definition['type']} ?? 1);
                    if ($storageType !== 1) {
                        continue;
                    }
                    $ready = true;
                    $hasMedia = false;
                    $record = ['table' => $definition['table'], 'id' => $row->id, 'group' => $definition['type'], 'fields' => []];
                    foreach ($definition['fields'] as $field) {
                        $value = trim((string) ($row->{$field} ?? ''));
                        if ($value === '' || $this->isRemoteUrl($value)) {
                            continue;
                        }
                        $hasMedia = true;
                        $path = trim($definition['folder'], '/') . '/' . basename($value);
                        if (!isset($verified[$path])) {
                            $ready = false;
                            $summary['referenced_media_missing']++;
                            $record['fields'][$field] = ['path' => $path, 'status' => 'missing_or_unverified'];
                        } else {
                            $summary['referenced_media_verified']++;
                            $record['fields'][$field] = ['path' => $path, 'status' => 'verified'];
                        }
                    }
                    if ($ready && $hasMedia) {
                        $record['storage_update'] = [$definition['type'] => 2];
                        if (!$dryRun) {
                            DB::table($definition['table'])->where('id', $row->id)->update([$definition['type'] => 2]);
                            $summary['database_rows_updated']++;
                        }
                    }
                    if ($record['fields'] !== []) {
                        $manifest['records'][] = $record;
                    }
                }
            });
        }

        $summary['manifest_objects'] = count($manifest['objects']);
        $summary['manifest_records'] = count($manifest['records']);
        $manifest['generated_at'] = now()->toIso8601String();
        $manifest['dry_run'] = $dryRun;
        $manifest['summary'] = $summary;

        $manifestPath = $this->option('manifest')
            ? (string) $this->option('manifest')
            : storage_path('app/r2-migration-manifest.json');
        if (!str_starts_with($manifestPath, DIRECTORY_SEPARATOR)) {
            $manifestPath = base_path($manifestPath);
        }
        if (!is_dir(dirname($manifestPath))) {
            mkdir(dirname($manifestPath), 0750, true);
        }
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $this->table(['metric', 'value'], collect($summary)->map(fn ($value, $key) => [$key, $value])->values()->all());
        $this->line('Manifest: ' . $manifestPath);
        if ($dryRun) {
            $this->comment('Dry run only: no objects or database rows were changed.');
        } elseif ($this->option('delete-local')) {
            $this->comment('Verified local media was deleted after matching R2 checksums.');
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

    private function isIgnoredPath(string $path): bool
    {
        $name = basename($path);
        return $name === '' || str_starts_with($name, '._') || $name === '.gitignore';
    }

    private function isRemoteUrl(string $value): bool
    {
        return (bool) preg_match('/^https?:\/\//i', $value);
    }
}
