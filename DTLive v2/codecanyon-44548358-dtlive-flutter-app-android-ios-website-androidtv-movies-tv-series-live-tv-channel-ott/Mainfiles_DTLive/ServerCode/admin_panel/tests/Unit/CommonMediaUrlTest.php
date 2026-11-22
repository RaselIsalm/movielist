<?php

namespace Tests\Unit;

use App\Models\Common;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommonMediaUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.image_url' => 'https://media.example.test/api/media/']);
        Storage::fake('public');
        Storage::disk('public')->put('content/poster.jpg', 'poster');
        Storage::disk('public')->put('content/video.mp4', 'video');
    }

    public function test_video_url_conversion_updates_each_response_item(): void
    {
        $result = (new Common())->videoNameToUrl([
            ['video_storage_type' => 1, 'video_320' => 'video.mp4'],
        ], 'video_320', 'content');

        $this->assertSame(
            'https://media.example.test/api/media/content/video.mp4',
            $result[0]['video_320']
        );
    }

    public function test_full_remote_urls_are_preserved_without_local_disk_validation(): void
    {
        $remote = 'https://media.example.test/content/video.mp4?signature=abc';

        $this->assertSame($remote, (new Common())->getVideo('content', $remote, 2));
        $result = (new Common())->videoNameToUrl([
            ['video_storage_type' => 2, 'video_320' => $remote],
        ], 'video_320', 'content');
        $this->assertSame($remote, $result[0]['video_320']);
    }

    public function test_private_cloud_media_uses_the_same_origin_range_endpoint(): void
    {
        config(['app.image_url' => 'https://media.example.test/api/media/']);
        Storage::fake('s3');
        Storage::disk('s3')->put('content/video.mp4', 'video');

        $this->assertSame(
            'https://media.example.test/api/media/content/video.mp4?storage=2',
            (new Common())->getVideo('content', 'video.mp4', 2)
        );
    }

    public function test_image_url_conversion_updates_each_response_item(): void
    {
        $result = (new Common())->imageNameToUrl([
            ['storage_type' => 1, 'image' => 'poster.jpg'],
        ], 'image', 'content', 'normal');

        $this->assertSame(
            'https://media.example.test/api/media/content/poster.jpg',
            $result[0]['image']
        );
    }
}
