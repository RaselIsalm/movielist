<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdSlot;
use App\Models\Common;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdSlotController extends Controller
{
    public const SLOT_LABELS = [
        'home.header_below' => 'Home — header below',
        'home.for_you_below' => 'Home — For You below',
        'home.recently_added_below' => 'Home — Recently added below',
        'home.more_to_explore_below' => 'Home — More to explore below',
        'home.browse_collections_below' => 'Home — Browse collections below',
        'home.browse_audio_below' => 'Home — Browse audio below',
        'home.clips_below' => 'Home — Clips below',
        'category.results_below' => 'Category — results below',
        'search.results_below' => 'Search — results below',
        'details.action_below' => 'Details — action below',
        'details.related_below' => 'Details — related below',
        'player.below' => 'Player — below',
    ];

    public function index()
    {
        return view('admin.adslots.index', [
            'slots' => AdSlot::orderBy('slot_key')->orderBy('sort_order')->orderByDesc('id')->get(),
            'slotLabels' => self::SLOT_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slot_key' => ['required', 'string', 'max:120', 'in:' . implode(',', array_keys(self::SLOT_LABELS))],
            'image' => ['required_without:image_url', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'image_url' => ['required_without:image', 'nullable', 'url', 'max:2048'],
            'click_url' => ['nullable', 'url', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'platform' => ['required', 'in:all,web,app'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'status' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 400, 'errors' => $validator->errors()->all()]);
        }

        $data = $request->only(['slot_key', 'click_url', 'alt_text', 'platform', 'sort_order', 'starts_at', 'ends_at']);
        $data['status'] = $request->boolean('status', true) ? 1 : 0;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['storage_type'] = Storage_Type();
        if ($request->hasFile('image')) {
            $data['image'] = (new Common())->saveImage($request->file('image'), 'ad', 'ad_', $data['storage_type']);
        } else {
            $data['image'] = trim((string) $request->input('image_url'));
            $data['storage_type'] = 1;
        }
        if (is_object($data['image'])) {
            return $data['image'];
        }
        AdSlot::create($data);
        return response()->json(['status' => 200, 'success' => 'Ad creative saved.']);
    }

    public function update(Request $request, $id)
    {
        $slot = AdSlot::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'click_url' => ['nullable', 'url', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'platform' => ['required', 'in:all,web,app'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'status' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 400, 'errors' => $validator->errors()->all()]);
        }
        $slot->fill($request->only(['click_url', 'alt_text', 'platform', 'sort_order', 'starts_at', 'ends_at']));
        $slot->status = $request->boolean('status');
        $slot->save();
        return response()->json(['status' => 200, 'success' => 'Ad creative updated.']);
    }

    public function destroy($id)
    {
        $slot = AdSlot::findOrFail($id);
        if ($slot->image && !filter_var($slot->image, FILTER_VALIDATE_URL)) {
            (new Common())->deleteImageToFolder('ad', $slot->image, $slot->storage_type);
        }
        $slot->delete();
        return response()->json(['status' => 200, 'success' => 'Ad creative deleted.']);
    }

    public function show($id)
    {
        $slot = AdSlot::findOrFail($id);
        $slot->status = $slot->status ? 0 : 1;
        $slot->save();
        return response()->json(['status' => 200, 'status_code' => $slot->status]);
    }
}
