(function ($) {
    'use strict';

    const $repair = $('#video_preview_repair');
    const $button = $('#generate_missing_video_previews');
    if (!$repair.length || !$button.length) return;

    const config = {
        listUrl: $repair.data('list-url'),
        saveUrl: $repair.data('save-url'),
        csrf: $repair.data('csrf'),
        demoMode: $repair.data('demo-mode')
    };
    let running = false;

    function jsonRequest(url, method, data) {
        return new Promise(function (resolve, reject) {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            xhr.setRequestHeader('Accept', 'application/json');
            if (method !== 'GET') xhr.setRequestHeader('X-CSRF-TOKEN', config.csrf);
            xhr.onload = function () {
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (error) {
                    reject(new Error('The server returned an unreadable response. Sign in again and retry.'));
                    return;
                }
                if (xhr.status >= 200 && xhr.status < 300 && Number(response.status) === 200) {
                    resolve(response);
                    return;
                }
                const errors = response.errors || response.message || 'The request failed.';
                reject(new Error(Array.isArray(errors) ? errors.join(' ') : errors));
            };
            xhr.onerror = function () { reject(new Error('Network error. Check the connection and retry.')); };
            xhr.send(data || null);
        });
    }

    function capturePreview(url) {
        return new Promise(function (resolve, reject) {
            const video = document.createElement('video');
            let timer;
            let settled = false;

            function finish(error, blob) {
                if (settled) return;
                settled = true;
                window.clearTimeout(timer);
                video.pause();
                video.removeAttribute('src');
                video.load();
                if (error) reject(error);
                else resolve(blob);
            }

            function captureFrame() {
                if (settled) return;
                if (!video.videoWidth || !video.videoHeight) {
                    finish(new Error('The browser could not decode a frame from this video.'));
                    return;
                }

                const maxWidth = 640;
                const scale = Math.min(1, maxWidth / video.videoWidth);
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
                canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
                const context = canvas.getContext('2d');
                if (!context) {
                    finish(new Error('The browser could not create a preview image.'));
                    return;
                }
                try {
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    canvas.toBlob(function (blob) {
                        if (!blob) {
                            finish(new Error('Could not create an image from the video frame.'));
                            return;
                        }
                        finish(null, blob);
                    }, 'image/jpeg', 0.86);
                } catch (error) {
                    finish(new Error('The video host blocked preview-frame access. Check its CORS settings.'));
                }
            }

            video.crossOrigin = 'anonymous';
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
                        video.onseeked = null;
                        video.onloadeddata = captureFrame;
                    }
                } else {
                    video.onloadeddata = captureFrame;
                    if (video.readyState >= 2) captureFrame();
                }
            };
            video.onerror = function () {
                finish(new Error('This browser cannot play the video to generate its thumbnail.'));
            };
            timer = window.setTimeout(function () {
                finish(new Error('Preview generation timed out.'));
            }, 45000);
            video.src = url;
            video.load();
        });
    }

    function savePreview(item, blob) {
        const data = new FormData();
        data.append('video_id', item.id);
        data.append('thumbnail', blob, 'video-preview.jpg');
        return jsonRequest(config.saveUrl, 'POST', data);
    }

    function renderItem($row, status, isError) {
        $row.removeClass('text-success text-danger text-muted')
            .addClass(isError ? 'text-danger' : (status.indexOf('saved') === 0 ? 'text-success' : 'text-muted'))
            .text(status);
    }

    function updateProgress(index, total) {
        const percent = total ? Math.round(index / total * 100) : 0;
        $('#preview_repair_progress .progress-bar')
            .css('width', percent + '%')
            .attr('aria-valuenow', percent);
    }

    $button.on('click', async function () {
        if (running) return;
        if (config.demoMode != 1) {
            showError();
            return;
        }

        running = true;
        $button.prop('disabled', true);
        $('#preview_repair_items').empty();
        $('#preview_repair_progress').show();
        updateProgress(0, 1);
        $('#preview_repair_summary').text('Checking for videos without preview thumbnails…');

        let completed = 0;
        let failed = 0;
        try {
            const response = await jsonRequest(config.listUrl, 'GET');
            const items = response.items || [];
            const skipped = Number(response.skipped_count || 0);
            if (!items.length) {
                $('#preview_repair_summary').text(skipped
                    ? skipped + ' video(s) still need thumbnails, but their stored video file could not be accessed.'
                    : 'All stored videos already have thumbnails.');
                updateProgress(1, 1);
                return;
            }

            $('#preview_repair_summary').text('Generating thumbnails for ' + items.length + ' video(s)…');
            const rows = items.map(function (item) {
                const $row = $('<div class="small"></div>');
                const label = item.name ? item.name + ' · ' : '';
                $row.text(label + 'Queued');
                $('#preview_repair_items').append($row);
                return $row;
            });

            for (let index = 0; index < items.length; index++) {
                const item = items[index];
                renderItem(rows[index], 'Generating preview…', false);
                try {
                    const blob = await capturePreview(item.url);
                    await savePreview(item, blob);
                    completed++;
                    renderItem(rows[index], 'Preview saved', false);
                } catch (error) {
                    failed++;
                    renderItem(rows[index], 'Failed: ' + error.message, true);
                }
                updateProgress(index + 1, items.length);
                $('#preview_repair_summary').text(
                    (index + 1) + ' of ' + items.length + ' processed · ' + completed + ' saved · ' + failed + ' failed'
                    + (skipped ? ' · ' + skipped + ' source video(s) unavailable' : '')
                );
            }

            if (completed) $('#datatable').DataTable().ajax.reload(null, false);
            if (!failed) {
                $('#preview_repair_summary').text(
                    completed + ' video thumbnail(s) generated and saved.'
                    + (skipped ? ' ' + skipped + ' source video(s) were unavailable.' : '')
                );
            }
        } catch (error) {
            $('#preview_repair_summary').text(error.message);
            updateProgress(1, 1);
        } finally {
            running = false;
            $button.prop('disabled', false);
        }
    });
}(jQuery));
