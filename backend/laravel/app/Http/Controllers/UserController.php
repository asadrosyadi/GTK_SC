<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.user.index', [
            'items' => User::with('roles')
                ->search($request->query('q'))
                ->render($request->query('size')),
            'user' => $request->user(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('pages.user.create', [
            'roles' => Role::all()->map(fn ($r) => [$r->name, $r->name]),
            'user' => $request->user(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        try {
            $input = $request->validated();
            $role = $input['role'];
            unset($input['role']);

            $iotId = null;
            do {
            $iotId = Str::random(8);
            } while (User::where('iot_id', $iotId)->exists());

            $user = User::query()->create([
                ...$input,
                'password' => Hash::make('password'),
                'image' => 'default.jpg',
                'iot_id' => 'jTZids5M',
                'iot_token' => 'lOsx0KMJSAfJpxEe',
            ]);

            $user->assignRole($role);

            return redirect()
                ->route('user.index')
                ->with('notification', $this->successNotification('notification.success_create', 'menu.user'));
        } catch (\Throwable $throwable) {
            Log::error($throwable->getMessage());

            return redirect()
                ->route('user.index')
                ->with('notification', $this->failNotification('notification.fail_create', 'menu.user'));
        }
    }

    public function show(User $user, Request $request): View
    {
        $user->load(['roles']);

        return view('pages.user.show', [
            'item' => $user,
            'user' => $request->user(),
        ]);
    }

    public function edit(User $user, Request $request): View
    {
        $user->load(['roles']);

        return view('pages.user.edit', [
            'roles' => Role::all()->map(fn ($r) => [$r->name, $r->name]),
            'item' => $user,
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        try {
            $input = $request->validated();
            $role = $input['role'];
            unset($input['role']);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            if ($user->image && $user->image !== 'default.jpg' && file_exists(public_path('img/profile/' . $user->image))) {
                unlink(public_path('img/profile/' . $user->image));
            }

            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('img/profile/'), $imageName);
            $input['image'] = $imageName;
        }

            $user->update($input);

            $user->syncRoles([$role]);
            $user->assignRole($role);

            return redirect()
                ->route('user.index')
                ->with('notification', $this->successNotification('notification.success_update', 'menu.user'));
        } catch (\Throwable $throwable) {
            Log::error($throwable->getMessage());

            return back()
                ->with('notification', $this->failNotification('notification.fail_update', 'menu.user'));
        }
    }

    public function destroy(User $user): RedirectResponse
    {
        try {
            $user->delete();

            return redirect()
                ->route('user.index')
                ->with('notification', $this->successNotification('notification.success_delete', 'menu.user'));
        } catch (\Throwable $throwable) {
            Log::error($throwable->getMessage());

            return redirect()
                ->route('user.index')
                ->with('notification', $this->failNotification('notification.fail_delete', 'menu.user'));
        }
    }
}
