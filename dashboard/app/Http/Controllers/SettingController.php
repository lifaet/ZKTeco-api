<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    // GET /api/settings
    public function index()
    {
        return response()->json([
            // Day numbers: 0=Sunday ... 6=Saturday. Default weekend: Friday & Saturday.
            'weekend_days' => Setting::getValue('weekend_days', [5, 6]),
            // Holidays as list of YYYY-MM-DD strings
            'holidays' => Setting::getValue('holidays', []),
        ]);
    }

    // POST /api/settings
    public function update(Request $request)
    {
        $data = $request->validate([
            'weekend_days' => 'sometimes|array',
            'weekend_days.*' => 'integer|min:0|max:6',
            'holidays' => 'sometimes|array',
            'holidays.*' => 'date_format:Y-m-d',
        ]);

        if (array_key_exists('weekend_days', $data)) {
            Setting::setValue('weekend_days', array_values(array_unique($data['weekend_days'])));
        }
        if (array_key_exists('holidays', $data)) {
            $holidays = array_values(array_unique($data['holidays']));
            sort($holidays);
            Setting::setValue('holidays', $holidays);
        }

        return $this->index();
    }
}
