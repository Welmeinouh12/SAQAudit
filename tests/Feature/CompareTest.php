<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\Rule;
use App\Models\User;
use Database\Seeders\RuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareTest extends TestCase
{
    use RefreshDatabase;

    public function test_compare_detects_new_resolved_and_unchanged(): void
    {
        $this->seed(RuleSeeder::class);
        $auditor = User::factory()->create(['role' => 'auditeur']);
        $instance = Instance::create([
            'name' => 'SRV-PROD-01', 'host' => '10.0.0.15', 'port' => 1433,
            'environment' => 'production', 'username' => 'sqlaudit_collect', 'password' => 'x',
        ]);
        $rule = fn ($code) => Rule::where('code', $code)->value('id');
        $make = fn ($run, $code, $severity, $object) => $run->findings()->create([
            'rule_id' => $rule($code), 'severity' => $severity, 'object_name' => $object, 'evidence' => 'preuve', 'recommendation' => 'y',
        ]);

        $before = $instance->auditRuns()->create(['status' => 'completed', 'started_at' => now()->subWeek()]);
        $make($before, 'R01', 'critique', 'app_compta');
        $make($before, 'R03', 'elevee', 'sa');

        $after = $instance->auditRuns()->create(['status' => 'completed', 'started_at' => now()]);
        $make($after, 'R01', 'critique', 'app_compta');    // inchangé
        $make($after, 'R16', 'elevee', 'xp_cmdshell');     // nouveau
        $make($after, 'R04', 'elevee', 'report_user');     // nouveau

        // Sans paramètre : les deux derniers audits de l'instance
        $response = $this->actingAs($auditor)->get('/audits/compare')->assertOk();
        $this->assertCount(2, $response->viewData('diff')['new']);
        $this->assertCount(1, $response->viewData('diff')['resolved']);   // sa
        $this->assertCount(1, $response->viewData('diff')['unchanged']);  // app_compta
        $response->assertSee('+2')->assertSee('xp_cmdshell')->assertSee("Absent de l'audit #".$after->id, false);

        // Filtre « Résolu »
        $this->actingAs($auditor)
            ->get("/audits/compare?instance={$instance->id}&reference={$before->id}&compared={$after->id}&filter=resolved")
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows[0]['finding']->object_name === 'sa');
    }

    public function test_compare_is_reserved_to_auditor(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'auditeur']))->get('/audits/compare')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'dba']))->get('/audits/compare')->assertForbidden();
    }
}
