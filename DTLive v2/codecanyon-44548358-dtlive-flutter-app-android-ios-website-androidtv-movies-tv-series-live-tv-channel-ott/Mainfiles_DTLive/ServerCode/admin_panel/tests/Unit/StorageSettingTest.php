<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\AppSettingController;
use App\Models\Storage_Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StorageSettingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
        ]);
        DB::purge('sqlite');
        Schema::create('tbl_storage_setting', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key');
            $table->string('value');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        foreach ([
            'storage_type' => '1',
            's3_access_key' => 'old-s3-key',
            's3_secret_key' => 'old-s3-secret',
            's3_bucket_name' => 'old-bucket',
            's3_region' => 'us-east-1',
            's3_endpoint' => 'https://old.example.test',
            'wasabi_access_key' => 'wasabi-key',
            'wasabi_secret_key' => 'wasabi-secret',
            'wasabi_bucket_name' => 'wasabi-bucket',
            'wasabi_region' => 'us-east-1',
            'wasabi_endpoint' => 'https://wasabi.example.test',
        ] as $key => $value) {
            Storage_Setting::create(compact('key', 'value'));
        }
    }

    public function test_saving_r2_settings_does_not_restore_old_s3_values(): void
    {
        $request = Request::create('/admin/app-setting/storagesetting', 'POST', [
            'id' => 1,
            'storage_type' => 2,
            's3_access_key' => 'r2-key',
            's3_secret_key' => 'r2-secret',
            's3_bucket_name' => 'linkviral-media-prod',
            's3_region' => 'auto',
            's3_endpoint' => 'https://example.r2.cloudflarestorage.com',
        ]);

        $response = (new AppSettingController())->StorageSetting($request);

        $this->assertSame(200, $response->getData(true)['status']);
        $this->assertSame('r2-key', Storage_Setting::where('key', 's3_access_key')->value('value'));
        $this->assertSame('linkviral-media-prod', Storage_Setting::where('key', 's3_bucket_name')->value('value'));
        $this->assertSame('wasabi-key', Storage_Setting::where('key', 'wasabi_access_key')->value('value'));
    }
}
