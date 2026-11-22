(function ($) {
    'use strict';

    const $section = $('#bulk_video_mode');
    if (!$section.length) return;

    const config = {
        initUrl: $section.data('init-url'),
        chunkUrl: $section.data('chunk-url'),
        thumbnailUrl: $section.data('thumbnail-url'),
        removeUrl: $section.data('remove-url'),
        saveUrl: $section.data('save-url'),
        typeId: $section.data('type-id'),
        csrf: $section.data('csrf'),
        demoMode: $section.data('demo-mode'),
        indexUrl: $section.data('index-url')
    };
    const chunkSize = 1024 * 1024;
    let queue = [];
    let uploading = false;
    let saving = false;

    function request(url, data, onProgress, timeout) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.timeout = typeof timeout === 'number' ? timeout : 120000;
            xhr.setRequestHeader('X-CSRF-TOKEN', config.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            if (onProgress) xhr.upload.onprogress = onProgress;
            xhr.onload = function () {
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (error) {
                    reject(new Error('The server returned an unreadable response. Check your connection and sign in again if needed.'));
                    return;
                }
                if (xhr.status >= 200 && xhr.status < 300 && Number(response.status) === 200) {
                    resolve(response);
                    return;
                }
                const errors = response.errors || response.message || 'Upload request failed.';
                reject(new Error(Array.isArray(errors) ? errors.join(' ') : errors));
            };
            xhr.onerror = function () { reject(new Error('Network error. Check your connection and retry this file.')); };
            xhr.onabort = function () { reject(new Error('Upload was cancelled.')); };
            xhr.ontimeout = function () { reject(new Error('The upload request timed out. Retry this file; completed chunks are checked safely.')); };
            xhr.send(data);
        });
    }

    async function requestChunk(url, data, onProgress) {
        let lastError;
        for (let attempt = 0; attempt < 3; attempt++) {
            try {
                return await request(url, data, onProgress);
            } catch (error) {
                lastError = error;
                if (attempt < 2) {
                    await new Promise(function (resolve) { window.setTimeout(resolve, 500 * (attempt + 1)); });
                }
            }
        }
        throw lastError;
    }

    function formatSize(bytes) {
        if (!bytes) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        return (bytes / Math.pow(1024, index)).toFixed(index ? 1 : 0) + ' ' + units[index];
    }

    function createPoster(file) {
        return new Promise(function (resolve, reject) {
            const video = document.createElement('video');
            const objectUrl = URL.createObjectURL(file);
            let timeout;
            let settled = false;

            function finish(error, blob) {
                if (settled) return;
                settled = true;
                window.clearTimeout(timeout);
                video.pause();
                video.removeAttribute('src');
                video.load();
                URL.revokeObjectURL(objectUrl);
                if (error) reject(error);
                else resolve(blob);
            }

            function captureFrame() {
                if (settled) return;
                if (!video.videoWidth || !video.videoHeight) {
                    finish(new Error('This video could not provide a preview frame. Try an MP4 encoded with H.264 video.'));
                    return;
                }

                const maxWidth = 640;
                const scale = Math.min(1, maxWidth / video.videoWidth);
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
                canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
                const context = canvas.getContext('2d');
                if (!context) {
                    finish(new Error('Your browser could not create the video preview image.'));
                    return;
                }
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        finish(new Error('Could not create a preview image from this video.'));
                        return;
                    }
                    finish(null, blob);
                }, 'image/jpeg', 0.86);
            }

            video.muted = true;
            video.playsInline = true;
            video.preload = 'metadata';
            video.onloadedmetadata = function () {
                const duration = Number.isFinite(video.duration) ? video.duration : 0;
                const captureAt = duration > 0.2 ? Math.min(4, duration / 2) : 0;
                if (captureAt > 0) {
                    video.onseeked = captureFrame;
                    try {
                        video.currentTime = captureAt;
                    } catch (error) {
                        finish(new Error('Could not seek to a preview frame in this video.'));
                    }
                } else {
                    video.onloadeddata = captureFrame;
                    if (video.readyState >= 2) captureFrame();
                }
            };
            video.onerror = function () {
                finish(new Error('This browser cannot decode the video to create its preview. Try an H.264 MP4.'));
            };
            timeout = window.setTimeout(function () {
                finish(new Error('Preview generation timed out. Try an H.264 MP4 video.'));
            }, 30000);
            video.src = objectUrl;
            video.load();
        });
    }

    function renderQueue() {
        const $list = $('#bulk_video_file_list').empty();
        const total = queue.length;
        const completed = queue.filter(function (item) { return item.status === 'uploaded'; }).length;
        const failed = queue.filter(function (item) { return item.status === 'failed'; }).length;
        const saved = queue.filter(function (item) { return item.saved; }).length;
        $('#bulk_video_empty').toggle(total === 0);
        $('#bulk_video_summary').text(total
            ? (saving ? saved + ' of ' + total + ' saved · processing one video at a time' : completed + ' of ' + total + ' uploaded') + (failed ? ' · ' + failed + ' failed' : '')
            : 'No files selected');

        queue.forEach(function (item) {
            const $row = $('<div class="border rounded p-2 mb-2"></div>');
            const $heading = $('<div class="d-flex flex-wrap justify-content-between align-items-center"></div>');
            const $description = $('<div class="mr-2"></div>');
            if (item.posterUrl) {
                $description.append($('<img class="rounded mr-2" alt="Video preview" style="width:72px;height:48px;object-fit:cover;vertical-align:middle;">').attr('src', item.posterUrl));
            }
            $description.append($('<strong></strong>').text(item.file.name));
            $description.append($('<small class="text-muted d-block"></small>').text(formatSize(item.file.size) + ' · ' + item.label));
            $heading.append($description);

            const $actions = $('<div class="mt-1 mt-md-0"></div>');
            if (item.status === 'failed') {
                $('<button type="button" class="btn btn-sm btn-outline-primary mr-1">Retry</button>')
                    .on('click', function () {
                        item.status = 'queued';
                        item.label = 'Queued';
                        item.progress = 0;
                        item.error = '';
                        renderQueue();
                        processQueue();
                    })
                    .appendTo($actions);
            }
            if (item.status !== 'uploading' && !saving) {
                $('<button type="button" class="btn btn-sm btn-outline-danger">Remove</button>')
                    .on('click', function () { removeItem(item); })
                    .appendTo($actions);
            }
            $heading.append($actions);
            $row.append($heading);

            const $progress = $('<div class="progress mt-2" style="height: 8px;"></div>');
            const percent = Math.max(0, Math.min(100, item.progress));
            const $bar = $('<div class="progress-bar" role="progressbar"></div>')
                .css('width', percent + '%')
                .attr('aria-valuenow', percent)
                .attr('aria-valuemin', '0')
                .attr('aria-valuemax', '100');
            if (item.status === 'failed') $bar.addClass('bg-danger');
            if (item.status === 'uploaded') $bar.addClass('bg-success');
            $row.append($progress.append($bar));
            if (item.error) $row.append($('<small class="text-danger d-block mt-1"></small>').text(item.error));
            $list.append($row);
        });

        const hasMetadata = $('#bulk_video_title').val().trim().length >= 2
            && $('#bulk_video_category').val()
            && $('#bulk_video_language').val();
        $('#save_bulk_videos').prop('disabled', saving || uploading || !total || completed !== total || !hasMetadata);
        $('#bulk_video_files').prop('disabled', saving);
    }

    async function uploadItem(item) {
        item.status = 'uploading';
        item.label = 'Preparing upload';
        item.progress = 0;
        item.error = '';
        renderQueue();

        // Browser decoders reject some valid MP4 codecs (notably HEVC), and
        // seeking through a large file can take a long time. Create a preview
        // in parallel, but never make the actual upload depend on the browser
        // being able to decode it. The server can create a JPEG with ffmpeg.
        if (!item.posterPromise) {
            item.posterPromise = createPoster(item.file).then(function (poster) {
                item.poster = poster;
                item.posterUrl = URL.createObjectURL(poster);
                renderQueue();
                return poster;
            }).catch(function () { return null; });
        }

        if (item.uploadId) {
            const cleanup = new FormData();
            cleanup.append('upload_id', item.uploadId);
            try { await request(config.removeUrl, cleanup); } catch (error) { /* a stale upload is cleaned up server-side */ }
            item.uploadId = null;
        }

        const init = new FormData();
        init.append('upload_name', item.file.name);
        init.append('file_size', item.file.size);
        const initialized = await request(config.initUrl, init);
        item.uploadId = initialized.upload_id;

        const actualChunkSize = initialized.chunk_size || chunkSize;
        const count = Math.ceil(item.file.size / actualChunkSize);
        item.label = 'Uploading';
        for (let index = 0; index < count; index++) {
            const start = index * actualChunkSize;
            const end = Math.min(start + actualChunkSize, item.file.size);
            const body = new FormData();
            body.append('upload_id', item.uploadId);
            body.append('chunk_index', index);
            body.append('chunk_count', count);
            body.append('chunk', item.file.slice(start, end), item.file.name);
            await requestChunk(config.chunkUrl, body, function (event) {
                if (event.lengthComputable) {
                    item.progress = Math.floor(((start + event.loaded) / item.file.size) * 100);
                    renderQueue();
                }
            });
            item.progress = Math.floor((end / item.file.size) * 100);
            renderQueue();
        }

        item.label = 'Finishing upload';
        renderQueue();
        const poster = await Promise.race([
            item.posterPromise,
            new Promise(function (resolve) { window.setTimeout(function () { resolve(null); }, 750); })
        ]);
        if (poster) {
            const posterData = new FormData();
            posterData.append('upload_id', item.uploadId);
            posterData.append('thumbnail', poster, item.file.name.replace(/\.[^/.]+$/, '') + '.jpg');
            await request(config.thumbnailUrl, posterData);
        }

        item.status = 'uploaded';
        item.label = poster ? 'Ready to save' : 'Uploaded · server will create preview';
        item.progress = 100;
        renderQueue();
    }

    async function processQueue() {
        if (uploading || saving) return;
        uploading = true;
        renderQueue();
        while (true) {
            const item = queue.find(function (candidate) { return candidate.status === 'queued'; });
            if (!item) break;
            try {
                await uploadItem(item);
            } catch (error) {
                item.status = 'failed';
                item.label = 'Upload failed';
                item.error = error.message || 'Upload failed.';
                renderQueue();
            }
        }
        uploading = false;
        renderQueue();
    }

    async function removeItem(item) {
        if (item.uploadId) {
            const body = new FormData();
            body.append('upload_id', item.uploadId);
            try { await request(config.removeUrl, body); } catch (error) { /* expiry cleanup removes abandoned stages */ }
        }
        if (item.posterUrl) URL.revokeObjectURL(item.posterUrl);
        queue = queue.filter(function (candidate) { return candidate.key !== item.key; });
        renderQueue();
    }

    async function saveBatch() {
        if (saving || !queue.length) return;
        if (String(config.demoMode) !== '1') {
            showError();
            return;
        }
        const title = $('#bulk_video_title').val().trim();
        const categoryId = $('#bulk_video_category').val();
        const languageId = $('#bulk_video_language').val();
        if (title.length < 2 || !categoryId || !languageId) {
            toastr.error('Enter a title and select a category and language for this batch.');
            return;
        }
        if (!queue.every(function (item) { return item.status === 'uploaded' && item.uploadId; })) {
            toastr.error('Retry or remove each failed upload before saving the batch.');
            return;
        }

        saving = true;
        renderQueue();
        $('#dvloader').show();

        try {
            // Finalize each video in its own request. A large batch no longer
            // depends on one HTTP request surviving every transcode. The
            // server's per-upload receipt makes retrying safe if the response
            // is lost after a video has already been committed.
            for (let index = 0; index < queue.length; index++) {
                const item = queue[index];
                if (item.saved) continue;
                item.label = 'Processing video ' + (index + 1) + ' of ' + queue.length;
                item.error = '';
                renderQueue();

                const body = new FormData();
                body.append('_token', config.csrf);
                body.append('title', title);
                body.append('type_id', config.typeId);
                body.append('category_id', categoryId);
                body.append('language_id', languageId);
                body.append('upload_ids[]', item.uploadId);
                await request(config.saveUrl, body, null, 300000);
                item.saved = true;
                item.label = 'Saved';
                renderQueue();
            }
            $('#dvloader').hide();
            toastr.success(queue.length + ' videos saved successfully.');
            window.setTimeout(function () { window.location.replace(config.indexUrl); }, 500);
        } catch (error) {
            $('#dvloader').hide();
            const saved = queue.filter(function (item) { return item.saved; }).length;
            toastr.error((error.message || 'Unable to save the next video.') + ' ' + saved + ' of ' + queue.length + ' are saved. Click Save batch to safely continue.');
            saving = false;
            renderQueue();
        }
    }

    $(function () {
        // Select2 is loaded from a third-party CDN on this page. The upload
        // queue must still initialize when that CDN is unavailable.
        if ($.fn.select2) {
            $('#bulk_video_category, #bulk_video_language').select2({ width: '100%' });
        }
        $('#show_bulk_video_mode').on('click', function () {
            $section.show();
            $('#save_video').hide();
        });
        $('#show_single_video_mode').on('click', function () {
            $section.hide();
            $('#save_video').show();
        });
        $('#bulk_video_files').on('change', function (event) {
            Array.from(event.target.files || []).forEach(function (file) {
                if (!file.name.toLowerCase().endsWith('.mp4')) {
                    toastr.error(file.name + ' is not an MP4 file.');
                    return;
                }
                queue.push({
                    key: Date.now().toString(36) + Math.random().toString(36).slice(2),
                    file: file,
                    uploadId: null,
                    status: 'queued',
                    label: 'Queued',
                    progress: 0,
                    error: ''
                });
            });
            event.target.value = '';
            renderQueue();
            processQueue();
        });
        $('#bulk_video_title, #bulk_video_category, #bulk_video_language').on('input change', renderQueue);
        $('#save_bulk_videos').on('click', saveBatch);
        renderQueue();
    });
})(jQuery);
