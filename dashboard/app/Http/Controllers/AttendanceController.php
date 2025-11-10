<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        // We'll perform grouping at the database level to avoid loading the entire table into PHP.
        // Group by user_id and date(timestamp). Use COUNT DISTINCT for totals.

        // Base filters applied to queries
        $baseQuery = DB::table('attendances');
        if ($type === 'daily' && $date) {
            $baseQuery = $baseQuery->whereDate('timestamp', $date);
        }
        if ($type === 'monthly' && $month) {
            $baseQuery = $baseQuery->whereRaw("DATE_FORMAT(timestamp, '%Y-%m') = ?", [$month]);
        }
        if ($type === 'user' && $user) {
            $baseQuery = $baseQuery->where('user_id', $user);
        }
        if ($search = $request->input('search.value')) {
            $baseQuery = $baseQuery->where(function($q) use ($search) {
                $q->where('user_id', 'like', "%$search%")
                  ->orWhere('timestamp', 'like', "%$search%");
            });
        }

        // recordsTotal: count of distinct user+date groups in entire table (no filters)
        $recordsTotal = DB::table('attendances')
            ->selectRaw("COUNT(DISTINCT CONCAT(user_id, '_', DATE(timestamp))) as cnt")
            ->value('cnt');

        // recordsFiltered: count of distinct groups after applying filters
        $recordsFiltered = (clone $baseQuery)
            ->selectRaw("COUNT(DISTINCT CONCAT(user_id, '_', DATE(timestamp))) as cnt")
            ->value('cnt');

        // Now get the paged groups
        $groups = (clone $baseQuery)
            ->selectRaw("user_id, DATE(timestamp) as date, MIN(timestamp) as first_ts, MAX(timestamp) as last_ts")
            ->groupBy('user_id', DB::raw("DATE(timestamp)"))
            ->orderBy('date', 'desc')
            ->orderBy('user_id')
            ->offset($start)
            ->limit($length)
            ->get();

        // For each group, fetch last record (to get punch/status) and compute derived fields
        $data = [];
        foreach ($groups as $g) {
            $firstPunch = $g->first_ts ? Carbon::parse($g->first_ts)->format('H:i:s') : '';
            $lastPunch = $g->last_ts ? Carbon::parse($g->last_ts)->format('H:i:s') : '';
            $workTime = '';
            if ($g->first_ts && $g->last_ts && $g->last_ts !== $g->first_ts) {
                $diff = Carbon::parse($g->last_ts)->diff(Carbon::parse($g->first_ts));
                $workTime = sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s ?? 0);
            }

            $lastRec = Attendance::where('user_id', $g->user_id)
                ->whereDate('timestamp', $g->date)
                ->orderBy('timestamp', 'desc')
                ->first(['punch', 'status']);

            $data[] = [
                'user_id' => $g->user_id,
                'date' => $g->date,
                'first_punch' => $firstPunch,
                'last_punch' => $lastPunch,
                'work_time' => $workTime,
                'punch' => $lastRec->punch ?? '',
                'status' => $lastRec->status ?? '',
            ];
        }

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => intval($recordsTotal),
            'recordsFiltered' => intval($recordsFiltered),
            'data' => $data,
        ]);
    }

    public function latest(Request $request)
    {

        $lastId = (int) $request->get('last_id', 0);

        // If a last_id is provided, return the next new entry after that id (oldest new entry)
        if ($lastId > 0) {
            $newEntry = Attendance::where('id', '>', $lastId)
                ->orderBy('id', 'asc')
                ->first(['id', 'user_id', 'timestamp']);

            // If a last_id was provided but no newer entry found, return 204 No Content
            if (! $newEntry) {
                return response()->json(null, 204);
            }
        } else {
            // No last_id provided -> return the most recent attendance (for initial sync)
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
