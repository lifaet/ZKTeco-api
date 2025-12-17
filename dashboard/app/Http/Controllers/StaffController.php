<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;

class StaffController extends Controller
{
    // GET /api/staff
    public function index()
    {
        return response()->json(Staff::orderBy('id')->get());
    }

    // Return the staff page view
    public function page()
    {
        return view('staff');
    }

    // POST /api/staff
    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer',
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
        ]);

        $staff = Staff::updateOrCreate(['id' => $data['id']], $data);
        return response()->json($staff, 201);
    }

    // PUT /api/staff/{id}
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
        ]);

        $staff = Staff::findOrFail($id);
        $staff->update($data);
        return response()->json($staff);
    }

    // DELETE /api/staff/{id}
    public function destroy($id)
    {
        $staff = Staff::find($id);
        if ($staff) {
            $staff->delete();
            return response()->json(['deleted' => true]);
        }
        return response()->json(['deleted' => false], 404);
    }
}
