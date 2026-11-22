<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Creates browser-friendly MP4 quality variants for locally stored uploads.
 *
 * The source is never upscaled: a variant is only produced when the source
 * video is at least as tall as the target quality. This keeps small uploads
 * from being made larger without adding detail that is not present.
 */
class VideoTranscoder
{
    private const QUALITY_TARGETS = [
        'video_320' => 320,
        'video_480' => 480,
        'video_720' => 720,
        'video_1080' => 1080,
    ];

    /**
     * Fill missing quality fields from the local video_320 source.
     * Existing explicitly uploaded quality files are left untouched.
     */
    public function prepareVariants(array $variants, int $storageType = 1): array
    {
        $variants = array_merge([
            'video_320' => '',
            'video_480' => '',
            'video_720' => '',
            'video_1080' => '',
        ], $variants);

        if (!$this->isEnabled() || $storageType !== 1 || trim((string) $variants['video_320']) === '') {
            return $variants;
        }

        // If the uploader supplied any higher quality file, it is already the
        // authoritative variant set and should not be silently replaced.
        foreach (['video_480', 'video_720', 'video_1080'] as $field) {
            if (trim((string) $variants[$field]) !== '') {
                return $variants;
            }
        }

        $sourceName = basename((string) $variants['video_320']);
        $sourcePath = storage_path('app/public/content/' . $sourceName);
        if (!is_file($sourcePath)) {
            return $variants;
        }

        $created = [];
        try {
            $sourceHeight = $this->probeHeight($sourcePath);
            if ($sourceHeight < 1) {
                return $variants;
            }

            foreach (self::QUALITY_TARGETS as $field => $height) {
                // Always create a browser-friendly H.264 MP4 for the default
                // stream, even when the source is below 320p. Never upscale.
                if ($field === 'video_320') {
                    $height = min($sourceHeight, $height);
                } elseif ($sourceHeight < $height) {
                    continue;
                }
                // libx264 with yuv420p needs even dimensions. Probe values are
                // usually even, but normalize odd source heights explicitly.
                $height -= $height % 2;
                if ($height < 2) {
                    continue;
                }

                $filename = 'vid_' . date('Y_m_d_') . Str::uuid() . '_' . $height . 'p.mp4';
                $outputPath = storage_path('app/public/content/' . $filename);
                $this->transcode($sourcePath, $outputPath, $height);
                $variants[$field] = $filename;
                $created[] = $outputPath;
            }
        } catch (Throwable $exception) {
            foreach ($created as $path) {
                @unlink($path);
            }
            Log::warning('Video quality variant generation failed; keeping the original upload.', [
                'source' => $sourceName,
                'error' => $exception->getMessage(),
            ]);
            return [
                'video_320' => (string) $variants['video_320'],
                'video_480' => '',
                'video_720' => '',
                'video_1080' => '',
            ];
        }

        // A source below 320p is still playable, but there is no honest
        // higher-quality representation to generate from it.
        if ($variants['video_320'] === '') {
            $variants['video_320'] = (string) $sourceName;
        }

        return $variants;
    }

    private function isEnabled(): bool
    {
        return (bool) config('video.transcoding.enabled', true);
    }

    private function probeHeight(string $sourcePath): int
    {
        $process = new Process([
            (string) config('video.transcoding.ffprobe', 'ffprobe'),
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=height',
            '-of', 'csv=p=0',
            $sourcePath,
        ]);
        $process->setTimeout((int) config('video.transcoding.timeout', 900));
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'ffprobe failed.');
        }

        return (int) trim($process->getOutput());
    }

    private function transcode(string $sourcePath, string $outputPath, int $height): void
    {
        $process = new Process([
            (string) config('video.transcoding.ffmpeg', 'ffmpeg'),
            '-nostdin',
            '-hide_banner',
            '-loglevel', 'error',
            '-y',
            '-i', $sourcePath,
            '-map', '0:v:0',
            '-map', '0:a?',
            '-vf', 'scale=-2:' . $height,
            '-c:v', 'libx264',
            '-preset', (string) config('video.transcoding.preset', 'veryfast'),
            '-crf', (string) config('video.transcoding.crf', 21),
            '-pix_fmt', 'yuv420p',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-movflags', '+faststart',
            $outputPath,
        ]);
        $process->setTimeout((int) config('video.transcoding.timeout', 900));
        $process->run();
        if (!$process->isSuccessful() || !is_file($outputPath) || filesize($outputPath) < 1024) {
            @unlink($outputPath);
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'ffmpeg failed.');
        }
    }

    /** Generate a poster server-side when a browser cannot decode its source. */
    public function createPoster(string $sourcePath, string $outputPath): void
    {
        $process = new Process([
            (string) config('video.transcoding.ffmpeg', 'ffmpeg'),
            '-nostdin',
            '-hide_banner',
            '-loglevel', 'error',
            '-y',
            '-ss', '1',
            '-i', $sourcePath,
            '-map', '0:v:0',
            '-frames:v', '1',
            '-vf', 'scale=min(640\,iw):-2',
            '-q:v', '3',
            $outputPath,
        ]);
        // Poster extraction is a best-effort enhancement during a web
        // request. Keep a hard bound so one problematic/large upload cannot
        // hold the whole bulk-save request open indefinitely.
        $process->setTimeout(min((int) config('video.transcoding.preview_timeout', 60), 60));
        $process->run();

        if (!$process->isSuccessful() || !is_file($outputPath) || filesize($outputPath) < 256) {
            @unlink($outputPath);
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Unable to create a video preview with ffmpeg.');
        }
    }

    /** Repack an uploaded MP4 so the moov atom is available in the first range. */
    public function ensureFastStart(string $sourcePath, string $outputPath): void
    {
        $process = new Process([
            (string) config('video.transcoding.ffmpeg', 'ffmpeg'),
            '-nostdin',
            '-hide_banner',
            '-loglevel', 'error',
            '-y',
            '-i', $sourcePath,
            '-map', '0:v:0',
            '-map', '0:a?',
            '-c', 'copy',
            '-movflags', '+faststart',
            $outputPath,
        ]);
        $process->setTimeout((int) config('video.transcoding.timeout', 900));
        $process->run();
        if (!$process->isSuccessful() || !is_file($outputPath) || filesize($outputPath) < 1024) {
            @unlink($outputPath);
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'Unable to prepare a fast-start MP4.');
        }
    }
}
