<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users with search and pagination.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $users = User::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'asc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users'],
            'username' => ['required', 'string', 'max:50', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'],
            'phone' => $validated['phone'] ?? null,
            'password' => bcrypt($validated['password']),
            'role' => 'user', // Auto-assign 'user' role for admin-created users
            'timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
            'email_verified_at' => now(), // Auto-verify for admin-created users
        ]);

        return back()->with('success', 'User created successfully.');
    }

    /**
     * Toggle the specified user's disabled status.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        // Prevent self-modification
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot modify your own status.']);
        }

        $user->update([
            'is_disabled' => !$user->is_disabled,
        ]);

        $status = $user->is_disabled ? 'disabled' : 'enabled';

        return back()->with('success', "User has been {$status} successfully.");
    }
}
