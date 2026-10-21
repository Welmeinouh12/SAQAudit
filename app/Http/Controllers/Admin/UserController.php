<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Liste des utilisateurs (filtrable par profil) + formulaire de création.
     */
    public function index(Request $request): View
    {
        return $this->listView($request);
    }

    /**
     * Même page, mais le formulaire de droite modifie l'utilisateur choisi.
     */
    public function edit(Request $request, User $user): View
    {
        return $this->listView($request, $user);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Le mot de passe est haché automatiquement (cast « hashed » du modèle User)
        $user = User::create($validated + ['is_active' => true]);
        $user->forceFill(['must_change_password' => true])->save();

        ActivityLogger::log('administration', 'Utilisateur créé', $user->email.' · '.$user->roleLabel());

        return redirect()->route('admin.users.index')->with('status', "Le compte {$user->email} a été créé.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        // L'administrateur ne peut pas se retirer lui-même le profil admin
        if ($user->is($request->user()) && $validated['role'] !== 'admin') {
            return back()->withInput()->withErrors(['role' => 'Vous ne pouvez pas retirer votre propre profil Administrateur.']);
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        // Nouveau mot de passe temporaire seulement si le champ est rempli
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
            $user->must_change_password = true;
        }

        $changes = array_keys($user->getDirty());
        $user->save();

        if ($changes) {
            ActivityLogger::log('administration', 'Utilisateur modifié', $user->email.' · '.implode(', ', $changes));
        }

        return redirect()->route('admin.users.index')->with('status', "Le compte {$user->email} a été modifié.");
    }

    /**
     * Activer ou désactiver un compte. On ne supprime jamais un utilisateur.
     */
    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('status', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        ActivityLogger::log('administration', $user->is_active ? 'Utilisateur activé' : 'Utilisateur désactivé', $user->email);

        return back()->with('status', "Le compte {$user->email} a été ".($user->is_active ? 'activé.' : 'désactivé.'));
    }

    /**
     * Débloquer un compte verrouillé après trop d'échecs de connexion.
     */
    public function unlock(User $user): RedirectResponse
    {
        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();

        ActivityLogger::log('administration', 'Compte débloqué', $user->email);

        return back()->with('status', "Le compte {$user->email} a été débloqué.");
    }

    protected function listView(Request $request, ?User $editing = null): View
    {
        // Filtre ignoré si la valeur n'est pas un profil connu
        $role = (string) $request->query('role');
        $role = isset(User::ROLES[$role]) ? $role : null;

        $users = User::query()
            ->when($role, fn ($query) => $query->where('role', $role))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('admin.users.index', [
            'users' => $users,
            'currentRole' => $role,
            'editing' => $editing,
        ]);
    }
}
