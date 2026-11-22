<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $publicPath = config('app.public_path');

        if (is_string($publicPath) && $publicPath !== '') {
            $this->app->usePublicPath($publicPath);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();

        // Set dynamic AWS S3 config from DB/cache
        $storage = Cache::rememberForever('s3_storage_settings', function () {
            return Storage_Setting();
        });

        if ($storage) {
            $s3Endpoint = trim((string) ($storage['s3_endpoint'] ?? ''));

            // AWS S3
            Config::set('filesystems.disks.s3', [
                'driver' => 's3',
                'key'    => $storage['s3_access_key'],
                'secret' => $storage['s3_secret_key'],
                'region' => $storage['s3_region'],
                'bucket' => $storage['s3_bucket_name'],
                // Cloudflare R2 is S3-compatible and requires its endpoint
                // to be passed to the runtime client (the admin setting is
                // stored in s3_endpoint). Path-style addressing is reliable
                // with R2's S3 endpoint and keeps bucket routing explicit.
                'endpoint' => $s3Endpoint !== '' ? rtrim($s3Endpoint, '/') : null,
                'use_path_style_endpoint' => $s3Endpoint !== '',
            ]);

            // Wasabi
            Config::set('filesystems.disks.wasabi', [
                'driver' => 's3',
                'key' => $storage['wasabi_access_key'],
                'secret' => $storage['wasabi_secret_key'],
                'region' => $storage['wasabi_region'],
                'bucket' => $storage['wasabi_bucket_name'],
                'endpoint' => $storage['wasabi_endpoint'],
            ]);
        }
    }
}
