<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index()
    {
        return view('dashboard');
    }

    public function data(Request $request)
    {
        // Support filtering by type (daily/monthly/user), date, month, user
        $type = $request->query('type', 'daily');
        $date = $request->query('date', null);
        $month = $request->query('month', null);
        $user = $request->query('user', null);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        // Helper to group a collection of Attendance models by user+date
        $groupCollection = function($collection) {
            return $collection->groupBy(function($item) {
                return $item->user_id . '_' . Carbon::parse($item->timestamp)->format('Y-m-d');
            })->map(function($records) {
                $first = $records->sortBy('timestamp')->first();
                $last  = $records->sortByDesc('timestamp')->first();

                $inTime  = Carbon::parse($first->timestamp)->format('H:i:s');
                $outTime = ($first->id !== $last->id) ? Carbon::parse($last->timestamp)->format('H:i:s') : '';
                $workTime = '';

                if ($outTime) {
                    $diff = Carbon::parse($last->timestamp)->diff(Carbon::parse($first->timestamp));
                    $workTime = sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s ?? 0);
                }

                return [
                    'user_id'     => $first->user_id,
                    'date'        => Carbon::parse($first->timestamp)->format('Y-m-d'),
                    'first_punch' => $inTime,
                    'last_punch'  => $outTime,
                    'work_time'   => $workTime,
                    'punch'       => $last->punch ?? '',
                    'status'      => $last->status ?? '',
                ];
            })->values();
        };

        // Get all records for computing totals (grouped total)
        $allRecords = Attendance::orderBy('timestamp', 'desc')->get();
        $allGrouped = $groupCollection($allRecords);
        $recordsTotal = $allGrouped->count();

        // Build filtered query
        $filteredQuery = Attendance::query();

        if ($type === 'daily' && $date) {
            $filteredQuery->whereDate('timestamp', $date);
        }
        if ($type === 'monthly' && $month) {
            // month expected in YYYY-MM
            $filteredQuery->whereRaw("DATE_FORMAT(timestamp, '%Y-%m') = ?", [$month]);
        }
        if ($type === 'user' && $user) {
            $filteredQuery->where('user_id', $user);
        }

        if ($search = $request->input('search.value')) {
            $filteredQuery->where(function($q) use ($search) {
                $q->where('user_id', 'like', "%$search%")
                  ->orWhere('timestamp', 'like', "%$search%");
            });
        }

        $filteredRecords = $filteredQuery->orderBy('timestamp', 'desc')->get();
        $groupedFiltered = $groupCollection($filteredRecords);
        $recordsFiltered = $groupedFiltered->count();

        // paginate grouped results
        $paged = $groupedFiltered->slice($start, $length)->values();

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $paged,
        ]);
    }

    public function latest(Request $request)
    {
        $lastId = (int) $request->get('last_id', 0);

        // If a last_id is provided, return the next new entry after that id (oldest new entry)
        $newEntry = Attendance::where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->first(['id', 'user_id', 'timestamp']);

        // If no last_id provided or no newer entry found, return the most recent attendance (for initial sync)
        if (! $newEntry) {
            $newEntry = Attendance::orderBy('id', 'desc')->first(['id', 'user_id', 'timestamp']);
        }

        if (! $newEntry) {
            return response()->json(null);
        }

        $ts = Carbon::parse($newEntry->timestamp);

        return response()->json([
            'id' => $newEntry->id,
            'user_id' => $newEntry->user_id,
            'time' => $ts->format('H:i:s'),
            'date' => $ts->format('Y-m-d'),
        ]);
    }

    public function update(Request $request)
    {
        $userId = $request->input('user_id');
        $date = $request->input('date');
        
        // Find the attendance records for this user and date
        $records = Attendance::where('user_id', $userId)
            ->whereDate('timestamp', $date)
            ->orderBy('timestamp')
            ->get();

        if ($records->isEmpty()) {
            return response()->json(['message' => 'Records not found'], 404);
        }

        // Update the first and last records
        $firstRecord = $records->first();
        $lastRecord = $records->last();

        // Parse the time inputs
        $firstTime = Carbon::parse($date . ' ' . $request->input('first_punch'));
        
        // Update first punch
        $firstRecord->timestamp = $firstTime;
        $firstRecord->punch = $request->input('punch');
        $firstRecord->status = $request->input('status');
        $firstRecord->save();

        // If there's a last punch and it's different from first punch
        if ($request->input('last_punch') && $firstRecord->id !== $lastRecord->id) {
            $lastTime = Carbon::parse($date . ' ' . $request->input('last_punch'));
            $lastRecord->timestamp = $lastTime;
            $lastRecord->punch = $request->input('punch');
            $lastRecord->status = $request->input('status');
            $lastRecord->save();
        }

        return response()->json(['message' => 'Records updated successfully']);
    }

    public function delete(Request $request)
    {
        $userId = $request->input('user_id');
        $date = $request->input('date');

        // Delete all attendance records for this user and date
        $deleted = Attendance::where('user_id', $userId)
            ->whereDate('timestamp', $date)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Records not found'], 404);
        }

        return response()->json(['message' => 'Records deleted successfully']);
    }

    // Return distinct users for frontend dropdowns
    public function users()
    {
        $users = Attendance::select('user_id')
            ->distinct()
            ->orderBy('user_id')
            ->get()
            ->map(function($u){ return ['id' => $u->user_id]; });

        return response()->json($users);
    }
}
