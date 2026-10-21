<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Rule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Répartition des profils (tous les comptes, y compris désactivés)
        $roleCounts = User::selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $profiles = [];
        foreach (User::ROLES as $role => $label) {
            $profiles[$role] = $roleCounts[$role] ?? 0;
        }

        return view('admin.dashboard', [
            'activeUsers' => User::where('is_active', true)->count(),
            'inactiveUsers' => User::where('is_active', false)->count(),
            'activeRules' => Rule::where('is_active', true)->count(),
            'totalRules' => Rule::count(),
            'failedLogins' => ActivityLog::where('type', 'connexion')
                ->where('action', 'Connexion échouée')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'lockedUsers' => User::where('locked_until', '>', now())->count(),
            'lockoutAttempts' => Setting::get('lockout_attempts'),
            'profiles' => $profiles,
            'maxProfile' => max(1, max($profiles)), // évite une division par zéro
            'recentLogs' => ActivityLog::with('user')->latest('created_at')->latest('id')->take(5)->get(),
        ]);
    }
}
