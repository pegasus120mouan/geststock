<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UtilisateurController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->check(), 401);
            abort_unless(auth()->user()->role === 'admin', 403);

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $query = User::query()->orderBy('name');

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function ($q2) use ($q) {
                $q2->where('name', 'like', "%{$q}%")
                    ->orWhere('prenom', 'like', "%{$q}%")
                    ->orWhere('login', 'like', "%{$q}%")
                    ->orWhere('contact', 'like', "%{$q}%")
                    ->orWhere('matricule', 'like', "%{$q}%");
            });
        }

        return view('utilisateurs.index', [
            'utilisateurs' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function create()
    {
        return redirect()->route('utilisateurs.index', ['create' => 1]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'prenom' => ['nullable', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:255', 'unique:users,login'],
            'contact' => ['nullable', 'string', 'max:255', 'unique:users,contact'],
            'matricule' => ['nullable', 'string', 'max:255', 'unique:users,matricule'],
            'role' => ['required', 'in:admin,agent,driver'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $user = new User();
        $user->name = $validated['name'];
        $user->prenom = $validated['prenom'] ?? null;
        $user->login = $validated['login'];
        $user->contact = $validated['contact'] ?? null;
        $user->matricule = $validated['matricule'] ?? null;
        $user->role = $validated['role'];
        $user->password = $validated['password'];
        $user->code_pin = $pin;
        $user->avatar = $request->hasFile('avatar')
            ? $request->file('avatar')->store('avatars', 'public')
            : 'user.png';
        $user->save();

        return redirect()
            ->route('utilisateurs.index')
            ->with('code_pin_clair', $pin)
            ->with('success', 'Utilisateur créé avec succès.');
    }

    public function edit(User $utilisateur)
    {
        return view('utilisateurs.edit', compact('utilisateur'));
    }

    public function update(Request $request, User $utilisateur)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'prenom' => ['nullable', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:255', 'unique:users,login,'.$utilisateur->id],
            'contact' => ['nullable', 'string', 'max:255', 'unique:users,contact,'.$utilisateur->id],
            'matricule' => ['nullable', 'string', 'max:255', 'unique:users,matricule,'.$utilisateur->id],
            'role' => ['required', 'in:admin,agent,driver'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $utilisateur->fill([
            'name' => $validated['name'],
            'prenom' => $validated['prenom'] ?? null,
            'login' => $validated['login'],
            'contact' => $validated['contact'] ?? null,
            'matricule' => $validated['matricule'] ?? null,
            'role' => $validated['role'],
        ]);

        if ($request->hasFile('avatar')) {
            $utilisateur->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        if (! empty($validated['password'])) {
            $utilisateur->password = $validated['password'];
        }

        $utilisateur->save();

        return redirect()->route('utilisateurs.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(User $utilisateur)
    {
        abort_unless($utilisateur->id !== auth()->id(), 422);

        $utilisateur->delete();

        return redirect()->route('utilisateurs.index')->with('success', 'Utilisateur supprimé avec succès.');
    }
}
