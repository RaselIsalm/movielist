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
    // Keep the browser responsive on low-end machines and leave enough
    // connections for R2, status polling, and automatic saves. The queue is
    // still continuous: a worker starts the next file as soon as one ends.
    const uploadConcurrency = 2;
    const autoSaveConcurrency = 4;
    const requestRetryLimit = 4;
    const requestTimeout = 180000;
    let queue = [];
    let uploading = false;
    let activeAutoSaves = 0;
    let batchTotal = 0;
    let batchSaved = 0;
    let redirectTimer = null;

    function request(url, data, onProgress, timeout) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.timeout = typeof timeout === 'number' ? timeout : requestTimeout;
            xhr.setRequestHeader('X-CSRF-TOKEN', config.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            if (onProgress) xhr.upload.onprogress = onProgress;
            xhr.onload = function () {
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (error) {
                    const parseError = new Error('The server returned an unreadable response. Check your connection and sign in again if needed.');
                    parseError.status = xhr.status;
                    reject(parseError);
                    return;
                }
                if (xhr.status >= 200 && xhr.status < 300 && Number(response.status) === 200) {
                    resolve(response);
                    return;
                }
                const errors = response.errors || response.message || 'Upload request failed.';
                const requestError = new Error(Array.isArray(errors) ? errors.join(' ') : errors);
                requestError.status = xhr.status;
                requestError.response = response;
                reject(requestError);
            };
            xhr.onerror = function () {
                const networkError = new Error('Network error. Check your connection and retry this file.');
                networkError.status = xhr.status || 0;
                networkError.retryable = true;
                reject(networkError);
            };
            xhr.onabort = function () { reject(new Error('Upload was cancelled.')); };
            xhr.ontimeout = function () {
                const timeoutError = new Error('The upload request timed out. Retry this file; completed chunks are checked safely.');
                timeoutError.status = 408;
                timeoutError.retryable = true;
                reject(timeoutError);
            };
            xhr.send(data);
        });
    }

    function isRetryableRequestError(error) {
        const status = Number(error && error.status);
        return Boolean(error && (error.retryable || !status || status === 408 || status === 425 || status === 429 || status >= 500));
    }

    function retryDelay(attempt) {
        // Short jitter prevents a batch of workers from retrying together.
        const base = Math.min(8000, 700 * Math.pow(2, attempt));
        return base + Math.floor(Math.random() * 350);
    }

    async function requestWithRetry(url, data, onProgress, timeout, attempts) {
        let lastError;
        const maxAttempts = Math.max(1, attempts || requestRetryLimit);
        for (let attempt = 0; attempt < maxAttempts; attempt++) {
            try {
                return await request(url, data, onProgress, timeout);
            } catch (error) {
                lastError = error;
                if (attempt + 1 >= maxAttempts || !isRetryableRequestError(error)) {
                    throw error;
                }
                await wait(retryDelay(attempt));
            }
        }
        throw lastError;
    }

    async function requestChunk(url, data, onProgress) {
        return requestWithRetry(url, data, onProgress, requestTimeout, requestRetryLimit);
    }

    function uploadR2Part(url, blob, onProgress) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open('PUT', url, true);
            xhr.timeout = requestTimeout;
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
            xhr.onerror = function () {
                const networkError = new Error('Direct Cloudflare R2 upload failed.');
                networkError.status = xhr.status || 0;
                networkError.retryable = true;
                reject(networkError);
            };
            xhr.ontimeout = function () {
                const timeoutError = new Error('Direct Cloudflare R2 upload timed out.');
                timeoutError.status = 408;
                timeoutError.retryable = true;
                reject(timeoutError);
            };
            xhr.onabort = function () { reject(new Error('Direct Cloudflare R2 upload was cancelled.')); };
            xhr.send(blob);
        });
    }

    async function requestR2Part(url, blob, onProgress) {
        let lastError;
        for (let attempt = 0; attempt < requestRetryLimit; attempt++) {
            try {
                return await uploadR2Part(url, blob, onProgress);
            } catch (error) {
                lastError = error;
                if (attempt + 1 >= requestRetryLimit || !isRetryableRequestError(error)) {
                    throw error;
                }
                await wait(retryDelay(attempt));
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

    function hasMetadata() {
        return $('#bulk_video_title').val().trim().length >= 2
            && $('#bulk_video_category').val()
            && $('#bulk_video_language').val();
    }

    function createQueueRow(item) {
        const $row = $('<div class="border rounded p-2 mb-2" style="content-visibility:auto;contain-intrinsic-size:92px;"></div>')
            .attr('data-bulk-item-key', item.key);
        const $heading = $('<div class="d-flex flex-wrap justify-content-between align-items-center"></div>');
        const $description = $('<div class="mr-2"></div>');
        const $name = $('<strong></strong>').text(item.file.name);
        const $meta = $('<small class="text-muted d-block"></small>');
        const $poster = $('<img class="rounded mr-2" alt="Video preview" style="width:72px;height:48px;object-fit:cover;vertical-align:middle;">');
        $description.append($name).append($meta);
        const $actions = $('<div class="mt-1 mt-md-0"></div>');
        const $retry = $('<button type="button" class="btn btn-sm btn-outline-primary mr-1">Retry</button>')
            .on('click', function () {
                item.autoSaveStarted = false;
                if (item.serverFailed && item.uploadId) {
                    item.status = 'uploaded';
                    item.label = 'Waiting to save automatically';
                } else {
                    item.status = 'queued';
                    item.label = 'Queued';
                    item.progress = 0;
                }
                item.serverFailed = false;
                item.error = '';
                renderQueue();
                if (item.status === 'queued') processQueue();
                else processAutoSaveQueue();
            });
        const $remove = $('<button type="button" class="btn btn-sm btn-outline-danger">Remove</button>')
            .on('click', function () { removeItem(item); });
        $actions.append($retry).append($remove);
        $heading.append($description).append($actions);
        const $progress = $('<div class="progress mt-2" style="height: 8px;"></div>');
        const $bar = $('<div class="progress-bar" role="progressbar"></div>')
            .attr('aria-valuemin', '0')
            .attr('aria-valuemax', '100');
        const $error = $('<small class="text-danger d-block mt-1"></small>');
        $row.append($heading).append($progress.append($bar)).append($error);
        item.dom = { $row: $row, $description: $description, $poster: $poster, $name: $name, $meta: $meta, $retry: $retry, $remove: $remove, $bar: $bar, $error: $error };
        return $row;
    }

    function updateQueueRow(item) {
        const dom = item.dom || {};
        if (!dom.$row) return;
        if (item.posterUrl) {
            if (!dom.$poster.parent().length) dom.$description.prepend(dom.$poster);
            dom.$poster.attr('src', item.posterUrl).show();
        } else {
            dom.$poster.detach();
        }
        dom.$name.text(item.file.name);
        dom.$meta.text(formatSize(item.file.size) + ' · ' + item.label);
        dom.$retry.toggle(item.status === 'failed');
        dom.$remove.toggle(!['uploading', 'server-queued', 'processing', 'saving'].includes(item.status));
        const percent = Math.max(0, Math.min(100, item.progress));
        dom.$bar.css('width', percent + '%').attr('aria-valuenow', percent)
            .toggleClass('bg-danger', item.status === 'failed')
            .toggleClass('bg-success', item.status === 'uploaded' || item.saved);
        dom.$error.text(item.error || '').toggle(Boolean(item.error));
    }

    function renderQueueNow() {
        const $list = $('#bulk_video_file_list');
        const pending = queue.length;
        const failed = queue.filter(function (item) { return item.status === 'failed'; }).length;
        const total = batchTotal || pending;
        $('#bulk_video_empty').toggle(pending === 0);
        $('#bulk_video_summary').text(total
            ? batchSaved + ' of ' + total + ' saved automatically' + (pending ? ' · ' + pending + ' pending' : '') + (failed ? ' · ' + failed + ' failed' : '')
            : 'No files selected');
        $('#bulk_video_auto_status').text(hasMetadata()
            ? 'Each completed video is saved automatically and removed from this queue.'
            : 'Enter the shared title, category, and language to start automatic saving.');

        const liveKeys = {};
        queue.forEach(function (item) {
            liveKeys[item.key] = true;
            if (!item.dom) $list.append(createQueueRow(item));
            updateQueueRow(item);
        });
        $list.children('[data-bulk-item-key]').each(function () {
            if (!liveKeys[$(this).attr('data-bulk-item-key')]) $(this).remove();
        });

        $('#bulk_video_files').prop('disabled', !hasMetadata());
    }

    function renderQueue() {
        if (renderTimer !== null) return;
        renderTimer = window.setTimeout(function () {
            renderTimer = null;
            renderQueueNow();
        }, queue.length > 100 ? 150 : 50);
    }

    function wait(milliseconds) {
        return new Promise(function (resolve) { window.setTimeout(resolve, milliseconds); });
    }

    function maybeRedirectAfterBatch() {
        if (batchTotal > 0 && batchSaved === batchTotal && queue.length === 0 && !uploading && !redirectTimer) {
            toastr.success(batchSaved + ' video' + (batchSaved === 1 ? '' : 's') + ' uploaded successfully.');
            redirectTimer = window.setTimeout(function () {
                window.location.replace(config.indexUrl);
            }, 900);
        }
    }

    function finishAutomaticallySavedItem(item) {
        if (item.saved) return;
        item.saved = true;
        item.autoSaveStarted = false;
        item.status = 'saved';
        item.label = 'Saved to server';
        item.progress = 100;
        item.error = '';
        batchSaved += 1;
        if (item.posterUrl) URL.revokeObjectURL(item.posterUrl);
        queue = queue.filter(function (candidate) { return candidate.key !== item.key; });
        renderQueue();

        maybeRedirectAfterBatch();
    }

    async function pollAutomaticallySavedItem(item) {
        // Keep checking the durable receipt without requiring the user to
        // click Save again. A hard limit prevents a permanently stuck spinner.
        for (let attempt = 0; attempt < 180; attempt++) {
            const body = new FormData();
            body.append('upload_ids_json', JSON.stringify([item.uploadId]));
            const response = await requestWithRetry(config.statusUrl, body, null, 60000, 3);
            const state = response.items && response.items[item.uploadId]
                ? response.items[item.uploadId]
                : { status: 'queued' };
            if (state.status === 'completed') {
                finishAutomaticallySavedItem(item);
                return;
            }
            if (state.status === 'failed' || state.status === 'missing') {
                throw new Error(state.error || 'The server could not finish this video.');
            }
            item.status = state.status === 'processing' ? 'processing' : 'server-queued';
            item.label = state.status === 'processing' ? 'Saving to server' : 'Queued for server save';
            renderQueue();
            await wait(2000);
        }
        throw new Error('The server is taking too long to finish this video. Retry it from the queue.');
    }

    async function autoSaveItem(item) {
        if (!hasMetadata() || !item.uploadId || item.autoSaveStarted) return;
        if (String(config.demoMode) !== '1') {
            item.status = 'failed';
            item.serverFailed = true;
            item.error = 'Automatic saving is unavailable while demo mode is disabled.';
            renderQueue();
            return;
        }

        item.autoSaveStarted = true;
        item.status = 'saving';
        item.label = 'Saving automatically';
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
            const response = await requestWithRetry(config.saveUrl, body, null, requestTimeout, requestRetryLimit);
            const state = response.items && response.items[item.uploadId]
                ? response.items[item.uploadId]
                : null;
            if (state && state.status === 'completed') {
                finishAutomaticallySavedItem(item);
            } else {
                await pollAutomaticallySavedItem(item);
            }
        } catch (error) {
            item.autoSaveStarted = false;
            item.status = 'failed';
            item.serverFailed = true;
            item.label = 'Automatic save failed';
            item.error = error.message || 'Unable to save this video automatically. Retry it.';
            renderQueue();
        }
    }

    function processAutoSaveQueue() {
        if (!hasMetadata()) {
            renderQueue();
            return;
        }
        while (activeAutoSaves < autoSaveConcurrency) {
            const item = queue.find(function (candidate) {
                return candidate.status === 'uploaded' && candidate.uploadId && !candidate.autoSaveStarted;
            });
            if (!item) break;
            activeAutoSaves += 1;
            autoSaveItem(item).finally(function () {
                activeAutoSaves -= 1;
                renderQueue();
                processAutoSaveQueue();
            });
        }
        renderQueue();
        maybeRedirectAfterBatch();
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
        // Lets the server return the same staging receipt when a response is
        // lost after it created the upload. This prevents orphaned R2
        // multipart sessions when the browser retries an init request.
        init.append('client_key', item.key);
        const initialized = await requestWithRetry(config.initUrl, init, null, requestTimeout, requestRetryLimit);
        item.uploadId = initialized.upload_id;

        // The init retry may have recovered a receipt that completed before
        // the browser received its response. Do not upload the same chunks or
        // ask R2 to sign parts for an already completed multipart upload.
        if (initialized.complete === true) {
            item.status = 'uploaded';
            item.label = hasMetadata() ? 'Waiting to save automatically' : 'Uploaded · waiting for shared details';
            item.progress = 100;
            renderQueue();
            processAutoSaveQueue();
            return;
        }

        const actualChunkSize = initialized.chunk_size || chunkSize;
        const count = Math.ceil(item.file.size / actualChunkSize);
        const directR2 = Boolean(initialized.direct_r2 && initialized.part_url && config.partUrl);
        item.label = 'Uploading';
        let finalChunkReceipt = null;
        for (let index = 0; index < count; index++) {
            const start = index * actualChunkSize;
            const end = Math.min(start + actualChunkSize, item.file.size);
            const chunk = item.file.slice(start, end);
            if (directR2) {
                const partRequest = new FormData();
                partRequest.append('upload_id', item.uploadId);
                partRequest.append('chunk_index', index);
                partRequest.append('chunk_count', count);
                const signed = await requestWithRetry(config.partUrl, partRequest, null, 60000, requestRetryLimit);
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
                finalChunkReceipt = await requestChunk(config.chunkUrl, receipt);
            } else {
                const body = new FormData();
                body.append('upload_id', item.uploadId);
                body.append('chunk_index', index);
                body.append('chunk_count', count);
                body.append('chunk', chunk, item.file.name);
                finalChunkReceipt = await requestChunk(config.chunkUrl, body, function (event) {
                    if (event.lengthComputable) {
                        item.progress = Math.floor(((start + event.loaded) / item.file.size) * 100);
                        renderQueue();
                    }
                });
            }
            item.progress = Math.floor((end / item.file.size) * 100);
            renderQueue();
        }

        if (!finalChunkReceipt || finalChunkReceipt.complete !== true) {
            throw new Error('The server has not confirmed that this video is complete. Retry this file before automatic saving.');
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
            try {
                // A preview is optional. Never turn a fully uploaded video
                // into `Upload failed` because this small follow-up request
                // was interrupted; the queue worker has its own fallback.
                await requestWithRetry(config.thumbnailUrl, posterData, null, 60000, 3);
            } catch (error) {
                item.error = 'Video uploaded; preview will be generated on the server.';
                renderQueue();
            }
        }

        item.status = 'uploaded';
        item.label = hasMetadata()
            ? 'Waiting to save automatically'
            : 'Uploaded · waiting for shared details';
        item.progress = 100;
        renderQueue();
        processAutoSaveQueue();
    }

    async function processQueue() {
        if (uploading) return;
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
        processAutoSaveQueue();
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
            if (!hasMetadata()) {
                toastr.error('Enter the shared title, category, and language before choosing videos.');
                event.target.value = '';
                renderQueue();
                return;
            }
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
                batchTotal += 1;
            });
            event.target.value = '';
            renderQueue();
            processQueue();
        });
        $('#bulk_video_title, #bulk_video_category, #bulk_video_language').on('input change', function () {
            renderQueue();
            processAutoSaveQueue();
        });
        renderQueue();
    });
})(jQuery);
