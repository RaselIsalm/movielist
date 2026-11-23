(function ($) {
    'use strict';

    const $section = $('#bulk_video_mode');
    if (!$section.length) return;

    const config = {
        initUrl: $section.data('init-url'),
        partUrl: $section.data('part-url'),
        chunkUrl: $section.data('chunk-url'),
        thumbnailUrl: $section.data('thumbnail-url'),
        removeUrl: $section.data('remove-url'),
        saveUrl: $section.data('save-url'),
        statusUrl: $section.data('status-url'),
        typeId: $section.data('type-id'),
        csrf: $section.data('csrf'),
        demoMode: $section.data('demo-mode'),
        indexUrl: $section.data('index-url')
    };
    const chunkSize = 1024 * 1024;
    const uploadConcurrency = 3;
    let queue = [];
    let uploading = false;
    let saving = false;
    let processing = false;
    let statusTimer = null;

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

    function uploadR2Part(url, blob, onProgress) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', url, true);
            xhr.timeout = 120000;
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');
            xhr.upload.onprogress = onProgress || null;
            xhr.onload = function () {
                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(new Error('Cloudflare R2 rejected an upload part (HTTP ' + xhr.status + ').'));
                    return;
                }
                const etag = xhr.getResponseHeader('ETag') || xhr.getResponseHeader('etag');
                if (!etag) {
                    reject(new Error('Cloudflare R2 did not expose the part ETag. Check the R2 CORS ExposeHeaders setting.'));
                    return;
                }
                resolve(etag);
            };
            xhr.onerror = function () { reject(new Error('Direct Cloudflare R2 upload failed.')); };
            xhr.ontimeout = function () { reject(new Error('Direct Cloudflare R2 upload timed out.')); };
            xhr.onabort = function () { reject(new Error('Direct Cloudflare R2 upload was cancelled.')); };
            xhr.send(blob);
        });
    }

    async function requestR2Part(url, blob, onProgress) {
        let lastError;
        for (let attempt = 0; attempt < 3; attempt++) {
            try {
                return await uploadR2Part(url, blob, onProgress);
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

    let renderTimer = null;

    function renderQueueNow() {
        const $list = $('#bulk_video_file_list').empty();
        const total = queue.length;
        const completed = queue.filter(function (item) { return item.status === 'uploaded'; }).length;
        const failed = queue.filter(function (item) { return item.status === 'failed'; }).length;
        const saved = queue.filter(function (item) { return item.saved; }).length;
        const serverPending = queue.filter(function (item) { return item.status === 'processing' || item.status === 'server-queued'; }).length;
        $('#bulk_video_empty').toggle(total === 0);
        $('#bulk_video_summary').text(total
            ? (processing ? saved + ' of ' + total + ' saved · ' + serverPending + ' uploading to R2' : completed + ' of ' + total + ' uploaded') + (failed ? ' · ' + failed + ' failed' : '')
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
                        if (item.serverFailed && item.uploadId) {
                            retryServerUpload(item);
                            return;
                        }
                        item.status = 'queued';
                        item.label = 'Queued';
                        item.progress = 0;
                        item.error = '';
                        renderQueue();
                        processQueue();
                    })
                    .appendTo($actions);
            }
            if (item.status !== 'uploading' && !saving && !processing) {
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
            if (item.status === 'uploaded' || item.saved) $bar.addClass('bg-success');
            $row.append($progress.append($bar));
            if (item.error) $row.append($('<small class="text-danger d-block mt-1"></small>').text(item.error));
            $list.append($row);
        });

        const hasMetadata = $('#bulk_video_title').val().trim().length >= 2
            && $('#bulk_video_category').val()
            && $('#bulk_video_language').val();
        $('#save_bulk_videos').prop('disabled', saving || processing || uploading || !total || completed !== total || !hasMetadata);
        $('#bulk_video_files').prop('disabled', saving || processing);
    }

    function renderQueue() {
        if (renderTimer !== null) return;
        renderTimer = window.setTimeout(function () {
            renderTimer = null;
            renderQueueNow();
        }, 50);
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
        const directR2 = Boolean(initialized.direct_r2 && initialized.part_url && config.partUrl);
        item.label = 'Uploading';
        for (let index = 0; index < count; index++) {
            const start = index * actualChunkSize;
            const end = Math.min(start + actualChunkSize, item.file.size);
            const chunk = item.file.slice(start, end);
            if (directR2) {
                const partRequest = new FormData();
                partRequest.append('upload_id', item.uploadId);
                partRequest.append('chunk_index', index);
                partRequest.append('chunk_count', count);
                const signed = await request(config.partUrl, partRequest);
                const etag = await requestR2Part(signed.url, chunk, function (event) {
                    if (event.lengthComputable) {
                        item.progress = Math.floor(((start + event.loaded) / item.file.size) * 100);
                        renderQueue();
                    }
                });
                const receipt = new FormData();
                receipt.append('upload_id', item.uploadId);
                receipt.append('chunk_index', index);
                receipt.append('chunk_count', count);
                receipt.append('part_size', chunk.size);
                receipt.append('part_etag', etag);
                await requestChunk(config.chunkUrl, receipt);
            } else {
                const body = new FormData();
                body.append('upload_id', item.uploadId);
                body.append('chunk_index', index);
                body.append('chunk_count', count);
                body.append('chunk', chunk, item.file.name);
                await requestChunk(config.chunkUrl, body, function (event) {
                    if (event.lengthComputable) {
                        item.progress = Math.floor(((start + event.loaded) / item.file.size) * 100);
                        renderQueue();
                    }
                });
            }
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
        if (uploading || saving || processing) return;
        uploading = true;
        renderQueue();
        async function worker() {
            while (true) {
                const item = queue.find(function (candidate) { return candidate.status === 'queued'; });
                if (!item) return;
                try {
                    await uploadItem(item);
                } catch (error) {
                    item.status = 'failed';
                    item.label = 'Upload failed';
                    item.error = error.message || 'Upload failed.';
                    renderQueue();
                }
            }
        }

        const workers = [];
        const workerCount = Math.min(uploadConcurrency, queue.length);
        for (let index = 0; index < workerCount; index++) workers.push(worker());
        await Promise.all(workers);
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

    async function retryServerUpload(item) {
        if (saving || processing) return;
        saving = true;
        item.status = 'server-queued';
        item.label = 'Re-queueing on server';
        item.error = '';
        renderQueue();

        const body = new FormData();
        body.append('_token', config.csrf);
        body.append('title', $('#bulk_video_title').val().trim());
        body.append('type_id', config.typeId);
        body.append('category_id', $('#bulk_video_category').val());
        body.append('language_id', $('#bulk_video_language').val());
        body.append('upload_ids_json', JSON.stringify([item.uploadId]));
        try {
            const response = await request(config.saveUrl, body, null, 120000);
            saving = false;
            processing = true;
            item.serverFailed = false;
            $('#dvloader').hide();
            applyServerStatus(response.items || {});
            renderQueue();
            pollServerStatus();
        } catch (error) {
            saving = false;
            item.status = 'failed';
            item.serverFailed = true;
            item.error = error.message || 'Unable to re-queue this video.';
            renderQueue();
        }
    }

    function applyServerStatus(items) {
        let pending = false;
        let failed = false;
        queue.forEach(function (item) {
            if (!item.uploadId || item.saved) return;
            const state = items[item.uploadId] || { status: 'queued' };
            if (state.status === 'completed') {
                item.saved = true;
                item.status = 'uploaded';
                item.label = 'Saved to R2';
                item.progress = 100;
                item.error = '';
            } else if (state.status === 'failed' || state.status === 'missing') {
                item.status = 'failed';
                item.serverFailed = state.status === 'failed';
                item.label = state.status === 'missing' ? 'Server receipt missing' : 'R2 upload failed';
                item.error = state.error || 'The server could not finish this video. Retry it.';
                failed = true;
            } else {
                item.serverFailed = false;
                item.status = state.status === 'processing' ? 'processing' : 'server-queued';
                item.label = state.status === 'processing' ? 'Uploading to R2' : 'Queued on server';
                pending = true;
            }
        });
        return { pending: pending, failed: failed };
    }

    function finishServerBatch() {
        processing = false;
        const saved = queue.filter(function (item) { return item.saved; }).length;
        const failed = queue.filter(function (item) { return item.status === 'failed'; }).length;
        $('#dvloader').hide();
        renderQueue();
        if (!failed && saved === queue.length) {
            toastr.success(saved + ' videos saved to R2 successfully.');
            window.setTimeout(function () { window.location.replace(config.indexUrl); }, 800);
        } else if (failed) {
            toastr.error(saved + ' of ' + queue.length + ' videos saved. Retry the failed rows.');
        }
    }

    async function pollServerStatus() {
        if (!processing) return;
        const ids = queue.filter(function (item) { return item.uploadId && !item.saved; }).map(function (item) { return item.uploadId; });
        if (!ids.length) {
            finishServerBatch();
            return;
        }

        const body = new FormData();
        body.append('upload_ids_json', JSON.stringify(ids));
        try {
            const response = await request(config.statusUrl, body, null, 120000);
            const result = applyServerStatus(response.items || {});
            renderQueue();
            if (result.pending) {
                statusTimer = window.setTimeout(pollServerStatus, 2000);
            } else {
                finishServerBatch();
            }
        } catch (error) {
            // A temporary status request failure must not stop the worker or
            // make the browser re-upload files that are already on the server.
            statusTimer = window.setTimeout(pollServerStatus, 3000);
        }
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
            const body = new FormData();
            body.append('_token', config.csrf);
            body.append('title', title);
            body.append('type_id', config.typeId);
            body.append('category_id', categoryId);
            body.append('language_id', languageId);
            body.append('upload_ids_json', JSON.stringify(queue.map(function (item) { return item.uploadId; })));
            const response = await request(config.saveUrl, body, null, 120000);
            saving = false;
            processing = true;
            $('#dvloader').hide();
            applyServerStatus(response.items || {});
            renderQueue();
            pollServerStatus();
        } catch (error) {
            $('#dvloader').hide();
            const saved = queue.filter(function (item) { return item.saved; }).length;
            toastr.error((error.message || 'Unable to save the next video.') + ' ' + saved + ' of ' + queue.length + ' are saved. Click Save batch to safely continue.');
            saving = false;
            processing = false;
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
