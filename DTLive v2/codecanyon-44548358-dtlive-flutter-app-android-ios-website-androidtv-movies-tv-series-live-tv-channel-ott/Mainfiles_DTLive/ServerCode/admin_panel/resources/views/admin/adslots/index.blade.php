@extends('admin.layout.page-app')
@section('page_title', 'Ad slots')
@section('tab_title', 'Ad slots')

@section('content')
    @include('admin.layout.sidebar')
    <div class="right-content">
        @include('admin.layout.header')
        <div class="body-content">
            <h1 class="page-title-sm">Ad slots</h1>
            <div class="card custom-border-card mb-4">
                <div class="card-header"><i class="fa-solid fa-rectangle-ad mr-2"></i>Add creative</div>
                <div class="card-body">
                    <form id="ad-slot-form" enctype="multipart/form-data">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Placement</label>
                                <select name="slot_key" class="form-control" required>
                                    @foreach($slotLabels as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label>Platform</label>
                                <select name="platform" class="form-control">
                                    <option value="all">Web + App</option>
                                    <option value="web">Web only</option>
                                    <option value="app">App only</option>
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label>Priority</label>
                                <input type="number" name="sort_order" class="form-control" value="0" min="0">
                            </div>
                            <div class="form-group col-md-2 d-flex align-items-end">
                                <label class="mb-2"><input type="checkbox" name="status" value="1" checked> Active</label>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Image file (recommended)</label>
                                <input type="file" name="image" class="form-control-file" accept="image/jpeg,image/png,image/webp">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Or image URL</label>
                                <input type="url" name="image_url" class="form-control" placeholder="https://...">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Click URL (optional)</label>
                                <input type="url" name="click_url" class="form-control" placeholder="https://...">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Alt text</label>
                                <input type="text" name="alt_text" class="form-control" maxlength="255">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-3"><label>Starts at</label><input type="datetime-local" name="starts_at" class="form-control"></div>
                            <div class="form-group col-md-3"><label>Ends at</label><input type="datetime-local" name="ends_at" class="form-control"></div>
                        </div>
                        <button class="btn btn-primary" type="submit">Save creative</button>
                    </form>
                </div>
            </div>

            <div class="card custom-border-card">
                <div class="card-header">Configured creatives</div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead><tr><th>Preview</th><th>Placement</th><th>Platform</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        @forelse($slots as $slot)
                            <tr id="ad-row-{{ $slot->id }}">
                                <td><img src="{{ filter_var($slot->image, FILTER_VALIDATE_URL) ? $slot->image : (new \App\Models\Common())->getImage('ad', $slot->image, 'normal', $slot->storage_type) }}" alt="{{ $slot->alt_text }}" style="max-width:180px;max-height:60px;object-fit:contain"></td>
                                <td><code>{{ $slot->slot_key }}</code></td>
                                <td>{{ strtoupper($slot->platform) }}</td>
                                <td>{{ $slot->sort_order }}</td>
                                <td><button class="btn btn-sm {{ $slot->status ? 'btn-success' : 'btn-secondary' }}" onclick="toggleAd({{ $slot->id }}, this)">{{ $slot->status ? 'Active' : 'Off' }}</button></td>
                                <td><button class="btn btn-sm btn-outline-danger" onclick="deleteAd({{ $slot->id }})">Delete</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">No creatives configured yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        $('#ad-slot-form').on('submit', function (event) {
            event.preventDefault();
            $.ajax({
                url: "{{ route('admin.adslots.store') }}",
                method: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                headers: {'X-CSRF-TOKEN': csrf},
                success: function (response) {
                    if (response.status === 200) location.reload();
                    else toastr.error((response.errors || ['Unable to save creative']).join(', '));
                },
                error: function () { toastr.error('Unable to save creative.'); }
            });
        });
        function toggleAd(id, button) {
            $.get("{{ url('admin/adslots') }}/" + id, function (response) {
                if (response.status === 200) {
                    button.className = 'btn btn-sm ' + (response.status_code ? 'btn-success' : 'btn-secondary');
                    button.textContent = response.status_code ? 'Active' : 'Off';
                }
            });
        }
        function deleteAd(id) {
            if (!confirm('Delete this creative?')) return;
            $.ajax({url: "{{ url('admin/adslots') }}/" + id, method: 'DELETE', headers: {'X-CSRF-TOKEN': csrf},
                success: function (response) { if (response.status === 200) $('#ad-row-' + id).remove(); }});
        }
    </script>
@endsection
