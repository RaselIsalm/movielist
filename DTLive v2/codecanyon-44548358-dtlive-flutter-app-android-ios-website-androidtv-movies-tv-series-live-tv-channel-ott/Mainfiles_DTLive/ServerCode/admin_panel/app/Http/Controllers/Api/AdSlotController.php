<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdSlot;
use App\Models\Common;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdSlotController extends Controller
{
    public function index(Request $request)
    {
        $platform = $request->input('platform', 'web');
        $platform = in_array($platform, ['all', 'web', 'app'], true) ? $platform : 'web';
        $keys = $request->input('slot_keys', []);
        $keys = is_array($keys) ? array_values(array_filter($keys, 'is_string')) : [];
        $now = Carbon::now();

        $query = AdSlot::query()
            ->where('status', 1)
            ->whereIn('platform', ['all', $platform])
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('sort_order')
            ->orderByDesc('id');

        if ($keys !== []) {
            $query->whereIn('slot_key', array_slice($keys, 0, 50));
        }

        $common = new Common();
        $result = [];
        foreach ($query->get() as $slot) {
            if (!$slot->image) {
                continue;
            }
            $imageUrl = filter_var($slot->image, FILTER_VALIDATE_URL)
                ? $slot->image
                : $common->getImage('ad', $slot->image, 'normal', $slot->storage_type);
            $result[$slot->slot_key][] = [
                'id' => (int) $slot->id,
                'image' => $imageUrl,
                'click_url' => $slot->click_url,
                'alt_text' => $slot->alt_text ?? 'Advertisement',
                'sort_order' => (int) $slot->sort_order,
            ];
        }

        return response()->json(['status' => 200, 'result' => $result]);
    }
}
