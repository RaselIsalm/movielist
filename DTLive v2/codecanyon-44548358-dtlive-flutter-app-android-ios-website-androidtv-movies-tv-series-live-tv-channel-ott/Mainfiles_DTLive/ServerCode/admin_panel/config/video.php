<?php

return [
    'transcoding' => [
        'enabled' => env('DTLIVE_VIDEO_TRANSCODE_ON_UPLOAD', true),
        'ffmpeg' => env('DTLIVE_FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('DTLIVE_FFPROBE_BINARY', 'ffprobe'),
        'preset' => env('DTLIVE_VIDEO_TRANSCODE_PRESET', 'veryfast'),
        'crf' => (int) env('DTLIVE_VIDEO_TRANSCODE_CRF', 21),
        'timeout' => (int) env('DTLIVE_VIDEO_TRANSCODE_TIMEOUT', 900),
        // Repacking an R2 object requires downloading and uploading the full
        // video again. Keep the automatic save path instant by leaving this
        // optional; the range-capable player can stream the original MP4.
        'fast_start_on_save' => env('DTLIVE_R2_FASTSTART_ON_SAVE', false),
    ],
];
