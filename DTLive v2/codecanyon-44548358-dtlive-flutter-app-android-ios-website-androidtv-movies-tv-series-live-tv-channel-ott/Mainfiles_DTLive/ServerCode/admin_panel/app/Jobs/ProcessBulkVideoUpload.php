<?php

namespace App\Jobs;

use App\Models\Type;
use App\Models\Video;
use App\Services\VideoTranscoder;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Move one staged movie to the configured storage disk outside the web request. */
class ProcessBulkVideoUpload implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 21600;
    public $backoff = [30, 120, 300];
    public $uniqueFor = 86400;

    public function __construct(
        public string $uploadId,
        public int $adminId
    ) {
        $this->onQueue('media-video');
    }

    public function uniqueId(): string
    {
        return $this->adminId . ':' . $this->uploadId;
    }

    public function handle(): void
    {
        $state = $this->readState();
        if (!$state) {
            throw new Exception('The staged upload receipt could not be found.');
        }
        if (($state['status'] ?? '') === 'completed') {
            return;
        }

        $this->writeState($state, [
            'status' => 'processing',
            'processing_at' => now()->toIso8601String(),
            'error' => null,
        ]);

        $disk = null;
        $targetPath = null;
        $posterTargetPath = null;
        $uploadedVideo = false;
        $uploadedPoster = false;
        try {
            $save = $state['save'] ?? [];
            $type = Type::where('id', (int) ($save['type_id'] ?? 0))
                ->where('type', 1)
                ->where('status', 1)
                ->first();
            if (!$type) {
                throw new Exception('The movie type is no longer active.');
            }

            $directory = $this->stagingDirectory();
            $videoPath = $directory . DIRECTORY_SEPARATOR . $this->uploadId . '.mp4';
            $directR2 = $this->directR2State($state);
            if ($directR2) {
                $videoSize = (int) ($state['size'] ?? 0);
            } else {
                $videoSize = is_file($videoPath) ? filesize($videoPath) : false;
                if ($videoSize === false || (int) $videoSize !== (int) ($state['size'] ?? 0)) {
                    throw new Exception('The staged video is incomplete or has changed.');
                }
            }

            $storageType = filter_var(config('filesystems.r2_only', false), FILTER_VALIDATE_BOOLEAN)
                ? 2
                : (int) ($save['storage_type'] ?? Storage_Type());
            $diskName = [1 => 'public', 2 => 's3', 3 => 'wasabi'][$storageType] ?? null;
            if (!$diskName) {
                throw new Exception('The configured video storage type is not supported.');
            }

            $filename = (string) ($state['video_filename'] ?? ('vid_' . date('Y_m_d_') . $this->uploadId . '.mp4'));
            $targetPath = $directR2['key'] ?? ('content/' . $filename);
            $disk = Storage::disk($diskName);
            $existing = Video::where('video_320', $filename)->first();
            if (!$directR2 && (!$existing || !$disk->exists($targetPath))) {
                $stream = fopen($videoPath, 'rb');
                if ($stream === false) {
                    throw new Exception('Unable to read the completed staged video.');
                }
                try {
                    if (!$disk->put($targetPath, $stream)) {
                        throw new Exception('Unable to store the video in configured storage.');
                    }
                    $uploadedVideo = true;
                } finally {
                    fclose($stream);
                }
            } elseif ($directR2 && !$disk->exists($targetPath)) {
                throw new Exception('The completed R2 video object is missing.');
            }

            $posterPath = $directory . DIRECTORY_SEPARATOR . $this->uploadId . '.poster.jpg';
            $posterFilename = (string) ($state['poster_filename'] ?? ('vid_' . date('Y_m_d_') . $this->uploadId . '.jpg'));
            $posterTargetPath = 'content/' . $posterFilename;
            $posterAlreadyUploaded = $directR2 && !empty($directR2['poster_uploaded']);
            if (!$posterAlreadyUploaded && !$directR2 && !is_file($posterPath)) {
                try {
                    app(VideoTranscoder::class)->createPoster($videoPath, $posterPath);
                } catch (Throwable $previewException) {
                    Log::warning('Bulk video preview generation skipped in queue worker.', [
                        'upload' => $state['name'] ?? $this->uploadId,
                        'error' => $previewException->getMessage(),
                    ]);
                    $posterPath = public_path('assets/imgs/no_img.png');
                }
            } elseif (!$posterAlreadyUploaded && $directR2) {
                // A direct upload has no local source to run ffmpeg against.
                // Use the standard placeholder until the browser preview is
                // uploaded, which keeps the VPS free of video copies.
                $posterPath = public_path('assets/imgs/no_img.png');
            }

            if (!$posterAlreadyUploaded && (!$existing || !$disk->exists($posterTargetPath))) {
                $posterStream = fopen($posterPath, 'rb');
                if ($posterStream === false) {
                    throw new Exception('Unable to read the generated video preview.');
                }
                try {
                    if (!$disk->put($posterTargetPath, $posterStream)) {
                        throw new Exception('Unable to store the video preview in configured storage.');
                    }
                    $uploadedPoster = true;
                } finally {
                    fclose($posterStream);
                }
            }

            if (!$existing) {
                $existing = DB::transaction(function () use ($type, $save, $storageType, $filename, $posterFilename) {
                    return Video::create([
                        'type_id' => $type->id,
                        'video_type' => $type->type,
                        'channel_id' => 0,
                        'producer_id' => 0,
                        'category_id' => (string) ($save['category_id'] ?? 0),
                        'language_id' => (string) ($save['language_id'] ?? 0),
                        'cast_id' => '',
                        'name' => trim((string) ($save['title'] ?? '')),
                        'storage_type' => $storageType,
                        'thumbnail' => $posterFilename,
                        'landscape' => $posterFilename,
                        'description' => '',
                        'video_storage_type' => $storageType,
                        'video_upload_type' => 'server_video',
                        'video_320' => $filename,
                        'video_480' => '',
                        'video_720' => '',
                        'video_1080' => '',
                        'video_extension' => 'mp4',
                        'video_duration' => 0,
                        'trailer_storage_type' => $storageType,
                        'trailer_type' => '',
                        'trailer_url' => '',
                        'subtitle_storage_type' => $storageType,
                        'subtitle_type' => 'server_video',
                        'subtitle_lang_1' => '',
                        'subtitle_1' => '',
                        'subtitle_lang_2' => '',
                        'subtitle_2' => '',
                        'subtitle_lang_3' => '',
                        'subtitle_3' => '',
                        'release_date' => '',
                        'is_premium' => 0,
                        'is_title' => 0,
                        'is_download' => 0,
                        'is_comment' => 0,
                        'is_like' => 0,
                        'is_rent' => 0,
                        'price' => 0,
                        'rent_day' => 0,
                        'avg_rating' => 0,
                        'total_review' => 0,
                        'total_view' => 0,
                        'total_like' => 0,
                        'status' => 1,
                    ]);
                });
            }

            $this->writeState($state, [
                'status' => 'completed',
                'video_id' => $existing->id,
                'completed_at' => now()->toIso8601String(),
                'error' => null,
            ]);
            $this->cleanupStaging($directory);
        } catch (Throwable $exception) {
            if ($disk && $uploadedVideo && $targetPath) {
                $disk->delete($targetPath);
            }
            if ($disk && $uploadedPoster && $posterTargetPath) {
                $disk->delete($posterTargetPath);
            }
            $this->writeState($state, [
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $state = $this->readState();
        if ($state && ($state['status'] ?? '') !== 'completed') {
            $this->writeState($state, [
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);
        }
    }

    private function stagingDirectory(): string
    {
        return storage_path('app/bulk-video-staging/' . $this->adminId . '/' . $this->uploadId);
    }

    private function directR2State(array $state): ?array
    {
        $r2 = $state['r2'] ?? null;
        if (!is_array($r2) || empty($r2['completed']) || empty($r2['key'])) {
            return null;
        }
        return $r2;
    }

    private function readState(): ?array
    {
        $metadataPath = $this->stagingDirectory() . DIRECTORY_SEPARATOR . 'meta.json';
        if (is_file($metadataPath)) {
            $state = json_decode((string) file_get_contents($metadataPath), true);
            if (is_array($state)) {
                return $state;
            }
        }

        $videoPath = $this->stagingDirectory() . DIRECTORY_SEPARATOR . $this->uploadId . '.mp4';
        $size = is_file($videoPath) ? filesize($videoPath) : false;
        if ($size === false || $size < 8) {
            return null;
        }

        return [
            'name' => $this->uploadId . '.mp4',
            'size' => (int) $size,
            'chunk_count' => (int) ceil($size / (1024 * 1024)),
            'complete' => true,
            'created_at' => time(),
        ];
    }

    private function writeState(array $state, array $changes): void
    {
        $directory = $this->stagingDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new Exception('Unable to create the staged upload status directory.');
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'meta.json';
        $temporary = $path . '.tmp';
        $encoded = json_encode(array_merge($state, $changes), JSON_UNESCAPED_SLASHES);
        if ($encoded === false || file_put_contents($temporary, $encoded, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new Exception('Unable to persist the staged upload status.');
        }
    }

    private function cleanupStaging(string $directory): void
    {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (basename($path) !== 'meta.json' && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
