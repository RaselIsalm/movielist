<?php

return [
    'transcoding' => [
        'enabled' => env('DTLIVE_VIDEO_TRANSCODE_ON_UPLOAD', true),
        'ffmpeg' => env('DTLIVE_FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('DTLIVE_FFPROBE_BINARY', 'ffprobe'),
        'preset' => env('DTLIVE_VIDEO_TRANSCODE_PRESET', 'veryfast'),
        'crf' => (int) env('DTLIVE_VIDEO_TRANSCODE_CRF', 21),
        'timeout' => (int) env('DTLIVE_VIDEO_TRANSCODE_TIMEOUT', 900),
    ],
];
