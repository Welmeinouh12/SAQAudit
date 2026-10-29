<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RuleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\DashboardController as UserDashboardController;
use App\Http\Controllers\ExceptionController;
use App\Http\Controllers\InstanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SyntheseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Tableau de bord DBA / auditeur
Route::get('/dashboard', [UserDashboardController::class, 'index'])
    ->middleware(['auth', 'role:dba,auditeur,lecteur'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Pas de suppression de compte : seul l'administrateur peut désactiver un utilisateur
});

// Espace DBA : instances SQL Server auditées
Route::middleware(['auth', 'role:dba'])->group(function () {
    Route::get('/instances', [InstanceController::class, 'index'])->name('instances.index');
    Route::post('/instances', [InstanceController::class, 'store'])->name('instances.store');
    Route::post('/instances/test', [InstanceController::class, 'testNew'])->name('instances.test-new');
    Route::post('/instances/{instance}/test', [InstanceController::class, 'test'])->name('instances.test');

    // Bouton « Auditer » : l'audit s'exécute directement
    Route::post('/instances/{instance}/audit', [AuditController::class, 'store'])->name('audits.store');

    // Le DBA propose une exception sur un constat
    Route::post('/findings/{finding}/exceptions', [ExceptionController::class, 'store'])->name('exceptions.store');
});

// Comparer deux audits : auditeur. Déclarée AVANT /audits/{auditRun}.
Route::get('/audits/compare', [AuditController::class, 'compare'])
    ->middleware(['auth', 'role:auditeur'])
    ->name('audits.compare');

// Liste des audits, résultats d'un audit et export CSV : DBA et auditeur
Route::middleware(['auth', 'role:dba,auditeur'])->group(function () {
    Route::get('/audits', [AuditController::class, 'index'])->name('audits.index');
    Route::get('/audits/{auditRun}',[AuditController::class, 'show'])->whereNumber('auditRun')->name('audits.show');
    Route::get('/audits/{auditRun}/csv', [AuditController::class, 'csv'])->whereNumber('auditRun')->name('audits.csv');

    // Rapports : fichiers dans storage/app/reports, téléchargés uniquement via cette route protégée
    // Le DBA consulte et télécharge les exports ; seul l'auditeur génère (route plus bas)
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{reportExport}/download', [ReportController::class, 'download'])->name('reports.download');
});

// Espace auditeur : exceptions
Route::middleware(['auth', 'role:auditeur'])->group(function () {
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');

    Route::get('/exceptions', [ExceptionController::class, 'index'])->name('exceptions.index');
    Route::patch('/exceptions/{exception}/approve', [ExceptionController::class, 'approve'])->name('exceptions.approve');
    Route::patch('/exceptions/{exception}/reject', [ExceptionController::class, 'reject'])->name('exceptions.reject');
});

// Espace lecteur : rapports de synthèse publiés (consultation et téléchargement)
Route::middleware(['auth', 'role:lecteur'])->group(function () {
    Route::get('/syntheses', [SyntheseController::class, 'index'])->name('syntheses.index');
    Route::get('/syntheses/{report}/download', [SyntheseController::class, 'download'])->name('syntheses.download');
});

// Catalogue de règles en consultation (activation réservée à l'admin, dans /admin/rules)
Route::get('/rules', [RuleController::class, 'index'])
    ->middleware(['auth', 'role:dba,auditeur'])
    ->name('rules.index');

// Espace administrateur
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Utilisateurs (pas de suppression : on désactive)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::patch('/users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');

    // Catalogue de règles
    Route::get('/rules', [RuleController::class, 'index'])->name('rules.index');
    Route::patch('/rules/{rule}/toggle', [RuleController::class, 'toggle'])->name('rules.toggle');

    // Paramètres
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/whitelist', [SettingController::class, 'addWhitelist'])->name('settings.whitelist.store');
    Route::delete('/settings/whitelist', [SettingController::class, 'removeWhitelist'])->name('settings.whitelist.destroy');

    // Journal d'activité (lecture seule)
    Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs.index');
    Route::get('/logs/export', [ActivityLogController::class, 'export'])->name('logs.export');
});

require __DIR__.'/auth.php';
