<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $role   = $request->string('role')->toString();

        $users = User::with('customer')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->when($role !== '', fn ($q) => $q->role($role))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users', compact('users', 'search', 'role'));
    }

    public function cards(Request $request): View
    {
        $users = User::with(['customer', 'roles'])
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('admin.user-cards', compact('users'));
    }

    public function create(): View
    {
        $user  = new User();
        $roles = Role::orderBy('name')->get();

        return view('admin.user-form', compact('user', 'roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['nullable', 'string', 'exists:roles,name'],
            'locale'   => ['nullable', 'string', 'in:cs,en'],
            'is_active'=> ['boolean'],
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'locale'    => $data['locale'] ?? 'cs',
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (!empty($data['role'])) {
            $user->assignRole($data['role']);
        }

        return redirect()->route('admin.users.index')
            ->with('status', 'Uživatel byl vytvořen.');
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();

        return view('admin.user-form', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', "unique:users,email,{$user->id}"],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role'     => ['nullable', 'string', 'exists:roles,name'],
            'locale'   => ['nullable', 'string', 'in:cs,en'],
            'is_active'=> ['boolean'],
        ]);

        $user->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'locale'    => $data['locale'] ?? $user->locale,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (!empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        if (array_key_exists('role', $data)) {
            $user->syncRoles($data['role'] ? [$data['role']] : []);
        }

        return redirect()->route('admin.users.index')
            ->with('status', 'Uživatel byl aktualizován.');
    }
}
