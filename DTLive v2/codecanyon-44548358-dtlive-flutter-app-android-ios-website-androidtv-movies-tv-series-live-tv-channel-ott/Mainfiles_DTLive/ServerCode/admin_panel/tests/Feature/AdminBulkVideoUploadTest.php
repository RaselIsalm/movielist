<?php

namespace Tests\Feature;

use App\Http\Middleware\Installation;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Language;
use App\Models\Storage_Setting;
use App\Models\Type;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBulkVideoUploadTest extends TestCase
{
    private $type;
    private $category;
    private $language;

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/bulk-video-staging/17'));
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
        ]);
        DB::purge('sqlite');
        $this->createTestSchema();
        Storage::fake('public');
        $this->withoutMiddleware(Installation::class);
        $this->actingAs(new Admin(['id' => 17, 'user_name' => 'Test Admin']), 'admin');

        $this->type = Type::create([
            'name' => 'Movies', 'type' => 1, 'storage_type' => 1,
            'icon' => '', 'sort_order' => 1, 'status' => 1,
        ]);
        $this->category = Category::create([
            'name' => 'Bulk test category', 'storage_type' => 1,
            'image' => '', 'sort_order' => 1, 'status' => 1,
        ]);
        $this->language = Language::create([
            'name' => 'English', 'storage_type' => 1,
            'image' => '', 'sort_order' => 1, 'status' => 1,
        ]);
        Storage_Setting::create(['key' => 'storage_type', 'value' => '1', 'status' => 1]);
    }

    public function test_it_saves_a_batch_with_shared_metadata_and_is_safe_to_retry(): void
    {
        $uploadIds = [
            $this->uploadMp4('first.mp4', $this->validMp4('first video')),
            $this->uploadMp4('second.mp4', $this->validMp4('second video')),
            $this->uploadMp4('third.mp4', $this->validMp4('third video')),
        ];

        $payload = $this->batchPayload($uploadIds);
        $response = $this->postJson(route('admin.video.bulk.save'), $payload);
        $response->assertOk()->assertJson(['status' => 200, 'count' => 3]);
        $this->assertSame(3, Video::count());

        foreach (Video::orderBy('id')->get() as $video) {
            $this->assertSame('Shared batch title', $video->name);
            $this->assertSame((string) $this->category->id, $video->category_id);
            $this->assertSame((string) $this->language->id, $video->language_id);
            $this->assertSame('server_video', $video->video_upload_type);
            $this->assertSame('mp4', $video->video_extension);
            $this->assertSame(1, $video->status);
            $this->assertSame(0, $video->is_premium);
            $this->assertSame(0, $video->is_rent);
            $this->assertNotEmpty($video->thumbnail);
            $this->assertSame($video->thumbnail, $video->landscape);
            Storage::disk('public')->assertExists('content/' . $video->video_320);
            Storage::disk('public')->assertExists('content/' . $video->thumbnail);
        }

        $response->assertJsonPath('video_ids.0', Video::orderBy('id')->value('id'));
        $this->postJson(route('admin.video.bulk.save'), $payload)->assertOk()->assertJson($response->json());
        $this->assertSame(3, Video::count(), 'A retried save request must not duplicate the batch.');
    }

    public function test_it_saves_batches_of_ten_twenty_and_thirty_files(): void
    {
        foreach ([10, 20, 30] as $batchSize) {
            $uploadIds = [];
            for ($index = 0; $index < $batchSize; $index++) {
                $uploadIds[] = $this->uploadMp4(
                    'movie-' . $batchSize . '-' . $index . '.mp4',
                    $this->validMp4('batch ' . $batchSize . ' item ' . $index)
                );
            }

            $this->postJson(route('admin.video.bulk.save'), $this->batchPayload($uploadIds))
                ->assertOk()->assertJson(['status' => 200, 'count' => $batchSize]);
        }

        $this->assertSame(60, Video::count());
        $this->assertSame(60, Video::where('name', 'Shared batch title')->count());
    }

    public function test_it_can_finalize_a_large_upload_queue_one_video_at_a_time_and_retry_safely(): void
    {
        $uploadIds = [
            $this->uploadMp4('queue-first.mp4', $this->validMp4('first queued video')),
            $this->uploadMp4('queue-second.mp4', $this->validMp4('second queued video')),
            $this->uploadMp4('queue-third.mp4', $this->validMp4('third queued video')),
        ];

        foreach ($uploadIds as $uploadId) {
            $payload = $this->batchPayload([$uploadId]);
            $this->postJson(route('admin.video.bulk.save'), $payload)
                ->assertOk()->assertJson(['status' => 200, 'count' => 1]);
        }

        // If a response is lost after the server committed a file, retrying
        // that file must return its receipt instead of creating a duplicate.
        $this->postJson(route('admin.video.bulk.save'), $this->batchPayload([$uploadIds[1]]))
            ->assertOk()->assertJson(['status' => 200, 'count' => 1]);

        $this->assertSame(3, Video::count());
    }

    public function test_it_uses_the_configured_s3_storage_disk(): void
    {
        Storage::fake('s3');
        Storage_Setting::where('key', 'storage_type')->update(['value' => '2']);
        $uploadId = $this->uploadMp4('cloud.mp4', $this->validMp4('cloud stored video'));

        $this->postJson(route('admin.video.bulk.save'), $this->batchPayload([$uploadId]))
            ->assertOk()->assertJson(['status' => 200, 'count' => 1]);
        $video = Video::firstOrFail();
        $this->assertSame(2, $video->video_storage_type);
        Storage::disk('s3')->assertExists('content/' . $video->video_320);
        Storage::disk('s3')->assertExists('content/' . $video->thumbnail);
    }

    public function test_it_uses_the_configured_wasabi_storage_disk(): void
    {
        Storage::fake('wasabi');
        Storage_Setting::where('key', 'storage_type')->update(['value' => '3']);
        $uploadId = $this->uploadMp4('wasabi.mp4', $this->validMp4('wasabi stored video'));

        $this->postJson(route('admin.video.bulk.save'), $this->batchPayload([$uploadId]))
            ->assertOk()->assertJson(['status' => 200, 'count' => 1]);
        $video = Video::firstOrFail();
        $this->assertSame(3, $video->video_storage_type);
        Storage::disk('wasabi')->assertExists('content/' . $video->video_320);
        Storage::disk('wasabi')->assertExists('content/' . $video->thumbnail);
    }

    public function test_it_rejects_a_save_until_every_upload_is_complete(): void
    {
        $init = $this->postJson(route('admin.video.bulk.init'), [
            'upload_name' => 'not-finished.mp4', 'file_size' => 32,
        ])->assertOk();

        $this->postJson(route('admin.video.bulk.save'), $this->batchPayload([$init->json('upload_id')]))
            ->assertUnprocessable();
        $this->assertSame(0, Video::count());
    }

    public function test_it_saves_when_the_server_cannot_generate_a_missing_preview(): void
    {
        $init = $this->postJson(route('admin.video.bulk.init'), [
            'upload_name' => 'no-poster.mp4', 'file_size' => strlen($this->validMp4('video')),
        ])->assertOk();
        $uploadId = $init->json('upload_id');
        $contents = $this->validMp4('video');
        $this->post(route('admin.video.bulk.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'chunk_count' => 1,
            'chunk' => UploadedFile::fake()->createWithContent('no-poster.mp4', $contents),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->postJson(route('admin.video.bulk.save'), $this->batchPayload([$uploadId]))
            ->assertOk()->assertJson(['status' => 200, 'count' => 1]);
        $this->assertSame(1, Video::count());
    }

    public function test_it_rejects_missing_metadata_and_inactive_category_ids(): void
    {
        $uploadId = $this->uploadMp4('metadata.mp4', $this->validMp4('metadata validation'));
        $payload = $this->batchPayload([$uploadId]);
        unset($payload['title']);
        $this->postJson(route('admin.video.bulk.save'), $payload)->assertUnprocessable();

        Category::where('id', $this->category->id)->update(['status' => 0]);
        $this->postJson(route('admin.video.bulk.save'), $this->batchPayload([$uploadId]))->assertUnprocessable();
        $this->assertSame(0, Video::count());
    }

    public function test_it_rejects_non_mp4_names_and_invalid_mp4_contents(): void
    {
        $this->postJson(route('admin.video.bulk.init'), [
            'upload_name' => 'movie.txt', 'file_size' => 12,
        ])->assertUnprocessable();

        $init = $this->postJson(route('admin.video.bulk.init'), [
            'upload_name' => 'fake.mp4', 'file_size' => 12,
        ])->assertOk();
        $this->post(route('admin.video.bulk.chunk'), [
            'upload_id' => $init->json('upload_id'),
            'chunk_index' => 0,
            'chunk_count' => 1,
            'chunk' => UploadedFile::fake()->createWithContent('fake.mp4', 'not an mp4!'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(0, Video::count());
    }

    public function test_chunk_retry_is_idempotent_and_large_files_are_assembled(): void
    {
        $contents = $this->validMp4(str_repeat('x', 1024 * 1024 + 11));
        $init = $this->postJson(route('admin.video.bulk.init'), [
            'upload_name' => 'large.mp4', 'file_size' => strlen($contents),
        ])->assertOk();
        $uploadId = $init->json('upload_id');
        $chunkSize = 1024 * 1024;

        $firstChunk = substr($contents, 0, $chunkSize);
        $chunkPayload = [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'chunk_count' => 2,
            'chunk' => UploadedFile::fake()->createWithContent('large.mp4', $firstChunk),
        ];
        $this->post(route('admin.video.bulk.chunk'), $chunkPayload, ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['complete' => false]);
        $chunkPayload['chunk'] = UploadedFile::fake()->createWithContent('large.mp4', $firstChunk);
        $this->post(route('admin.video.bulk.chunk'), $chunkPayload, ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['complete' => false]);
        $this->post(route('admin.video.bulk.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 1,
            'chunk_count' => 2,
            'chunk' => UploadedFile::fake()->createWithContent('large.mp4', substr($contents, $chunkSize)),
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['complete' => true]);
        $this->uploadPoster($uploadId);

        $this->postJson(route('admin.video.bulk.save'), $this->batchPayload([$uploadId]))
            ->assertOk()->assertJson(['status' => 200, 'count' => 1]);
        $video = Video::firstOrFail();
        $this->assertSame('Shared batch title', $video->name);
        Storage::disk('public')->assertExists('content/' . $video->video_320);
    }

    public function test_it_removes_staged_data_after_a_database_batch_rolls_back(): void
    {
        Schema::table('tbl_video', function (Blueprint $table) {
            $table->unique('name');
        });
        $uploadIds = [
            $this->uploadMp4('one.mp4', $this->validMp4('one')),
            $this->uploadMp4('two.mp4', $this->validMp4('two')),
        ];

        $this->postJson(route('admin.video.bulk.save'), $this->batchPayload($uploadIds))->assertStatus(400);
        $this->assertSame(0, Video::count(), 'The database transaction must roll back all batch rows.');
        $this->assertSame([], Storage::disk('public')->allFiles('content'));
    }

    public function test_upload_endpoints_require_an_authenticated_admin(): void
    {
        auth()->guard('admin')->logout();
        $this->postJson(route('admin.video.bulk.init'), [
            'upload_name' => 'movie.mp4', 'file_size' => 10,
        ])->assertUnauthorized();
    }

    public function test_it_lists_server_videos_with_missing_or_unavailable_preview_files(): void
    {
        Storage::disk('public')->put('content/legacy.mp4', $this->validMp4('legacy video'));
        Storage::disk('public')->put('content/present.jpg', 'existing image');
        $missing = $this->createPreviewVideo(['video_320' => 'legacy.mp4']);
        $broken = $this->createPreviewVideo([
            'video_320' => 'legacy.mp4',
            'thumbnail' => 'missing-file.jpg',
            'landscape' => 'missing-file.jpg',
        ]);
        $complete = $this->createPreviewVideo([
            'video_320' => 'legacy.mp4',
            'thumbnail' => 'present.jpg',
            'landscape' => 'present.jpg',
        ]);
        $this->createPreviewVideo(['video_upload_type' => 'external', 'video_320' => 'https://example.test/video.mp4']);

        $response = $this->getJson(route('admin.video.previews.missing', ['type_id' => $this->type->id]));
        $response->assertOk()->assertJson(['status' => 200, 'count' => 2]);
        $this->assertSame(
            [$missing->id, $broken->id],
            array_column($response->json('items'), 'id')
        );
        $this->assertNotContains($complete->id, array_column($response->json('items'), 'id'));
    }

    public function test_it_saves_a_generated_preview_to_both_missing_video_image_fields(): void
    {
        $video = $this->createPreviewVideo(['video_320' => 'legacy.mp4']);

        $response = $this->post(route('admin.video.previews.save', ['type_id' => $this->type->id]), [
            'video_id' => $video->id,
            'thumbnail' => UploadedFile::fake()->image('frame.jpg', 64, 36),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('status', 200);
        $video->refresh();
        $this->assertNotEmpty($video->thumbnail);
        $this->assertSame($video->thumbnail, $video->landscape);
        Storage::disk('public')->assertExists('content/' . $video->thumbnail);
        $this->assertStringContainsString('api/media/content/' . $video->thumbnail, $response->json('thumbnail'));
    }

    public function test_it_preserves_a_valid_existing_preview_and_saves_missing_landscape_to_the_image_storage_disk(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('content/original.jpg', 'existing image');
        $video = $this->createPreviewVideo([
            'storage_type' => 2,
            'video_storage_type' => 2,
            'video_320' => 'cloud.mp4',
            'thumbnail' => 'original.jpg',
            'landscape' => '',
        ]);

        $this->post(route('admin.video.previews.save', ['type_id' => $this->type->id]), [
            'video_id' => $video->id,
            'thumbnail' => UploadedFile::fake()->image('frame.jpg', 64, 36),
        ], ['Accept' => 'application/json'])->assertOk();

        $video->refresh();
        $this->assertSame('original.jpg', $video->thumbnail);
        $this->assertNotEmpty($video->landscape);
        Storage::disk('s3')->assertExists('content/' . $video->landscape);
    }

    public function test_it_rejects_invalid_generated_preview_files_and_other_content_types(): void
    {
        $video = $this->createPreviewVideo(['video_320' => 'legacy.mp4']);
        $this->post(route('admin.video.previews.save', ['type_id' => $this->type->id]), [
            'video_id' => $video->id,
            'thumbnail' => UploadedFile::fake()->createWithContent('invalid.jpg', 'not an image'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $tvType = Type::create([
            'name' => 'TV Shows', 'type' => 2, 'storage_type' => 1,
            'icon' => '', 'sort_order' => 2, 'status' => 1,
        ]);
        $otherTypeVideo = $this->createPreviewVideo(['type_id' => $tvType->id, 'video_320' => 'other.mp4']);
        $this->post(route('admin.video.previews.save', ['type_id' => $this->type->id]), [
            'video_id' => $otherTypeVideo->id,
            'thumbnail' => UploadedFile::fake()->image('frame.jpg', 64, 36),
        ], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_preview_repair_endpoints_require_an_authenticated_admin(): void
    {
        auth()->guard('admin')->logout();

        $this->getJson(route('admin.video.previews.missing', ['type_id' => $this->type->id]))
            ->assertUnauthorized();
        $this->postJson(route('admin.video.previews.save', ['type_id' => $this->type->id]), [])
            ->assertUnauthorized();
    }

    private function uploadMp4(string $name, string $contents): string
    {
        $init = $this->postJson(route('admin.video.bulk.init'), [
            'upload_name' => $name, 'file_size' => strlen($contents),
        ])->assertOk();
        $uploadId = $init->json('upload_id');

        $this->post(route('admin.video.bulk.chunk'), [
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'chunk_count' => 1,
            'chunk' => UploadedFile::fake()->createWithContent($name, $contents),
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['complete' => true]);
        $this->uploadPoster($uploadId);

        return $uploadId;
    }

    private function uploadPoster(string $uploadId): void
    {
        $this->post(route('admin.video.bulk.thumbnail'), [
            'upload_id' => $uploadId,
            'thumbnail' => UploadedFile::fake()->image('poster.jpg', 64, 36),
        ], ['Accept' => 'application/json'])->assertOk();
    }

    private function batchPayload(array $uploadIds): array
    {
        return [
            'title' => 'Shared batch title',
            'type_id' => $this->type->id,
            'category_id' => $this->category->id,
            'language_id' => $this->language->id,
            'upload_ids' => $uploadIds,
        ];
    }

    private function validMp4(string $payload): string
    {
        return "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2" . $payload;
    }

    private function createPreviewVideo(array $attributes = []): Video
    {
        return Video::create(array_merge([
            'type_id' => $this->type->id,
            'video_type' => 1,
            'name' => 'Legacy preview test',
            'storage_type' => 1,
            'thumbnail' => '',
            'landscape' => '',
            'video_storage_type' => 1,
            'video_upload_type' => 'server_video',
            'video_320' => '',
            'status' => 1,
        ], $attributes));
    }

    private function createTestSchema(): void
    {
        Schema::create('tbl_type', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('type');
            $table->integer('storage_type');
            $table->string('icon');
            $table->integer('sort_order');
            $table->integer('status');
            $table->timestamps();
        });
        Schema::create('tbl_category', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('storage_type');
            $table->string('image');
            $table->integer('sort_order');
            $table->integer('status');
            $table->timestamps();
        });
        Schema::create('tbl_language', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('storage_type');
            $table->string('image');
            $table->integer('sort_order');
            $table->integer('status');
            $table->timestamps();
        });
        Schema::create('tbl_storage_setting', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->string('value');
            $table->integer('status');
            $table->timestamps();
        });
        Schema::create('tbl_video', function (Blueprint $table) {
            $table->increments('id');
            foreach ([
                'type_id', 'video_type', 'channel_id', 'producer_id', 'video_duration',
                'storage_type', 'video_storage_type', 'trailer_storage_type', 'subtitle_storage_type',
                'is_premium', 'is_title', 'is_download', 'is_comment', 'is_like', 'is_rent',
                'price', 'rent_day', 'total_review', 'total_view', 'total_like', 'status',
            ] as $column) {
                $table->integer($column)->default(0);
            }
            foreach ([
                'category_id', 'language_id', 'cast_id', 'name', 'thumbnail', 'landscape',
                'description', 'video_upload_type', 'video_320', 'video_480', 'video_720',
                'video_1080', 'video_extension', 'trailer_type', 'trailer_url', 'subtitle_type',
                'subtitle_lang_1', 'subtitle_lang_2', 'subtitle_lang_3', 'subtitle_1',
                'subtitle_2', 'subtitle_3', 'release_date',
            ] as $column) {
                $table->text($column)->default('');
            }
            $table->float('avg_rating')->default(0);
            $table->timestamps();
        });
    }
}
