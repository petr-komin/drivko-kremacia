<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users', [
            'users' => User::orderBy('role')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:190|unique:users,email',
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
            'locale'   => ['nullable', Rule::in(array_keys(config('kremacia.locales')))],
            // Heslo je volitelné – stačí přístupový odkaz
            'password' => ['nullable', Password::min(10)],
        ]);

        $user = User::create(array_filter($data) + ['active' => true]);

        return $this->showLink($user, $user->issueAccessToken(), __('Účet :email je založený.', ['email' => $user->email]));
    }

    public function issueLink(Request $request, User $user)
    {
        $token = $user->issueAccessToken();

        // Vydání odkazu odhlásí všechna zařízení – vlastní přihlášení obnovit
        if ($user->is($request->user())) {
            Auth::login($user, remember: true);
        }

        return $this->showLink($user, $token, __('Nový odkaz pro :name. Předchozí přestal platit.', ['name' => $user->name]));
    }

    public function revokeLink(Request $request, User $user)
    {
        if ($user->is($request->user()) && ! $user->password) {
            return back()->withErrors(['active' => __('Vlastní odkaz nejde zrušit, když účet nemá heslo – zamkl byste se ven.')]);
        }

        $user->revokeAccessToken();

        if ($user->is($request->user())) {
            Auth::login($user, remember: true);
        }

        return back()->with('success', __('Odkaz pro :name je zrušený a jeho zařízení odhlášená.', ['name' => $user->name]));
    }

    /** Čerstvě vydaný odkaz ukáže nahoře; později je k dispozici u účtu (User::accessLink). */
    private function showLink(User $user, string $token, string $message)
    {
        return back()
            ->with('success', $message)
            ->with('access_link', ['user_id' => $user->id, 'url' => User::accessUrl($token)]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
            'locale'   => ['nullable', Rule::in(array_keys(config('kremacia.locales')))],
            'active'   => 'boolean',
            'password' => ['nullable', Password::min(10)],
        ]);

        // Pojistka, aby se dílna nezamkla sama ven
        if ($user->is($request->user()) && (! $request->boolean('active') || $data['role'] !== User::ROLE_ADMIN)) {
            return back()->withErrors(['active' => __('Vlastní účet nejde vypnout ani mu odebrat roli dílny.')]);
        }

        $user->fill([
            'name'   => $data['name'],
            'email'  => $data['email'],
            'role'   => $data['role'],
            'locale' => $data['locale'] ?? $user->locale,
            'active' => $request->boolean('active'),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return back()->with('success', __('Účet :email je uložený.', ['email' => $user->email]));
    }
}
