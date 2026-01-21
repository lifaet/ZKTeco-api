<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Staff;
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

        if ($type === 'daily' && $date) {
            // Get all attendance records for the date
            $attendanceRecords = Attendance::whereDate('timestamp', $date)
                ->orderBy('timestamp')
                ->get();

            // Group by user_id
            $groupedRecords = $attendanceRecords->groupBy('user_id');
            $data = [];

            foreach ($groupedRecords as $userId => $records) {
                // Check staff status
                $staff = Staff::where('id', $userId)->first();
                if ($staff && !$staff->active) {
                    continue; // Skip inactive staff
                }

                // Calculate times
                $first = $records->first();
                $last = $records->last();

                $inTime = Carbon::parse($first->timestamp)->format('H:i:s');
                $outTime = ($first->id !== $last->id) ? Carbon::parse($last->timestamp)->format('H:i:s') : '';
                $workTime = '';

                if ($outTime) {
                    $diff = Carbon::parse($last->timestamp)->diff(Carbon::parse($first->timestamp));
                    $workTime = sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s ?? 0);
                }

                $data[] = [
                    'user_id' => $userId,
                    'date' => $date,
                    'first_punch' => $inTime,
                    'last_punch' => $outTime,
                    'work_time' => $workTime,
                    'punch' => $last->punch ?? '',
                    'status' => $last->status ?? '',
                    'is_absent' => false,
                ];
            }

            // Now, add absent entries for active staff with no attendance
            $activeStaff = Staff::where('active', true)->get();
            $attendedUserIds = $groupedRecords->keys()->toArray();

            foreach ($activeStaff as $staff) {
                if (!in_array($staff->id, $attendedUserIds)) {
                    $data[] = [
                        'user_id' => $staff->id,
                        'date' => $date,
                        'first_punch' => 'Absent',
                        'last_punch' => '',
                        'work_time' => '',
                        'punch' => '',
                        'status' => '',
                        'is_absent' => true,
                    ];
                }
            }

            // Apply search filter if provided
            if ($search = $request->input('search.value')) {
                $data = array_filter($data, function($row) use ($search) {
                    return stripos($row['user_id'], $search) !== false ||
                           stripos($row['first_punch'], $search) !== false ||
                           stripos($row['last_punch'], $search) !== false;
                });
            }

            $recordsTotal = count($data);
            $recordsFiltered = $recordsTotal;

            // Sort by user_id
            usort($data, function($a, $b) {
                return $a['user_id'] <=> $b['user_id'];
            });

            // Paginate
            $paged = array_slice($data, $start, $length);

            return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $paged,
            ]);
        }

        // For other types (monthly, user), use the original logic
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
                    'is_absent'   => false,
                ];
            })->values();
        };

        // Get all records for computing totals (grouped total)
        $allRecords = Attendance::orderBy('timestamp', 'desc')->get();
        $allGrouped = $groupCollection($allRecords);
        $recordsTotal = $allGrouped->count();

        // Build filtered query
        $filteredQuery = Attendance::query();

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

    /**
     * Add a manual attendance record (first punch required, optional last punch).
     */
    public function add(Request $request)
    {
        $userId = $request->input('user_id');
        $date = $request->input('date');
        $firstPunch = $request->input('first_punch');
        $lastPunch = $request->input('last_punch');
        $status = $request->input('status', null);

        if (! $userId || ! $date || ! $firstPunch) {
            return response()->json(['message' => 'user_id, date and first_punch are required'], 400);
        }

        try {
            $firstTs = Carbon::parse($date . ' ' . $firstPunch)->format('Y-m-d H:i:s');

            Attendance::create([
                'user_id' => $userId,
                'timestamp' => $firstTs,
                'status' => $status,
                'punch' => 'manual',
                'message' => 'Created manually via dashboard'
            ]);

            if ($lastPunch && $lastPunch !== $firstPunch) {
                $lastTs = Carbon::parse($date . ' ' . $lastPunch)->format('Y-m-d H:i:s');
                Attendance::create([
                    'user_id' => $userId,
                    'timestamp' => $lastTs,
                    'status' => $status,
                    'punch' => 'manual',
                    'message' => 'Created manually via dashboard'
                ]);
            }

            return response()->json(['message' => 'Attendance created successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create attendance: ' . $e->getMessage()], 500);
        }
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
