<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    // GET /api/users
    public function index()
    {
        // Order by custom view_order (nulls last), then by id
        return response()->json(
            User::orderByRaw('CASE WHEN view_order IS NULL THEN 1 ELSE 0 END')
                ->orderBy('view_order')
                ->orderBy('id')
                ->get()
        );
    }

    // Return the user page view
    public function page()
    {
        return view('user');
    }

    // POST /api/users
    public function store(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer',
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
            'view_order' => 'nullable|integer',
        ]);

        // Ensure active is boolean
        if (isset($data['active'])) {
            $data['active'] = (bool) $data['active'];
        }

        $user = User::updateOrCreate(['id' => $data['id']], $data);
        return response()->json($user, 201);
    }

    // PUT /api/users/{id}
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
            'view_order' => 'nullable|integer',
        ]);

        // Ensure active is boolean
        if (isset($data['active'])) {
            $data['active'] = (bool) $data['active'];
        }

        $user = User::findOrFail($id);
        $user->update($data);
        return response()->json($user);
    }

    // DELETE /api/users/{id}
    public function destroy($id)
    {
        $user = User::find($id);
        if ($user) {
            $user->delete();
            return response()->json(['deleted' => true]);
        }
        return response()->json(['deleted' => false], 404);
    }
}
