<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Cast;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\Common;
use App\Models\Language;
use App\Models\Like;
use App\Models\Producer;
use App\Models\Rent_Price_List;
use App\Models\Review;
use App\Models\Type;
use App\Models\Video;
use App\Models\Video_Watch;
use App\Models\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Video Type = 1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts
// Video Upload Type = server_video, external, youtube, live_stream_url
// Trailer Type = server_video, external, youtube
// Subtitle Type = server_video, external

class VideoController extends Controller
{
    private $folder_content = "content";
    private $folder_cast = "cast";
    public $common;
    public function __construct()
    {
        $this->common = new Common;
    }

    public function index(Request $request, $type_id)
    {
        try {
            $params['type'] = Type::where('id', $type_id)->where('status', 1)->first();
            if (!$params['type']) {
                return view('errors.404');
            }

            $params['releases_type'] = Type::whereIn('type', [1, 6, 7])->where('status', 1)->get();
            $params['channel_list'] = Channel::where('status', 1)->latest()->get();
            $params['producer'] = Producer::where('status', 1)->latest()->get();

            if ($request->ajax()) {
                $input_search   = $request['input_search'];
                $input_rent     = $request['input_rent'];
                $input_premium  = $request['input_premium'];
                $input_status   = $request['input_status'];
                $input_producer = $request['input_producer'];

                $query = Video::where('type_id', $type_id)
                    ->select(['id', 'type_id', 'video_type', 'producer_id', 'name', 'thumbnail', 'landscape', 'storage_type', 'is_premium', 'is_rent', 'avg_rating', 'total_review', 'total_view', 'total_like', 'release_date', 'status', 'created_at', 'video_storage_type', 'video_upload_type', 'video_320']);

                if ($input_search) {
                    $query->where('name', 'LIKE', "%{$input_search}%");
                }
                if ($input_producer && $input_producer != 0) {
                    $query->where('producer_id', $input_producer);
                }
                if ($input_rent && $input_rent != 0) {
                    $query->where('is_rent', 1);
                }
                if ($input_premium !== null && $input_premium !== 'all') {
                    $query->where('is_premium', $input_premium);
                }
                if ($input_status !== null && $input_status !== 'all') {
                    $query->where('status', $input_status);
                }
                $data = $query->with('producer')->orderBy('status', 'desc')->latest();

                return DataTables()::of($data)
                    ->addIndexColumn()
                    ->addColumn('thumbnail_img', function ($row) {
                        return $this->common->getImage($this->folder_content, $row->thumbnail, 'portrait', $row->storage_type);
                    })
                    ->addColumn('name_col', function ($row) {
                        $date     = $row->release_date ? '<br><small class="text-muted">' . e($row->release_date) . '</small>' : '';
                        $producer = $row->producer ? '<br><small class="text-muted">' . e($row->producer->user_name) . '</small>' : '';
                        return '<div style="text-align:left;">' . e($row->name) . $date . $producer . '</div>';
                    })
                    ->addColumn('type_badge', function ($row) {
                        $badges = '<div class="vi-td-type">';
                        if ($row->is_premium == 1) {
                            $badges .= '<span class="vi-badge vi-badge-premium"><i class="fa-solid fa-crown fa-md mr-1"></i>' . __('label.premium') . '</span>';
                        }
                        if ($row->is_rent == 1) {
                            $badges .= '<span class="vi-badge vi-badge-rent"><i class="fa-solid fa-tag fa-md mr-1"></i>' . __('label.rent') . '</span>';
                        }
                        if ($row->is_premium == 0 && $row->is_rent == 0) {
                            $badges .= '<span class="vi-badge vi-badge-free"><i class="fa-solid fa-unlock fa-md mr-1"></i>' . __('label.free') . '</span>';
                        }
                        $badges .= '</div>';
                        return $badges;
                    })
                    ->addColumn('stats', function ($row) {
                        $views  = No_Format($row->total_view ?? 0);
                        $rating = number_format($row->avg_rating ?? 0, 1);
                        $count  = No_Format($row->total_review ?? 0);
                        return '<div class="vi-stat-row"><i class="fa-solid fa-eye fa-md"></i> ' . $views . '</div>'
                            . '<div class="vi-stat-row vi-stat-rating"><i class="fa-solid fa-star fa-md"></i> ' . $rating . ' <span class="vi-stat-count">(' . $count . ')</span></div>';
                    })
                    ->addColumn('status', function ($row) {
                        if ($row->status == 1) {
                            return "<div class='d-flex flex-column align-items-center' style='gap:4px;'>
                                <label id='$row->id' class='status-toggle status-on' onclick='change_status($row->id)'><span class='status-toggle-track'><span class='status-toggle-thumb'></span></span></label>
                                <small id='text_$row->id' class='font-weight-bold text-success'>" . __('label.show') . "</small>
                            </div>";
                        }
                        return "<div class='d-flex flex-column align-items-center' style='gap:4px;'>
                            <label id='$row->id' class='status-toggle status-off' onclick='change_status($row->id)'><span class='status-toggle-track'><span class='status-toggle-thumb'></span></span></label>
                            <small id='text_$row->id' class='font-weight-bold text-danger'>" . __('label.hide') . "</small>
                        </div>";
                    })
                    ->addColumn('action', function ($row) use ($type_id) {

                        $btn = '<div class="d-flex justify-content-around">';

                        // Play button — server_video or external direct MP4
                        if (in_array($row->video_upload_type, ['server_video', 'external'])) {
                            $videoFile = $row->video_320;
                            if ($videoFile) {
                                if ($row->video_upload_type === 'server_video') {
                                    $videoUrl = $this->common->getVideo($this->folder_content, $videoFile, $row->video_storage_type);
                                } else {
                                    $videoUrl = $videoFile;
                                }
                                $thumbUrl = $this->common->getImage($this->folder_content, $row->landscape, 'landscape', $row->storage_type);
                                $btn .= '<button type="button" class="edit-delete-btn mr-2 video" data-toggle="modal" data-target="#videoModal" data-video="' . e($videoUrl) . '" data-image="' . e($thumbUrl) . '"><i class="fa-solid fa-circle-play fa-xl"></i></button>';
                            }
                        }

                        if ($row->video_type == 5) {
                            $btn .= '<button type="button" class="edit-delete-btn mr-2 releases_modal" data-toggle="modal" data-target="#ReleasesModal" data-id="' . $row->id . '" title="' . __('label.releases') . '"><i class="fa-solid fa-satellite-dish fa-xl"></i></button>';
                        }
                        $btn .= '<a href="' . route('admin.video.edit', ['video_id' => $row->id, 'type_id' => $type_id]) . '" class="edit-delete-btn mr-2"><i class="fa-solid fa-pen-to-square fa-xl"></i></a>';
                        $btn .= '<button type="button" class="edit-delete-btn" title="' . __('label.delete') . '" onclick="deleteVideo(\'' . route('admin.video.show', ['video_id' => $row->id, 'type_id' => $type_id]) . '\')"><i class="fa-solid fa-trash-can fa-xl"></i></button>';
                        $btn .= '</div>';
                        return $btn;
                    })
                    ->rawColumns(['thumbnail_img', 'name_col', 'type_badge', 'stats', 'status', 'action'])
                    ->make(true);
            }

            return view('admin.video.index', $params);
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }
    /** Return existing server videos that still need a generated preview frame. */
    public function missingPreviews($type_id)
    {
        $type = Type::where('id', $type_id)->where('status', 1)->first();
        if (!$type) {
            return response()->json(['status' => 404, 'errors' => ['Content type not found.']], 404);
        }

        $videos = Video::where('type_id', $type_id)
            ->where('video_upload_type', 'server_video')
            ->whereNotNull('video_320')
            ->where('video_320', '!=', '')
            ->orderBy('id')
            ->get(['id', 'name', 'video_320', 'video_storage_type', 'thumbnail', 'landscape', 'storage_type'])
            ->filter(function ($video) {
                return !$this->previewImageExists($video->thumbnail, $video->storage_type)
                    || !$this->previewImageExists($video->landscape, $video->storage_type);
            });

        $skipped = 0;
        $items = $videos->map(function ($video) use (&$skipped) {
            $url = $this->common->getVideo($this->folder_content, $video->video_320, $video->video_storage_type);
            if (!is_string($url) || $url === '') {
                $skipped++;
                return null;
            }

            return ['id' => $video->id, 'name' => $video->name, 'url' => $url];
        })->filter()->values();

        return response()->json([
            'status' => 200,
            'count' => $items->count(),
            'skipped_count' => $skipped,
            'items' => $items,
        ]);
    }

    /** Store a browser-captured video frame on an existing video with missing artwork. */
    public function saveGeneratedPreview(Request $request, $type_id)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|integer',
            'thumbnail' => 'required|image|mimes:jpeg,jpg,png|max:5120',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 400, 'errors' => $validator->errors()->all()], 422);
        }

        $type = Type::where('id', $type_id)->where('status', 1)->first();
        if (!$type) {
            return response()->json(['status' => 404, 'errors' => ['Content type not found.']], 404);
        }

        $video = Video::where('id', $request->input('video_id'))
            ->where('type_id', $type_id)
            ->where('video_upload_type', 'server_video')
            ->first();
        if (!$video || !$video->video_320) {
            return response()->json(['status' => 404, 'errors' => ['The server video could not be found.']], 404);
        }

        $thumbnail = $request->file('thumbnail');
        $imageInfo = @getimagesize($thumbnail->getRealPath());
        if (!$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return response()->json(['status' => 422, 'errors' => ['The preview must be a valid JPEG or PNG image.']], 422);
        }

        $needsThumbnail = !$this->previewImageExists($video->thumbnail, $video->storage_type);
        $needsLandscape = !$this->previewImageExists($video->landscape, $video->storage_type);
        if (!$needsThumbnail && !$needsLandscape) {
            return response()->json(['status' => 200, 'already_complete' => true]);
        }

        $storageType = (int) $video->storage_type;
        $diskName = [1 => 'public', 2 => 's3', 3 => 'wasabi'][$storageType] ?? null;
        if (!$diskName) {
            return response()->json(['status' => 422, 'errors' => ['This video uses an unsupported image storage type.']], 422);
        }

        $extension = $imageInfo[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $filename = 'vid_' . date('Y_m_d_') . Str::uuid() . '.' . $extension;
        $path = $this->folder_content . '/' . $filename;
        $stored = false;

        try {
            $stored = Storage::disk($diskName)->put($path, file_get_contents($thumbnail->getRealPath()), 'public');
            if (!$stored) {
                throw new Exception('Unable to store the generated preview image.');
            }

            $attached = DB::transaction(function () use ($video, $filename) {
                $locked = Video::where('id', $video->id)->lockForUpdate()->first();
                if (!$locked) {
                    throw new Exception('The video was removed before its preview could be saved.');
                }
                $changed = false;
                if (!$this->previewImageExists($locked->thumbnail, $locked->storage_type)) {
                    $locked->thumbnail = $filename;
                    $changed = true;
                }
                if (!$this->previewImageExists($locked->landscape, $locked->storage_type)) {
                    $locked->landscape = $filename;
                    $changed = true;
                }
                if ($changed) {
                    $locked->save();
                }
                return $changed;
            });
            if (!$attached) {
                Storage::disk($diskName)->delete($path);
                return response()->json(['status' => 200, 'already_complete' => true]);
            }
        } catch (Exception $exception) {
            if ($stored) {
                Storage::disk($diskName)->delete($path);
            }
            return response()->json(['status' => 400, 'errors' => [$exception->getMessage()]], 400);
        }

        $video->refresh();
        return response()->json([
            'status' => 200,
            'thumbnail' => $this->common->getImage($this->folder_content, $video->thumbnail, 'portrait', $video->storage_type),
            'landscape' => $this->common->getImage($this->folder_content, $video->landscape, 'landscape', $video->storage_type),
        ]);
    }

    private function previewImageExists($filename, $storageType)
    {
        $diskName = [1 => 'public', 2 => 's3', 3 => 'wasabi'][(int) $storageType] ?? null;
        if (!$diskName || trim((string) $filename) === '') {
            return false;
        }

        try {
            return Storage::disk($diskName)->exists($this->folder_content . '/' . $filename);
        } catch (Exception $exception) {
            return false;
        }
    }

    public function create($type_id)
    {
        try {
            $params['type'] = Type::where('id', $type_id)->where('status', 1)->first();
            if (!$params['type']) {
                return view('errors.404');
            }

            $params['channel'] = Channel::where('status', 1)->latest()->get();
            $params['category'] = Category::where('status', 1)->orderBy('sort_order', 'asc')->get();
            $params['language'] = Language::where('status', 1)->orderBy('sort_order', 'asc')->get();
            $params['producer'] = Producer::get();
            $params['cast'] = Cast::where('status', 1)->get();
            $params['rent_price_list'] = Rent_Price_List::where('status', 1)->get();

            return view('admin.video.add', $params);
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }

    /** Start a private, admin-scoped staged upload for the bulk movie uploader. */
    public function bulkUploadInit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upload_name' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 400, 'errors' => $validator->errors()->all()], 422);
        }

        $name = trim($request->input('upload_name'));
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'mp4') {
            return response()->json(['status' => 400, 'errors' => ['Only MP4 video files are supported.']], 422);
        }

        $this->cleanupExpiredBulkUploads();

        $uploadId = (string) Str::uuid();
        $upload = [
            'name' => basename($name),
            'size' => (int) $request->input('file_size'),
            'chunk_count' => null,
            'complete' => false,
            'created_at' => time(),
        ];
        $uploads = session()->get('bulk_video_uploads', []);
        $uploads[$uploadId] = $upload;
        session()->put('bulk_video_uploads', $uploads);

        $directory = $this->bulkUploadDirectory($uploadId);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            unset($uploads[$uploadId]);
            session()->put('bulk_video_uploads', $uploads);
            return response()->json(['status' => 500, 'errors' => ['Unable to prepare temporary upload storage.']], 500);
        }

        return response()->json([
            'status' => 200,
            'upload_id' => $uploadId,
            'chunk_size' => 1024 * 1024,
        ]);
    }

    /** Receive one retry-safe chunk and assemble the file when every chunk arrives. */
    public function bulkUploadChunk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|uuid',
            'chunk_index' => 'required|integer|min:0',
            'chunk_count' => 'required|integer|min:1|max:100000',
            'chunk' => 'required|file|max:1024',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 400, 'errors' => $validator->errors()->all()], 422);
        }

        $uploadId = $request->input('upload_id');
        $uploads = session()->get('bulk_video_uploads', []);
        $upload = $uploads[$uploadId] ?? null;
        if (!$upload) {
            return response()->json(['status' => 404, 'errors' => ['Upload session not found.']], 404);
        }
        if ($upload['complete']) {
            return response()->json(['status' => 200, 'complete' => true, 'uploaded_bytes' => $upload['size']]);
        }

        $chunkIndex = (int) $request->input('chunk_index');
        $chunkCount = (int) $request->input('chunk_count');
        $chunkSize = 1024 * 1024;
        $expectedCount = (int) ceil($upload['size'] / $chunkSize);
        if ($chunkIndex >= $chunkCount || $chunkCount !== $expectedCount) {
            return response()->json(['status' => 400, 'errors' => ['Invalid chunk sequence.']], 422);
        }
        if ($upload['chunk_count'] !== null && $upload['chunk_count'] !== $chunkCount) {
            return response()->json(['status' => 409, 'errors' => ['Chunk count changed during upload.']], 409);
        }

        $expectedBytes = min($chunkSize, $upload['size'] - ($chunkIndex * $chunkSize));
        $chunk = $request->file('chunk');
        if ($chunk->getSize() !== $expectedBytes) {
            return response()->json(['status' => 422, 'errors' => ['The uploaded chunk has an invalid size.']], 422);
        }

        $directory = $this->bulkUploadDirectory($uploadId);
        $chunkPath = $directory . DIRECTORY_SEPARATOR . $uploadId . '.chunk.' . $chunkIndex;
        if (is_file($chunkPath)) {
            if (filesize($chunkPath) !== $expectedBytes || hash_file('sha256', $chunkPath) !== hash_file('sha256', $chunk->getRealPath())) {
                return response()->json(['status' => 409, 'errors' => ['Chunk retry did not match the saved chunk.']], 409);
            }
        } else {
            $chunk->move($directory, basename($chunkPath));
        }

        $upload['chunk_count'] = $chunkCount;
        $uploads[$uploadId] = $upload;
        session()->put('bulk_video_uploads', $uploads);

        $allChunksPresent = true;
        for ($index = 0; $index < $chunkCount; $index++) {
            if (!is_file($directory . DIRECTORY_SEPARATOR . $uploadId . '.chunk.' . $index)) {
                $allChunksPresent = false;
                break;
            }
        }

        if ($allChunksPresent) {
            $assembledPath = $directory . DIRECTORY_SEPARATOR . $uploadId . '.assembling';
            $output = fopen($assembledPath, 'wb');
            if ($output === false) {
                return response()->json(['status' => 500, 'errors' => ['Unable to assemble uploaded video.']], 500);
            }

            $assembledBytes = 0;
            try {
                for ($index = 0; $index < $chunkCount; $index++) {
                    $input = fopen($directory . DIRECTORY_SEPARATOR . $uploadId . '.chunk.' . $index, 'rb');
                    if ($input === false) {
                        throw new Exception('Unable to read an uploaded chunk.');
                    }
                    $assembledBytes += stream_copy_to_stream($input, $output);
                    fclose($input);
                }
            } catch (Exception $exception) {
                fclose($output);
                @unlink($assembledPath);
                return response()->json(['status' => 500, 'errors' => [$exception->getMessage()]], 500);
            }
            fclose($output);

            if ($assembledBytes !== $upload['size']) {
                @unlink($assembledPath);
                return response()->json(['status' => 422, 'errors' => ['Uploaded video size did not match the selected file.']], 422);
            }

            $input = fopen($assembledPath, 'rb');
            $header = $input ? fread($input, 12) : '';
            if ($input) {
                fclose($input);
            }
            if (strlen($header) < 8 || substr($header, 4, 4) !== 'ftyp') {
                @unlink($assembledPath);
                foreach (glob($directory . DIRECTORY_SEPARATOR . $uploadId . '.chunk.*') ?: [] as $path) {
                    @unlink($path);
                }
                unset($uploads[$uploadId]);
                session()->put('bulk_video_uploads', $uploads);
                return response()->json(['status' => 422, 'errors' => ['The selected file is not a valid MP4 video.']], 422);
            }

            $finalPath = $directory . DIRECTORY_SEPARATOR . $uploadId . '.mp4';
            if (!rename($assembledPath, $finalPath)) {
                @unlink($assembledPath);
                return response()->json(['status' => 500, 'errors' => ['Unable to finalize uploaded video.']], 500);
            }
            foreach (glob($directory . DIRECTORY_SEPARATOR . $uploadId . '.chunk.*') ?: [] as $path) {
                @unlink($path);
            }

            $upload['complete'] = true;
            $uploads[$uploadId] = $upload;
            session()->put('bulk_video_uploads', $uploads);
        }

        return response()->json([
            'status' => 200,
            'complete' => $upload['complete'],
            'uploaded_bytes' => $upload['complete'] ? $upload['size'] : null,
        ]);
    }

    /** Save the browser-generated poster frame alongside its staged video. */
    public function bulkUploadThumbnail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|uuid',
            'thumbnail' => 'required|image|mimes:jpeg,jpg,png|max:5120',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 400, 'errors' => $validator->errors()->all()], 422);
        }

        $thumbnail = $request->file('thumbnail');
        $imageInfo = @getimagesize($thumbnail->getRealPath());
        if (!$imageInfo || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return response()->json(['status' => 400, 'errors' => ['The preview must be a valid JPEG or PNG image.']], 422);
        }

        $uploadId = $request->input('upload_id');
        $uploads = session()->get('bulk_video_uploads', []);
        $upload = $uploads[$uploadId] ?? null;
        if (!$upload || !$upload['complete']) {
            return response()->json(['status' => 404, 'errors' => ['Finish the video upload before sending its preview image.']], 404);
        }

        $directory = $this->bulkUploadDirectory($uploadId);
        $posterName = $uploadId . '.poster.jpg';
        $posterPath = $directory . DIRECTORY_SEPARATOR . $posterName;
        if (is_file($posterPath)) {
            @unlink($posterPath);
        }
        $thumbnail->move($directory, $posterName);

        $upload['poster_complete'] = true;
        $uploads[$uploadId] = $upload;
        session()->put('bulk_video_uploads', $uploads);

        return response()->json(['status' => 200]);
    }

    /** Remove a staged upload, including partially received chunks. */
    public function bulkUploadRemove(Request $request)
    {
        $validator = Validator::make($request->all(), ['upload_id' => 'required|uuid']);
        if ($validator->fails()) {
            return response()->json(['status' => 400, 'errors' => $validator->errors()->all()], 422);
        }

        $uploadId = $request->input('upload_id');
        $uploads = session()->get('bulk_video_uploads', []);
        unset($uploads[$uploadId]);
        session()->put('bulk_video_uploads', $uploads);
        $this->deleteBulkUploadDirectory($uploadId);

        return response()->json(['status' => 200]);
    }

    /** Save every completed staged movie with the batch's shared metadata. */
    public function bulkUploadSave(Request $request)
    {
        @set_time_limit(0);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:2|max:255',
            'type_id' => 'required|integer',
            'category_id' => 'required|integer',
            'language_id' => 'required|integer',
            'upload_ids' => 'required|array|min:1',
            'upload_ids.*' => 'required|uuid|distinct',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 400, 'errors' => $validator->errors()->all()], 422);
        }

        $type = Type::where('id', $request->input('type_id'))->where('type', 1)->where('status', 1)->first();
        if (!$type) {
            return response()->json(['status' => 400, 'errors' => ['Bulk upload is available for active Movies content only.']], 422);
        }
        if (!Category::where('id', $request->input('category_id'))->where('status', 1)->exists()) {
            return response()->json(['status' => 400, 'errors' => ['Select an active category.']], 422);
        }
        if (!Language::where('id', $request->input('language_id'))->where('status', 1)->exists()) {
            return response()->json(['status' => 400, 'errors' => ['Select an active language.']], 422);
        }

        $uploadIds = $request->input('upload_ids');
        $canonicalIds = $uploadIds;
        sort($canonicalIds, SORT_STRING);
        $batchKey = hash('sha256', implode('|', $canonicalIds));
        $receipts = session()->get('bulk_video_receipts', []);
        if (isset($receipts[$batchKey]) && $receipts[$batchKey]['expires_at'] > time()) {
            return response()->json($receipts[$batchKey]['response']);
        }

        $uploads = session()->get('bulk_video_uploads', []);
        $batch = [];
        foreach ($uploadIds as $uploadId) {
            $upload = $uploads[$uploadId] ?? null;
            $videoPath = $this->bulkUploadDirectory($uploadId) . DIRECTORY_SEPARATOR . $uploadId . '.mp4';
            $posterPath = $this->bulkUploadDirectory($uploadId) . DIRECTORY_SEPARATOR . $uploadId . '.poster.jpg';
            if (!$upload || !$upload['complete'] || !is_file($videoPath) || filesize($videoPath) !== $upload['size']) {
                return response()->json(['status' => 400, 'errors' => ['Every selected video must finish uploading before the batch can be saved.']], 422);
            }
            // A client preview is optional. HEVC/large videos often cannot be
            // decoded in the browser; ffmpeg generates the thumbnail below.
            $batch[] = ['id' => $uploadId, 'upload' => $upload, 'path' => $videoPath, 'poster_path' => $posterPath];
        }

        $storageType = (int) Storage_Type();
        $diskName = [1 => 'public', 2 => 's3', 3 => 'wasabi'][$storageType] ?? null;
        if (!$diskName) {
            return response()->json(['status' => 500, 'errors' => ['The configured video storage type is not supported.']], 500);
        }

        $savedPaths = [];
        try {
            $preparedBatch = [];
            foreach ($batch as $item) {
                $filename = 'vid_' . date('Y_m_d_') . Str::uuid() . '.mp4';
                $targetPath = $this->folder_content . '/' . $filename;
                $savedPaths[] = $targetPath;
                $stream = fopen($item['path'], 'rb');
                if ($stream === false) {
                    throw new Exception('Unable to read a completed staged video.');
                }
                try {
                    $stored = Storage::disk($diskName)->put($targetPath, $stream);
                } finally {
                    fclose($stream);
                }
                if (!$stored) {
                    throw new Exception('Unable to store video ' . $item['upload']['name'] . '.');
                }
                $item['filename'] = $filename;
                $item['target_path'] = $targetPath;

                $qualityVariants = $this->common->prepareVideoVariants([
                    'video_320' => $filename,
                    'video_480' => '',
                    'video_720' => '',
                    'video_1080' => '',
                ], $storageType);
                $item['filename'] = $qualityVariants['video_320'];
                $item['video_480'] = $qualityVariants['video_480'];
                $item['video_720'] = $qualityVariants['video_720'];
                $item['video_1080'] = $qualityVariants['video_1080'];
                foreach ([$item['filename'], $item['video_480'], $item['video_720'], $item['video_1080']] as $variant) {
                    if ($variant !== '' && $variant !== $filename) {
                        $savedPaths[] = $this->folder_content . '/' . $variant;
                    }
                }

                $posterFilename = 'vid_' . date('Y_m_d_') . Str::uuid() . '.jpg';
                $posterTargetPath = $this->folder_content . '/' . $posterFilename;
                $savedPaths[] = $posterTargetPath;
                if (!is_file($item['poster_path'])) {
                    app(\App\Services\VideoTranscoder::class)->createPoster(
                        $item['path'],
                        $this->bulkUploadDirectory($item['id']) . DIRECTORY_SEPARATOR . $item['id'] . '.poster.jpg'
                    );
                    $item['poster_path'] = $this->bulkUploadDirectory($item['id']) . DIRECTORY_SEPARATOR . $item['id'] . '.poster.jpg';
                }
                $posterStream = fopen($item['poster_path'], 'rb');
                if ($posterStream === false) {
                    throw new Exception('Unable to read a generated video preview.');
                }
                try {
                    $posterStored = Storage::disk($diskName)->put($posterTargetPath, $posterStream);
                } finally {
                    fclose($posterStream);
                }
                if (!$posterStored) {
                    throw new Exception('Unable to store the preview image for ' . $item['upload']['name'] . '.');
                }
                $item['poster_filename'] = $posterFilename;
                $preparedBatch[] = $item;
            }
            $batch = $preparedBatch;

            $videos = DB::transaction(function () use ($batch, $request, $type, $storageType) {
                $created = [];
                foreach ($batch as $item) {
                    $created[] = Video::create([
                        'type_id' => $type->id,
                        'video_type' => $type->type,
                        'channel_id' => 0,
                        'producer_id' => 0,
                        'category_id' => (string) $request->input('category_id'),
                        'language_id' => (string) $request->input('language_id'),
                        'cast_id' => '',
                        'name' => trim($request->input('title')),
                        'storage_type' => $storageType,
                        'thumbnail' => $item['poster_filename'],
                        'landscape' => $item['poster_filename'],
                        'description' => '',
                        'video_storage_type' => $storageType,
                        'video_upload_type' => 'server_video',
                        'video_320' => $item['filename'],
                        'video_480' => $item['video_480'] ?? '',
                        'video_720' => $item['video_720'] ?? '',
                        'video_1080' => $item['video_1080'] ?? '',
                        'video_extension' => 'mp4',
                        'video_duration' => 0,
                        'trailer_storage_type' => $storageType,
                        'trailer_type' => '',
                        'trailer_url' => '',
                        'subtitle_storage_type' => $storageType,
                        'subtitle_type' => 'server_video',
                        'subtitle_lang_1' => '',
                        'subtitle_lang_2' => '',
                        'subtitle_lang_3' => '',
                        'subtitle_1' => '',
                        'subtitle_2' => '',
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
                }
                return $created;
            });
        } catch (Exception $exception) {
            foreach ($savedPaths as $path) {
                Storage::disk($diskName)->delete($path);
            }
            return response()->json(['status' => 400, 'errors' => [$exception->getMessage()]], 400);
        }

        $response = [
            'status' => 200,
            'success' => count($videos) . ' videos added successfully.',
            'count' => count($videos),
            'video_ids' => array_map(function ($video) { return $video->id; }, $videos),
        ];
        foreach ($uploadIds as $uploadId) {
            $this->deleteBulkUploadDirectory($uploadId);
            unset($uploads[$uploadId]);
        }
        session()->put('bulk_video_uploads', $uploads);
        $receipts[$batchKey] = ['expires_at' => time() + 86400, 'response' => $response];
        foreach ($receipts as $key => $receipt) {
            if (($receipt['expires_at'] ?? 0) <= time()) {
                unset($receipts[$key]);
            }
        }
        session()->put('bulk_video_receipts', $receipts);

        return response()->json($response);
    }

    private function bulkUploadDirectory($uploadId)
    {
        return storage_path('app/bulk-video-staging/' . Auth::guard('admin')->id() . '/' . $uploadId);
    }

    private function deleteBulkUploadDirectory($uploadId)
    {
        $directory = $this->bulkUploadDirectory($uploadId);
        if (is_dir($directory)) {
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($directory);
        }
    }

    private function cleanupExpiredBulkUploads()
    {
        $adminDirectory = storage_path('app/bulk-video-staging/' . Auth::guard('admin')->id());
        if (!is_dir($adminDirectory)) {
            return;
        }

        foreach (glob($adminDirectory . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (filemtime($directory) < time() - 86400) {
                foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
                @rmdir($directory);
            }
        }
    }

    public function store(Request $request)
    {
        try {
            $rules = [
                'name' => 'required|min:2',
                'type_id' => 'required',
                'video_type' => 'required',
                'category_id' => 'required',
                'language_id' => 'required',
                'video_upload_type' => 'required',
                'subtitle_type' => 'required',
                'is_premium' => 'required',
                'is_title' => 'required',
                'is_download' => 'required',
                'is_comment' => 'required',
                'is_like' => 'required',
                'is_rent' => 'required',
            ];
            if ($request['video_type'] == 6) {
                $rules['channel_id'] = 'required';
            }
            if ($request['is_rent'] == 1) {
                $rules['price'] = 'required|numeric|min:1';
                $rules['rent_day'] = 'required|numeric|min:1';
            }
            $messages = [];
            if ($request['video_upload_type'] == "server_video") {
                $rules['video_320'] = 'required';
            } elseif ($request['video_upload_type'] == "live_stream_url") {
                $rules['video_url_320'] = 'required';
                $messages['video_url_320.required'] = __('label.the_live_stream_url_is_required');
            } elseif ($request['video_upload_type'] == "vdocipher_id") {
                $rules['video_url_320'] = 'required';
                $messages['video_url_320.required'] = __('label.vdocipher_id_is_required');
            } else {
                $rules['video_url_320'] = 'required';
            }
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json(['status' => 400, 'errors' => $validator->errors()->all()]);
            }

            $storage_type = Storage_Type();

            $insert = new Video();
            $insert['type_id'] = $request['type_id'];
            $insert['video_type'] = $request['video_type'];
            $insert['channel_id'] = $request['channel_id'] ?? 0;
            $insert['producer_id'] = $request['producer_id'] ?? 0;
            $insert['category_id'] = implode(',', $request['category_id']);
            $insert['language_id'] = implode(',', $request['language_id']);
            $insert['cast_id'] = is_array($request['cast_id']) ? implode(',', $request['cast_id']) : "";
            $insert['name'] = $request['name'];
            $insert['storage_type'] = $storage_type;
            $insert['thumbnail'] = "";
            $file = $request->file('thumbnail');
            if (isset($file) && $file != null) {
                $insert['thumbnail'] = $this->common->saveImage($file, $this->folder_content, 'vid_', $storage_type);
            } else if ($request['thumbnail_tmdb']) {
                $insert['thumbnail'] = $this->common->URLSaveInImage($request['thumbnail_tmdb'], $this->folder_content, 'vid_', $storage_type);
            }
            $insert['landscape'] = "";
            $file1 = $request->file('landscape');
            if (isset($file1) && $file1 != null) {
                $insert['landscape'] = $this->common->saveImage($file1, $this->folder_content, 'vid_', $insert['storage_type']);
            } else if ($request['landscape_tmdb']) {
                $insert['landscape'] = $this->common->URLSaveInImage($request['landscape_tmdb'], $this->folder_content, 'vid_', $insert['storage_type']);
            }
            $insert['description'] = $request['description'] ?? "";
            // Video (320, 480, 720, 1080)
            $insert['video_storage_type'] = $storage_type;
            $insert['video_upload_type'] = $request['video_upload_type'];
            if ($insert['video_upload_type'] == "server_video") {

                $video_320 = $request['video_320'] ?? '';
                $video_480 = $request['video_480'] ?? '';
                $video_720 = $request['video_720'] ?? '';
                $video_1080 = $request['video_1080'] ?? '';
                if ($insert['video_storage_type'] == 1) {

                    $insert['video_320'] = $video_320;
                    $insert['video_480'] = $video_480;
                    $insert['video_720'] = $video_720;
                    $insert['video_1080'] = $video_1080;
                } else {

                    $insert['video_320'] = $this->common->saveImage($video_320, $this->folder_content, 'vid_', $storage_type);
                    $insert['video_480'] = $video_480 != null ? $this->common->saveImage($video_480, $this->folder_content, 'vid_', $storage_type) : "";
                    $insert['video_720'] = $video_720 != null ? $this->common->saveImage($video_720, $this->folder_content, 'vid_', $storage_type) : "";
                    $insert['video_1080'] = $video_1080 != null ? $this->common->saveImage($video_1080, $this->folder_content, 'vid_', $storage_type) : "";
                }

                $qualityVariants = $this->common->prepareVideoVariants([
                    'video_320' => $insert['video_320'],
                    'video_480' => $insert['video_480'],
                    'video_720' => $insert['video_720'],
                    'video_1080' => $insert['video_1080'],
                ], (int) $insert['video_storage_type']);
                $insert['video_320'] = $qualityVariants['video_320'];
                $insert['video_480'] = $qualityVariants['video_480'];
                $insert['video_720'] = $qualityVariants['video_720'];
                $insert['video_1080'] = $qualityVariants['video_1080'];

                $array = explode('.', $insert['video_320']);
                $insert['video_extension'] = end($array);
            } else {

                $insert['video_320'] = $request['video_url_320'] ?? '';
                $insert['video_480'] = $request['video_url_480'] ?? '';
                $insert['video_720'] = $request['video_url_720'] ?? '';
                $insert['video_1080'] = $request['video_url_1080'] ?? '';

                $array = explode('.', $request['video_url_320']);
                $array1 = explode('?', end($array));
                if (isset($array1) && $array1 != null) {
                    $insert['video_extension'] = isset($array1) ? reset($array1) : "";
                } else {
                    $insert['video_extension'] = "";
                }
            }
            $insert['video_duration'] = isset($request['video_duration']) ? Time_To_Milliseconds($request['video_duration']) : 0;
            // Trailer
            $insert['trailer_storage_type'] = $storage_type;
            $insert['trailer_type'] = $request['trailer_type'] ?? '';
            if ($insert['trailer_type'] == "server_video") {

                $trailer = $request['trailer'] ?? '';
                if ($insert['trailer_storage_type'] == 1) {
                    $insert['trailer_url'] = $trailer;
                } else {
                    $insert['trailer_url'] = $trailer != null ? $this->common->saveImage($trailer, $this->folder_content, 'vid_', $storage_type) : "";
                }
            } else {
                $insert['trailer_url'] = $request['trailer_url'] ?? '';
            }
            // Subtitle
            $insert['subtitle_storage_type'] = $storage_type;
            $insert['subtitle_type'] = $request['subtitle_type'] ?? '';
            $insert['subtitle_lang_1'] = $request['subtitle_lang_1'] ?? '';
            $insert['subtitle_lang_2'] = $request['subtitle_lang_2'] ?? '';
            $insert['subtitle_lang_3'] = $request['subtitle_lang_3'] ?? '';
            if ($insert['subtitle_type'] == "server_video") {

                $subtitle_1 = $request['subtitle_1'] ?? '';
                $subtitle_2 = $request['subtitle_2'] ?? '';
                $subtitle_3 = $request['subtitle_3'] ?? '';
                if ($insert['subtitle_storage_type'] == 1) {

                    $insert['subtitle_1'] = $subtitle_1;
                    $insert['subtitle_2'] = $subtitle_2;
                    $insert['subtitle_3'] = $subtitle_3;
                } else {

                    $insert['subtitle_1'] = $subtitle_1 != null ? $this->common->saveImage($subtitle_1, $this->folder_content, 'vid_', $storage_type) : "";
                    $insert['subtitle_2'] = $subtitle_2 != null ? $this->common->saveImage($subtitle_2, $this->folder_content, 'vid_', $storage_type) : "";
                    $insert['subtitle_3'] = $subtitle_3 != null ? $this->common->saveImage($subtitle_3, $this->folder_content, 'vid_', $storage_type) : "";
                }
            } else {
                $insert['subtitle_1'] = $request['subtitle_url_1'] ?? '';
                $insert['subtitle_2'] = $request['subtitle_url_2'] ?? '';
                $insert['subtitle_3'] = $request['subtitle_url_3'] ?? '';
            }
            $insert['release_date'] = $request['release_date'] ?? "";
            $insert['is_premium'] = $request['is_premium'];
            $insert['is_title'] = $request['is_title'];
            $insert['is_download'] = $request['video_upload_type'] == "server_video" ? $request['is_download'] : 0;
            $insert['is_comment'] = $request['is_comment'];
            $insert['is_like'] = $request['is_like'];
            $insert['is_rent'] = $request['is_rent'];
            $insert['price'] = $request['price'] ?? 0;
            $insert['rent_day'] = $request['rent_day'] ?? 0;
            $insert['avg_rating'] = 0;
            $insert['total_review'] = 0;
            $insert['total_view'] = 0;
            $insert['total_like'] = 0;
            $insert['status'] = 1;
            if ($insert->save()) {

                // Send Notification
                $sub_video_type = 0;
                if ($insert->video_type == 1) {
                    $check = $this->common->NotificationConfiguration('add_movies');
                } else if ($insert->video_type == 5) {
                    $check = $this->common->NotificationConfiguration('add_upcoming_content');
                    $sub_video_type = 1;
                } else if ($insert->video_type == 6) {
                    $check = $this->common->NotificationConfiguration('add_channel_content');
                    $sub_video_type = 1;
                } else if ($insert->video_type == 7) {
                    $check = $this->common->NotificationConfiguration('add_kids_content');
                    $sub_video_type = 1;
                }
                if (isset($check) && $check['status'] == 1 && $check['send_notification'] == 1) {

                    $imageURL = $this->common->getImage($this->folder_content, $insert->thumbnail, 'normal', $insert->storage_type);
                    $noti_array = array(
                        'id' => $insert->id,
                        'name' => $insert->name,
                        'image' => $imageURL,
                        'type_id' => $insert->type_id,
                        'video_type' => $insert->video_type,
                        'sub_video_type' => $sub_video_type,
                        'description' => String_Cut($insert->description, 90),
                    );
                    $this->common->sendNotification($noti_array);
                }

                return response()->json(['status' => 200, 'success' => __('label.success_add_video')]);
            }
            return response()->json(['status' => 400, 'errors' => __('label.error_add_video')]);
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }
    public function edit($id, $type_id)
    {
        try {
            $params['type_id'] = $type_id;
            $params['type'] = Type::where('id', $type_id)->where('status', 1)->first();
            if (!$params['type']) {
                return view('errors.404');
            }

            $params['data'] = Video::where('id', $id)->first();
            if ($params['data'] != null) {

                $params['data']['thumbnail'] = $this->common->getImage($this->folder_content, $params['data']['thumbnail'], 'portrait', $params['data']['storage_type']);
                $params['data']['landscape'] = $this->common->getImage($this->folder_content, $params['data']['landscape'], 'landscape', $params['data']['storage_type']);

                $params['channel'] = Channel::where('status', 1)->latest()->get();
                $params['category'] = Category::where('status', 1)->orderBy('sort_order', 'asc')->get();
                $params['language'] = Language::where('status', 1)->orderBy('sort_order', 'asc')->get();
                $params['producer'] = Producer::get();
                $params['cast'] = Cast::where('status', 1)->get();
                $params['rent_price_list'] = Rent_Price_List::where('status', 1)->get();

                return view('admin.video.edit', $params);
            }
            return redirect()->back()->with('error', __('label.data_not_found'));
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }
    public function update(Request $request)
    {
        try {
            $rules = [
                'name' => 'required|min:2',
                'category_id' => 'required',
                'language_id' => 'required',
                'video_upload_type' => 'required',
                'subtitle_type' => 'required',
                'is_premium' => 'required',
                'is_title' => 'required',
                'is_download' => 'required',
                'is_comment' => 'required',
                'is_like' => 'required',
                'is_rent' => 'required',
            ];
            if ($request['video_type'] == 6) {
                $rules['channel_id'] = 'required';
            }
            if ($request['is_rent'] == 1) {
                $rules['price'] = 'required|numeric|min:1';
                $rules['rent_day'] = 'required|numeric|min:1';
            }
            $messages = [];
            if ($request['video_upload_type'] != "server_video") {
                $rules['video_url_320'] = 'required';
                if ($request['video_upload_type'] == 'live_stream_url') {
                    $messages['video_url_320.required'] = __('label.the_live_stream_url_is_required');
                } elseif ($request['video_upload_type'] == "vdocipher_id") {
                    $messages['video_url_320.required'] = __('label.vdocipher_id_is_required');
                }
            }
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                return response()->json(['status' => 400, 'errors' => $validator->errors()->all()]);
            }

            $video = Video::where('id', $request['id'])->first();
            if (isset($video['id'])) {

                $video['video_type'] = $request['video_type'];
                $video['channel_id'] = $request['channel_id'] ?? 0;
                $video['producer_id'] = $request['producer_id'] ?? 0;
                $video['category_id'] = implode(',', $request['category_id']);
                $video['language_id'] = implode(',', $request['language_id']);
                $video['cast_id'] = is_array($request['cast_id']) ? implode(',', $request['cast_id']) : "";
                $video['name'] = $request['name'];
                $video['video_upload_type'] = $request['video_upload_type'];
                $video['description'] = $request['description'] ?? "";

                $storage_type = Storage_Type();

                // Image
                $file = $request->file('thumbnail');
                if (isset($file) && $file != null) {
                    $video['storage_type'] = $storage_type;
                    $video['thumbnail'] = $this->common->saveImage($file, $this->folder_content, 'vid_', $storage_type);
                    $this->common->deleteImageToFolder($this->folder_content, basename($request['old_thumbnail']), $request['old_storage_type']);
                } elseif ($request['thumbnail_tmdb']) {
                    $video['storage_type'] = $storage_type;
                    $url = $request['thumbnail_tmdb'];
                    $video['thumbnail'] = $this->common->URLSaveInImage($url, $this->folder_content, 'vid_', $storage_type);
                    $this->common->deleteImageToFolder($this->folder_content, basename($request['old_thumbnail']), $request['old_storage_type']);
                }

                $file1 = $request->file('landscape');
                if (isset($file1) && $file1 != null) {
                    $video['storage_type'] = $storage_type;
                    $video['landscape'] = $this->common->saveImage($file1, $this->folder_content, 'vid_', $storage_type);
                    $this->common->deleteImageToFolder($this->folder_content, basename($request['old_landscape']), $request['old_storage_type']);
                } elseif ($request['landscape_tmdb']) {
                    $video['storage_type'] = $storage_type;
                    $url = $request['landscape_tmdb'];
                    $video['landscape'] = $this->common->URLSaveInImage($url, $this->folder_content, 'vid_', $storage_type);
                    $this->common->deleteImageToFolder($this->folder_content, basename($request['old_landscape']), $request['old_storage_type']);
                }

                // Videos (320, 420, 720, 1080)
                $video_storage_type = $storage_type;
                if ($request['video_upload_type'] == "server_video") {

                    if ($request['video_upload_type'] == $request['old_video_upload_type']) {

                        if ($request['video_320']) {

                            if ($video_storage_type == 1) {
                                $video['video_320'] = $request['video_320'];
                            } else {
                                $video['video_320'] = $this->common->saveImage($request['video_320'], $this->folder_content, 'vid_', $video_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_video_320'], $request['old_video_storage_type']);

                            $array = explode('.', $video['video_320']);
                            $video['video_extension'] = end($array);
                            $video['video_storage_type'] = $video_storage_type;
                        }
                        if ($request['video_480']) {

                            $video['video_storage_type'] = $video_storage_type;
                            if ($video_storage_type == 1) {
                                $video['video_480'] = $request['video_480'];
                            } else {
                                $video['video_480'] = $this->common->saveImage($request['video_480'], $this->folder_content, 'vid_', $video_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_video_480'], $request['old_video_storage_type']);
                        }
                        if ($request['video_720']) {

                            $video['video_storage_type'] = $video_storage_type;
                            if ($video_storage_type == 1) {
                                $video['video_720'] = $request['video_720'];
                            } else {
                                $video['video_720'] = $this->common->saveImage($request['video_720'], $this->folder_content, 'vid_', $video_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_video_720'], $request['old_video_storage_type']);
                        }
                        if ($request['video_1080']) {

                            $video['video_storage_type'] = $video_storage_type;
                            if ($video_storage_type == 1) {
                                $video['video_1080'] = $request['video_1080'];
                            } else {
                                $video['video_1080'] = $this->common->saveImage($request['video_1080'], $this->folder_content, 'vid_', $video_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_video_1080'], $request['old_video_storage_type']);
                        }
                    } else {

                        if ($request['video_320']) {

                            if ($video_storage_type == 1) {
                                $video['video_320'] = $request['video_320'];
                            } else {
                                $video['video_320'] = $this->common->saveImage($request['video_320'], $this->folder_content, 'vid_', $video_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_video_320'], $request['old_video_storage_type']);

                            $array = explode('.', $video['video_320']);
                            $video['video_extension'] = end($array);
                            $video['video_storage_type'] = $video_storage_type;
                        } else {
                            $video['video_320'] = "";
                        }
                        if ($request['video_480']) {

                            $video['video_storage_type'] = $video_storage_type;
                            if ($video_storage_type == 1) {
                                $video['video_480'] = $request['video_480'];
                            } else {
                                $video['video_480'] = $this->common->saveImage($request['video_480'], $this->folder_content, 'vid_', $video_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_video_480'], $request['old_video_storage_type']);
                        } else {
                            $video['video_480'] = "";
                        }
                        if ($request['video_720']) {

                            $video['video_storage_type'] = $video_storage_type;
                            if ($video_storage_type == 1) {
                                $video['video_720'] = $request['video_720'];
                            } else {
                                $video['video_720'] = $this->common->saveImage($request['video_720'], $this->folder_content, 'vid_', $video_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_video_720'], $request['old_video_storage_type']);
                        } else {
                            $video['video_720'] = "";
                        }
                        if ($request['video_1080']) {

                            $video['video_storage_type'] = $video_storage_type;
                            if ($video_storage_type == 1) {
                                $video['video_1080'] = $request['video_1080'];
                            } else {
                                $video['video_1080'] = $this->common->saveImage($request['video_1080'], $this->folder_content, 'vid_', $video_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_video_1080'], $request['old_video_storage_type']);
                        } else {
                            $video['video_1080'] = "";
                        }
                    }
                } else {

                    $this->common->deleteImageToFolder($this->folder_content, $request['old_video_320'], $request['old_video_storage_type']);
                    $this->common->deleteImageToFolder($this->folder_content, $request['old_video_480'], $request['old_video_storage_type']);
                    $this->common->deleteImageToFolder($this->folder_content, $request['old_video_720'], $request['old_video_storage_type']);
                    $this->common->deleteImageToFolder($this->folder_content, $request['old_video_1080'], $request['old_video_storage_type']);

                    $video['video_storage_type'] = $video_storage_type;
                    if ($request['video_url_320']) {

                        $array = explode('.', $request['video_url_320']);
                        $array1 = explode('?', end($array));
                        if (isset($array1) && $array1 != null) {
                            $video['video_extension'] = isset($array1) ? reset($array1) : "";
                        } else {
                            $video['video_extension'] = "";
                        }

                        $video['video_320'] = $request['video_url_320'];
                    }
                    $video['video_480'] = $request['video_url_480'] ?? '';
                    $video['video_720'] = $request['video_url_720'] ?? '';
                    $video['video_1080'] = $request['video_url_1080'] ?? '';
                }
                $qualityVariants = $this->common->prepareVideoVariants([
                    'video_320' => $video['video_320'],
                    'video_480' => $video['video_480'],
                    'video_720' => $video['video_720'],
                    'video_1080' => $video['video_1080'],
                ], (int) ($video['video_storage_type'] ?? $storage_type));
                $video['video_320'] = $qualityVariants['video_320'];
                $video['video_480'] = $qualityVariants['video_480'];
                $video['video_720'] = $qualityVariants['video_720'];
                $video['video_1080'] = $qualityVariants['video_1080'];
                $video['video_duration'] = isset($request['video_duration']) ? Time_To_Milliseconds($request['video_duration']) : 0;

                // Subtitle
                $subtitle_storage_type = $storage_type;
                $video['subtitle_type'] = $request['subtitle_type'] ?? '';
                $video['subtitle_lang_1'] =  $request['subtitle_lang_1'] ?? '';
                $video['subtitle_lang_2'] =  $request['subtitle_lang_2'] ?? '';
                $video['subtitle_lang_3'] =  $request['subtitle_lang_3'] ?? '';
                if ($request['subtitle_type'] == "server_video") {

                    if ($request['subtitle_type'] == $request['old_subtitle_type']) {

                        if ($request['subtitle_1']) {

                            $video['subtitle_storage_type'] = $subtitle_storage_type;
                            if ($subtitle_storage_type == 1) {
                                $video['subtitle_1'] = $request['subtitle_1'];
                            } else {
                                $video['subtitle_1'] = $this->common->saveImage($request['subtitle_1'], $this->folder_content, 'vid_', $subtitle_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_subtitle_1'], $request['old_subtitle_storage_type']);
                        }
                        if ($request['subtitle_2']) {

                            $video['subtitle_storage_type'] = $subtitle_storage_type;
                            if ($subtitle_storage_type == 1) {
                                $video['subtitle_2'] = $request['subtitle_2'];
                            } else {
                                $video['subtitle_2'] = $this->common->saveImage($request['subtitle_2'], $this->folder_content, 'vid_', $subtitle_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_subtitle_2'], $request['old_subtitle_storage_type']);
                        }
                        if ($request['subtitle_3']) {

                            $video['subtitle_storage_type'] = $subtitle_storage_type;
                            if ($subtitle_storage_type == 1) {
                                $video['subtitle_3'] = $request['subtitle_3'];
                            } else {
                                $video['subtitle_3'] = $this->common->saveImage($request['subtitle_3'], $this->folder_content, 'vid_', $subtitle_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_subtitle_3'], $request['old_subtitle_storage_type']);
                        }
                    } else {
                        if ($request['subtitle_1']) {

                            $video['subtitle_storage_type'] = $subtitle_storage_type;
                            if ($subtitle_storage_type == 1) {
                                $video['subtitle_1'] = $request['subtitle_1'];
                            } else {
                                $video['subtitle_1'] = $this->common->saveImage($request['subtitle_1'], $this->folder_content, 'vid_', $subtitle_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_subtitle_1'], $request['old_subtitle_storage_type']);
                        } else {
                            $video['subtitle_1'] = "";
                        }
                        if ($request['subtitle_2']) {
                            $video['subtitle_storage_type'] = $subtitle_storage_type;
                            if ($subtitle_storage_type == 1) {
                                $video['subtitle_2'] = $request['subtitle_2'];
                            } else {
                                $video['subtitle_2'] = $this->common->saveImage($request['subtitle_2'], $this->folder_content, 'vid_', $subtitle_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_subtitle_2'], $request['old_subtitle_storage_type']);
                        } else {
                            $video['subtitle_2'] = "";
                        }
                        if ($request['subtitle_3']) {
                            $video['subtitle_storage_type'] = $subtitle_storage_type;
                            if ($subtitle_storage_type == 1) {
                                $video['subtitle_3'] = $request['subtitle_3'];
                            } else {
                                $video['subtitle_3'] = $this->common->saveImage($request['subtitle_3'], $this->folder_content, 'vid_', $subtitle_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_subtitle_3'], $request['old_subtitle_storage_type']);
                        } else {
                            $video['subtitle_3'] = "";
                        }
                    }
                } else {

                    $this->common->deleteImageToFolder($this->folder_content, $request['old_subtitle_1'], $request['old_subtitle_storage_type']);
                    $this->common->deleteImageToFolder($this->folder_content, $request['old_subtitle_2'], $request['old_subtitle_storage_type']);
                    $this->common->deleteImageToFolder($this->folder_content, $request['old_subtitle_3'], $request['old_subtitle_storage_type']);

                    $video['subtitle_storage_type'] = $subtitle_storage_type;
                    $video['subtitle_1'] = $request['subtitle_url_1'] ?? '';
                    $video['subtitle_2'] = $request['subtitle_url_2'] ?? '';
                    $video['subtitle_3'] = $request['subtitle_url_3'] ?? '';
                }

                // Trailer
                $trailer_storage_type = $storage_type;
                $video['trailer_type'] = $request['trailer_type'] ?? '';
                if ($request['trailer_type'] == "server_video") {

                    if ($request['trailer_type'] == $request['old_trailer_type']) {

                        if ($request['trailer']) {

                            $video['trailer_storage_type'] = $trailer_storage_type;
                            if ($trailer_storage_type == 1) {
                                $video['trailer_url'] = $request['trailer'];
                            } else {
                                $video['trailer_url'] = $this->common->saveImage($request['trailer'], $this->folder_content, 'vid_', $trailer_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_trailer'], $request['old_trailer_storage_type']);
                        }
                    } else {
                        if ($request['trailer']) {

                            $video['trailer_storage_type'] = $trailer_storage_type;
                            if ($trailer_storage_type == 1) {
                                $video['trailer_url'] = $request['trailer'];
                            } else {
                                $video['trailer_url'] = $this->common->saveImage($request['trailer'], $this->folder_content, 'vid_', $trailer_storage_type);
                            }
                            $this->common->deleteImageToFolder($this->folder_content, $request['old_trailer'], $request['old_trailer_storage_type']);
                        } else {
                            $video['trailer_url'] = "";
                        }
                    }
                } else {

                    $this->common->deleteImageToFolder($this->folder_content, basename($request['old_trailer']), $request['old_trailer_storage_type']);

                    $video['trailer_storage_type'] = $trailer_storage_type;
                    $video['trailer_url'] = $request['trailer_url'] ?? '';
                }

                $video['release_date'] = $request['release_date'] ?? "";
                $video['is_premium'] = $request['is_premium'];
                $video['is_title'] = $request['is_title'];
                $video['is_download'] = $request['video_upload_type'] == "server_video" ? $request['is_download'] : 0;
                $video['is_comment'] = $request['is_comment'];
                $video['is_like'] = $request['is_like'];
                $video['is_rent'] = $request['is_rent'];
                $video['price'] = $video['is_rent'] != 0 ? $request['price'] : 0;
                $video['rent_day'] = $video['is_rent'] != 0 ? $request['rent_day'] : 0;

                if ($video->save()) {
                    return response()->json(['status' => 200, 'success' => __('label.success_edit_video')]);
                }
            }
            return response()->json(['status' => 400, 'errors' => __('label.error_edit_video')]);
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }
    public function show($id, $type)
    {
        try {

            $video = Video::where('id', $id)->first();
            if ($video) {

                $this->common->deleteImageToFolder($this->folder_content, $video->thumbnail, $video['storage_type']);
                $this->common->deleteImageToFolder($this->folder_content, $video->landscape, $video['storage_type']);

                $this->common->deleteImageToFolder($this->folder_content, $video->video_320, $video['video_storage_type']);
                $this->common->deleteImageToFolder($this->folder_content, $video->video_480, $video['video_storage_type']);
                $this->common->deleteImageToFolder($this->folder_content, $video->video_720, $video['video_storage_type']);
                $this->common->deleteImageToFolder($this->folder_content, $video->video_1080, $video['video_storage_type']);

                $this->common->deleteImageToFolder($this->folder_content, $video->trailer_url, $video['trailer_storage_type']);

                $this->common->deleteImageToFolder($this->folder_content, $video->subtitle_1, $video['subtitle_storage_type']);
                $this->common->deleteImageToFolder($this->folder_content, $video->subtitle_2, $video['subtitle_storage_type']);
                $this->common->deleteImageToFolder($this->folder_content, $video->subtitle_3, $video['subtitle_storage_type']);

                $video->delete();

                // Releted Data Delete
                Bookmark::where('video_type', $video['video_type'])->where('video_id', $id)->delete();
                Comment::where('video_type', $video['video_type'])->where('video_id', $id)->delete();
                Like::where('video_type', $video['video_type'])->where('video_id', $id)->delete();
                Review::where('video_type', $video['video_type'])->where('video_id', $id)->delete();
                Video_Watch::where('video_type', $video['video_type'])->where('video_id', $id)->where('episode_id', 0)->delete();
                View::where('video_type', $video['video_type'])->where('video_id', $id)->where('episode_id', 0)->delete();

                return redirect()->route('admin.video.index', ['type_id' => $type])->with('success', __('label.content_delete'));
            }
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }
    public function saveChunk()
    {

        @set_time_limit(5 * 60);

        $targetDir = storage_path('/app/public/content');
        $cleanupTargetDir = true; // Remove old files
        $maxFileAge = 5 * 3600; // Temp file age in seconds

        // Create target dir
        if (!file_exists($targetDir)) {
            @mkdir($targetDir);
        }

        // Get a file name
        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }
        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        // Chunk information
        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

        // Remove old temp files
        if ($cleanupTargetDir && is_dir($targetDir) && $dir = opendir($targetDir)) {
            while (($file = readdir($dir)) !== false) {
                $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

                // Remove temp file if it is older than the max age and not the current file
                if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge)) {
                    @unlink($tmpfilePath);
                }
            }
            closedir($dir);
        } else {
            die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
        }

        // Open temp file
        if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
        }

        if (!empty($_FILES)) {
            if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
            }

            // Read binary input stream and append it to temp file
            if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
            }
        }

        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }

        @fclose($out);
        @fclose($in);

        // Check if file has been uploaded
        if (!$chunks || $chunk == $chunks - 1) {
            // Strip the temp .part suffix off
            rename("{$filePath}.part", $filePath);

            // Generate a new filename based on the current date and time
            $extension = pathinfo($fileName, PATHINFO_EXTENSION); // Get the file extension from the original filename
            $newFileName = 'vid_' . date('Y_m_d_') . uniqid() . '.' . $extension; // Use the extracted extension
            $newFilePath = $targetDir . DIRECTORY_SEPARATOR . $newFileName;

            // Rename the uploaded file to the new filename
            rename($filePath, $newFilePath);

            // Send the new file name back to the client
            die(json_encode(array('jsonrpc' => '2.0', 'result' => $newFileName, 'id' => 'id')));
        }

        // Return Success JSON-RPC response
        die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
    }
    public function changeStatus(Request $request)
    {
        try {

            $data = Video::where('id', $request->id)->first();
            if ($data) {

                $data['status'] = $data['status'] === 1 ? 0 : 1;
                $data->save();
                return response()->json(['status' => 200, 'success' => __('label.status_changed'), 'status_code' => $data['status']]);
            }
            return response()->json(['status' => 400, 'errors' => __('label.data_not_found')]);
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }
    public function videoReleases(Request $request)
    {
        try {
            $rules = [
                'id' => 'required',
                'type_id' => 'required',
            ];
            $check_type = Type::where('id', $request['type_id'])->first();
            if (isset($check_type['type']) && $check_type['type'] == 6) {
                $rules['channel_id'] = 'required';
            }
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json(['status' => 400, 'errors' => $validator->errors()->all()]);
            }

            $video = Video::where('id', $request['id'])->first();
            if (isset($video['id'])) {

                $video['type_id'] = $request['type_id'];
                $video['video_type'] = $check_type['type'];
                $video['channel_id'] = $check_type?->type == 6 ? $request->channel_id : 0;

                if ($video->save()) {
                    return response()->json(['status' => 200, 'success' => __('label.video_released')]);
                }
            }
            return response()->json(['status' => 400, 'errors' => __('label.error_edit_video')]);
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }

    // TMDb
    public function SerachName($txtVal)
    {
        try {
            $tmdbTitle = $txtVal;
            $tmdb_api_key = TMDB_API_Key();

            if (strlen($tmdbTitle) >= 3 && $tmdb_api_key != "" && isset($tmdb_api_key) && $tmdb_api_key != null) {

                $url = 'https://api.themoviedb.org/3/search/movie?api_key=' . $tmdb_api_key . '&language=en-US&page=1&include_adult=false&query=' . $tmdbTitle;
                $response = Http::get($url);
                $Status = $response->getStatusCode();
                $Data = $response->json();

                if ($Status == 200) {
                    return response()->json(['status' => 200, 'success' => __('label.data_get_successfully'), 'data' => $Data]);
                }
            } else {
                return response()->json(['status' => 400, 'success' => __('label.enter_tmdb_key')]);
            }
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }
    public function GetData($tmdbID)
    {
        try {

            $tmdb_api_key = TMDB_API_Key();
            if ($tmdb_api_key != "" && isset($tmdb_api_key) && $tmdb_api_key != null) {

                $url = 'https://api.themoviedb.org/3/movie/' . $tmdbID . '?api_key=' . $tmdb_api_key . '&append_to_response=credits&language=en-US';
                $response = Http::get($url);
                $Status = $response->getStatusCode();
                $movies = $response->json();
                $storage_type = Storage_Type();

                // Category
                $C_Id = [];
                $C_Insert_Data = [];
                if (isset($movies['genres']) && $movies['genres'] != null && count($movies['genres']) > 0) {

                    for ($i = 0; $i < count($movies['genres']); $i++) {

                        $Category = Category::where(DB::raw('lower(name)'), strtolower($movies['genres'][$i]['name']))->first();
                        if (!empty($Category)) {

                            $C_Id[] = $Category['id'];
                        } else {

                            $insert = new Category();
                            $insert['name'] = $movies['genres'][$i]['name'];
                            $insert['storage_type'] = $storage_type;
                            $insert['image'] = "";
                            $insert['sort_order'] = 0;
                            $insert['status'] = 1;
                            $insert->save();

                            $C_Id[] = $insert['id'];
                            $C_Insert_Data[] = $insert;
                        }
                    }
                }
                $params['C_Id'] = $C_Id;
                $params['C_Insert_Data'] = $C_Insert_Data;

                // Language
                $L_Id = [];
                $L_Insert_Data = [];
                if (isset($movies['spoken_languages']) && $movies['spoken_languages'] != null && count($movies['spoken_languages']) > 0) {

                    for ($i = 0; $i < count($movies['spoken_languages']); $i++) {

                        $Language = Language::where(DB::raw('lower(name)'), strtolower($movies['spoken_languages'][$i]['english_name']))->first();
                        if (!empty($Language)) {

                            $L_Id[] = $Language['id'];
                        } else {

                            $insert = new Language();
                            $insert['name'] = $movies['spoken_languages'][$i]['english_name'];
                            $insert['storage_type'] = $storage_type;
                            $insert['image'] = "";
                            $insert['sort_order'] = 0;
                            $insert['status'] = 1;
                            $insert->save();

                            $L_Id[] = $insert['id'];
                            $L_Insert_Data[] = $insert;
                        }
                    }
                }
                $params['L_Id'] = $L_Id;
                $params['L_Insert_Data'] = $L_Insert_Data;

                // Cast
                $Cast_Id = [];
                $Cast_Insert_Data = [];
                if (isset($movies['credits']['cast']) && $movies['credits']['cast'] != null && count($movies['credits']['cast']) > 0) {

                    for ($i = 0; $i < count($movies['credits']['cast']); $i++) {

                        $CastData = Cast::where(DB::raw('lower(name)'), strtolower($movies['credits']['cast'][$i]['name']))->first();
                        if (!empty($CastData)) {

                            $Cast_Id[] = $CastData['id'];
                        } else {

                            $insert = new Cast();
                            $insert['name'] = $movies['credits']['cast'][$i]['name'];
                            $insert['storage_type'] = $storage_type;
                            $castImage = "";
                            if ($movies['credits']['cast'][$i]['profile_path'] != null) {
                                $img_url = 'https://image.tmdb.org/t/p/original' . $movies['credits']['cast'][$i]['profile_path'];
                                $castImage = $this->common->URLSaveInImage($img_url, $this->folder_cast, 'cast_', $storage_type);
                            }
                            $insert['image'] = $castImage;
                            $insert['type'] = "Actor";
                            $insert['personal_info'] = $movies['credits']['cast'][$i]['character'];
                            $insert['status'] = 1;
                            $insert->save();

                            $Cast_Id[] = $insert['id'];
                            $Cast_Insert_Data[] = $insert;
                        }
                        if ($i == 9) {
                            break;
                        }
                    }
                }
                $params['Cast_Id'] = $Cast_Id;
                $params['Cast_Insert_Data'] = $Cast_Insert_Data;

                // Poster
                $Thumbnail = "";
                if (isset($movies['poster_path']) && $movies['poster_path'] != null) {
                    $img_url = 'https://image.tmdb.org/t/p/original' . $movies['poster_path'];
                    $Thumbnail = $img_url;
                }
                $params['Thumbnail'] = $Thumbnail;

                // Title
                $Title = "";
                if (isset($movies['title']) && $movies['title'] != null) {
                    $Title = $movies['title'];
                }
                $params['Title'] = $Title;

                // Description
                $Description = "";
                if (isset($movies['overview'])) {
                    $Description = $movies['overview'];
                }
                $params['Description'] = $Description;

                // Release Date
                $Release_Date = date('Y-m-d');
                if (isset($movies['release_date']) && $movies['release_date'] != null) {
                    $Release_Date = $movies['release_date'];
                }
                $params['Release_Date'] = $Release_Date;

                return response()->json(['status' => 200, 'data' => $params]);
            } else {
                return response()->json(['status' => 400, 'success' => __('label.enter_tmdb_key')]);
            }
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }
}
