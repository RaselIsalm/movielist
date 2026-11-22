<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaRouteTest extends TestCase
{
    public function test_cloud_media_route_supports_http_ranges(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('content/video.mp4', '0123456789');

        $response = $this->withHeaders(['Range' => 'bytes=2-5'])
            ->get('/api/media/content/video.mp4?storage=2');

        $response->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 2-5/10')
            ->assertHeader('Content-Length', '4');

        ob_start();
        $response->sendContent();
        $body = ob_get_clean();
        $this->assertSame('2345', $body);
    }
}
