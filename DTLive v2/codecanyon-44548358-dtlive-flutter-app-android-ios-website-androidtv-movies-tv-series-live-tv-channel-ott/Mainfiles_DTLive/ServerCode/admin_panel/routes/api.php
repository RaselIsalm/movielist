<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Serve catalog files through the API middleware stack so Flutter Web receives
// same-origin responses. Cloud disks are streamed with HTTP Range support;
// this is required for private R2 buckets because their presigned responses
// do not include browser CORS headers.
Route::get('media/{folder}/{filename}', function (Request $request, string $folder, string $filename) {
    $publicFolders = ['app', 'avatar', 'cast', 'category', 'channel', 'content', 'language', 'notification', 'type', 'user'];

    abort_unless(in_array($folder, $publicFolders, true), 404);
    abort_unless(basename($filename) === $filename && $filename !== '.' && $filename !== '..', 404);

    $storageType = (int) $request->query('storage', 1);
    $diskName = [1 => 'public', 2 => 's3', 3 => 'wasabi'][$storageType] ?? null;
    abort_unless($diskName !== null, 404);
    $path = $folder . '/' . $filename;
    $disk = Storage::disk($diskName);

    if ($diskName === 'public') {
        $localPath = $disk->path($path);
        abort_unless(is_file($localPath), 404);
        return response()->file($localPath, [
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    abort_unless($disk->exists($path), 404);
    $size = (int) $disk->size($path);
    abort_unless($size > 0, 404);

    $start = 0;
    $end = $size - 1;
    $range = $request->header('Range');
    if ($range !== null && preg_match('/bytes=(\d*)-(\d*)/i', $range, $matches)) {
        if ($matches[1] === '' && $matches[2] === '') {
            return response('', 416, ['Content-Range' => 'bytes */' . $size]);
        }
        if ($matches[1] === '') {
            $suffixLength = min($size, (int) $matches[2]);
            $start = $size - $suffixLength;
        } else {
            $start = (int) $matches[1];
            $end = $matches[2] === '' ? $end : (int) $matches[2];
        }
        if ($start < 0 || $start >= $size || $end < $start) {
            return response('', 416, ['Content-Range' => 'bytes */' . $size]);
        }
        $end = min($end, $size - 1);
    }

    $length = $end - $start + 1;
    $contentType = match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
        'mp4', 'm4v' => 'video/mp4',
        'webm' => 'video/webm',
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => 'application/octet-stream',
    };

    // Ask the S3-compatible client for exactly the requested range. This
    // avoids downloading a whole movie when a browser seeks near the end.
    $body = null;
    $stream = null;
    $adapter = $disk->getAdapter();
    if (method_exists($adapter, 'getClient')) {
        $client = $adapter->getClient();
        $options = [
            'Bucket' => $disk->getConfig()['bucket'],
            'Key' => $path,
        ];
        if ($start !== 0 || $end !== $size - 1) {
            $options['Range'] = 'bytes=' . $start . '-' . $end;
        }
        $object = $client->getObject($options);
        $body = $object['Body'];
        $contentType = $object['ContentType'] ?? $contentType;
    }
    if ($body === null) {
        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 404);
        if ($start > 0) {
            fseek($stream, $start);
        }
    }

    $headers = [
        'Content-Type' => $contentType,
        'Content-Length' => (string) $length,
        'Accept-Ranges' => 'bytes',
        'Cache-Control' => 'public, max-age=3600',
        'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        'Access-Control-Allow-Origin' => '*',
        'X-Content-Type-Options' => 'nosniff',
    ];
    $status = ($start !== 0 || $end !== $size - 1) ? 206 : 200;
    if ($status === 206) {
        $headers['Content-Range'] = 'bytes ' . $start . '-' . $end . '/' . $size;
    }

    return response()->stream(function () use ($body, $stream, $length) {
        $remaining = $length;
        if ($body !== null) {
            while ($remaining > 0 && !$body->eof()) {
                $chunk = $body->read(min(1024 * 1024, $remaining));
                if ($chunk === '') {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }
            return;
        }
        while ($remaining > 0 && !feof($stream)) {
            $chunk = fread($stream, min(1024 * 1024, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
            flush();
        }
        fclose($stream);
    }, $status, $headers);
})->withoutMiddleware('throttle:api')->name('api.media');

Route::group(['middleware' => 'apipurchasecode'], function () {
    Route::group(['middleware' => 'apiauthtoken'], function () {

        // ---------------- UsersController ----------------
        Route::post('register', [UserController::class, 'register']);
        Route::post('login', [UserController::class, 'login']);
        Route::post('get_profile', [UserController::class, 'get_profile']);
        Route::post('update_profile', [UserController::class, 'update_profile']);
        Route::post('get_tv_login_code', [UserController::class, 'get_tv_login_code']);
        Route::post('tv_login', [UserController::class, 'tv_login']);
        Route::post('check_tv_login', [UserController::class, 'check_tv_login']);
        Route::post('parent_control_check_password', [UserController::class, 'parent_control_check_password']);
        Route::post('get_device_sync_list', [UserController::class, 'get_device_sync_list']);
        Route::post('logout_device_sync', [UserController::class, 'logout_device_sync']);
        Route::post('add_remove_device_watching', [UserController::class, 'add_remove_device_watching']);

        // ---------------- HomeController ----------------
        Route::post('general_setting', [HomeController::class, 'general_setting']);
        Route::post('get_payment_option', [HomeController::class, 'get_payment_option']);
        Route::post('get_social_link', [HomeController::class, 'get_social_link']);
        Route::post('get_onboarding_screen', [HomeController::class, 'get_onboarding_screen']);
        Route::post('get_package', [HomeController::class, 'get_package']);
        Route::post('get_avatar', [HomeController::class, 'get_avatar']);
        Route::post('get_category', [HomeController::class, 'get_category']);
        Route::post('get_language', [HomeController::class, 'get_language']);
        Route::post('get_channel', [HomeController::class, 'get_channel']);
        Route::post('get_type', [HomeController::class, 'get_type']);
        Route::post('get_pages', [HomeController::class, 'get_pages']);
        Route::post('add_transaction', [HomeController::class, 'add_transaction']);
        Route::post('update_transaction_status', [HomeController::class, 'update_transaction_status']);
        Route::post('get_transaction_list', [HomeController::class, 'get_transaction_list']);
        Route::post('add_rent_transaction', [HomeController::class, 'add_rent_transaction']);
        Route::post('get_coupon_list', [HomeController::class, 'get_coupon_list']);
        Route::post('apply_coupon', [HomeController::class, 'apply_coupon']);
        Route::post('user_rent_content_list', [HomeController::class, 'user_rent_content_list']);
        Route::post('rent_content_list', [HomeController::class, 'rent_content_list']);
        Route::post('get_banner', [HomeController::class, 'get_banner']);
        Route::post('section_list', [HomeController::class, 'section_list']);
        Route::post('section_detail', [HomeController::class, 'section_detail']);
        Route::post('content_detail', [HomeController::class, 'content_detail']);
        Route::post('get_releted_content', [HomeController::class, 'get_releted_content']);
        Route::post('cast_detail', [HomeController::class, 'cast_detail']);
        Route::post('content_by_category', [HomeController::class, 'content_by_category']);
        Route::post('content_by_language', [HomeController::class, 'content_by_language']);
        Route::post('content_by_cast', [HomeController::class, 'content_by_cast']);
        Route::post('content_by_channel', [HomeController::class, 'content_by_channel']);
        Route::post('add_continue_watching', [HomeController::class, 'add_continue_watching']);
        Route::post('remove_continue_watching', [HomeController::class, 'remove_continue_watching']);
        Route::post('add_remove_like', [HomeController::class, 'add_remove_like']);
        Route::post('add_remove_bookmark', [HomeController::class, 'add_remove_bookmark']);
        Route::post('add_video_view', [HomeController::class, 'add_video_view']);
        Route::post('get_bookmark_video', [HomeController::class, 'get_bookmark_video']);
        Route::post('add_comment', [HomeController::class, 'add_comment']);
        Route::post('edit_comment', [HomeController::class, 'edit_comment']);
        Route::post('delete_comment', [HomeController::class, 'delete_comment']);
        Route::post('get_comment', [HomeController::class, 'get_comment']);
        Route::post('get_replay_comment', [HomeController::class, 'get_replay_comment']);
        Route::post('get_video_by_season_id', [HomeController::class, 'get_video_by_season_id']);
        Route::post('search_content', [HomeController::class, 'search_content']);
        Route::post('get_continue_watching', [HomeController::class, 'get_continue_watching']);
        Route::post('add_remove_kids_mode', [HomeController::class, 'add_remove_kids_mode']);
        Route::post('create_razorpay_order', [HomeController::class, 'create_razorpay_order']);
        Route::post('get_shorts_list', [HomeController::class, 'get_shorts_list']);
        Route::post('get_shorts_episode', [HomeController::class, 'get_shorts_episode']);
        Route::post('get_notification', [HomeController::class, 'get_notification']);
        Route::post('read_notification', [HomeController::class, 'read_notification']);
        Route::post('get_refer_earn_history', [HomeController::class, 'getReferEarnHistory']);
        Route::post('add_wallet_amount', [HomeController::class, 'add_wallet_amount']);
        Route::post('get_wallet_transaction', [HomeController::class, 'get_wallet_transaction']);
        Route::post('get_vdocipher_otp', [HomeController::class, 'get_vdocipher_otp']);
        Route::post('add_review',  [HomeController::class, 'add_review']);
        Route::post('get_reviews', [HomeController::class, 'get_reviews']);

        // Coupon System — v2.1
        Route::post('validate_coupon', [HomeController::class, 'validate_coupon']);
    });
});
