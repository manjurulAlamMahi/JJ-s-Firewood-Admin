<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin Users — the people who can log in to admin.jjsfirewoodperth.com.au.
 *
 * An admin is any row in `users` with is_admin = 1 (role = 'admin'). Customers live in
 * the same table, so "removing" an admin only takes away panel access; it never deletes
 * the account (they might also be an app customer).
 */
class UserController extends Controller
{
    public function index()
    {
        $admins = User::where('is_admin', 1)->orderBy('name')->get();

        return view('backend.layouts.users.index', compact('admins'));
    }

    /** Kept for the old DataTables URL; now returns admins only. */
    public function getData()
    {
        return response()->json([
            'data' => User::where('is_admin', 1)->orderBy('name')->get(['id', 'name', 'email', 'created_at']),
        ]);
    }

    public function create()
    {
        return view('backend.layouts.users.create');
    }

    /**
     * Add an admin. If the email already has an account (e.g. an app customer), that
     * account is given admin access; otherwise a new account is created.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $email = strtolower(trim($request->email));
        $existing = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing && $existing->is_admin) {
            return back()->withInput()->withErrors(['email' => 'That person is already an admin.']);
        }

        if ($existing) {
            $existing->is_admin = 1;
            $existing->role = 'admin';
            if ($request->filled('password')) {
                $existing->password = $request->password; // hashed by the model cast
            }
            $existing->save();

            Log::info('Admin access granted to existing account', ['user_id' => $existing->id, 'by' => auth()->id()]);

            return redirect()->route('users.index')->with('success',
                "{$existing->email} already had an account and has been given admin access."
                . ($request->filled('password') ? ' Their password was updated.' : ' They log in with their existing password.'));
        }

        if (!$request->filled('password')) {
            return back()->withInput()->withErrors(['password' => 'A password is required for a new account.']);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $email,
            'password' => $request->password,
            'is_admin' => 1,
            'role'     => 'admin',
        ]);
        $user->email_verified_at = now();
        $user->save();

        Log::info('Admin account created', ['user_id' => $user->id, 'by' => auth()->id()]);

        return redirect()->route('users.index')->with('success', "Admin {$user->email} created. They can log in now.");
    }

    public function edit($id)
    {
        $user = User::where('is_admin', 1)->findOrFail($id);

        return view('backend.layouts.users.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::where('is_admin', 1)->findOrFail($id);

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->name  = $request->name;
        $user->email = strtolower(trim($request->email));
        if ($request->filled('password')) {
            $user->password = $request->password;
        }
        $user->save();

        return redirect()->route('users.index')->with('success', 'Admin updated.');
    }

    /** Remove admin access (the account itself is kept). */
    public function destroy($id)
    {
        $user = User::where('is_admin', 1)->findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['admin' => "You can't remove your own admin access."]);
        }
        if (User::where('is_admin', 1)->count() <= 1) {
            return back()->withErrors(['admin' => "You can't remove the last admin."]);
        }

        $user->is_admin = 0;
        $user->role = 'customer';
        $user->save();

        Log::info('Admin access removed', ['user_id' => $user->id, 'by' => auth()->id()]);

        return redirect()->route('users.index')->with('success', "{$user->email} can no longer log in to the admin panel.");
    }
}
