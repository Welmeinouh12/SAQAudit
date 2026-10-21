<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Rule;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_pages_are_reserved_to_admins(): void
    {
        $dba = User::factory()->create(['role' => 'dba']);

        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs($dba)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_open_every_page(): void
    {
        $this->seed(RuleSeeder::class);
        $admin = $this->admin();

        foreach (['/admin', '/admin/users', '/admin/rules', '/admin/settings', '/admin/logs'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_creates_user_with_temporary_password(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Nouveau DBA',
            'email' => 'nouveau@sqlaudit.local',
            'role' => 'dba',
            'password' => 'Temporaire123',
        ])->assertRedirect('/admin/users');

        $user = User::where('email', 'nouveau@sqlaudit.local')->first();
        $this->assertTrue($user->must_change_password);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Utilisateur créé']);
    }

    public function test_admin_deactivates_user_instead_of_deleting(): void
    {
        $dba = User::factory()->create(['role' => 'dba', 'is_active' => true]);

        $this->actingAs($this->admin())->patch("/admin/users/{$dba->id}/toggle");

        $this->assertFalse($dba->fresh()->is_active);
    }

    public function test_admin_toggles_rule_and_it_is_logged(): void
    {
        $this->seed(RuleSeeder::class);
        $rule = Rule::where('code', 'R14')->first();

        $this->actingAs($this->admin())->patch("/admin/rules/{$rule->id}/toggle");

        $this->assertFalse($rule->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Règle modifiée', 'detail' => 'R14 désactivée']);
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'lockout_attempts' => 0,
        ])->assertSessionHasErrors(['lockout_attempts', 'retention_years']);
    }

    public function test_account_is_locked_after_too_many_failures(): void
    {
        Setting::set('lockout_attempts', '3');
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'mauvais']);
        }

        $this->assertTrue($user->fresh()->isLocked());
        $this->assertSame(3, ActivityLog::where('action', 'Connexion échouée')->count());
    }
}
