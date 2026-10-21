<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditListTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstance(string $name): Instance
    {
        return Instance::create([
            'name' => $name, 'host' => '10.0.0.15', 'port' => 1433,
            'environment' => 'production', 'username' => 'sqlaudit_collect', 'password' => 'x',
        ]);
    }

    public function test_list_shows_all_audits_and_filters_by_instance(): void
    {
        $prod = $this->makeInstance('SRV-PROD-01');
        $rec = $this->makeInstance('SRV-REC-02');
        $prodRun = $prod->auditRuns()->create(['status' => 'completed', 'started_at' => now()]);
        $recRun = $rec->auditRuns()->create(['status' => 'failed', 'started_at' => now()->subDay()]);

        foreach (['dba', 'auditeur'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/audits')
                ->assertOk()
                ->assertSee('Audits et constats')
                ->assertSee('SRV-PROD-01')
                ->assertSee('SRV-REC-02')
                ->assertSee("/audits/{$prodRun->id}")   // la ligne mène aux résultats
                ->assertSee('aria-current="page"', false);
        }

        $this->actingAs(User::factory()->create(['role' => 'auditeur']))->get("/audits?instance={$rec->id}")
            ->assertOk()
            ->assertSee("#{$recRun->id}")
            ->assertSee('Échec')
            ->assertDontSee("/audits/{$prodRun->id}\"", false);
    }

    public function test_compare_route_is_not_taken_as_an_audit_id(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'auditeur']))
            ->get('/audits/compare')
            ->assertOk()
            ->assertViewIs('audits.compare');
    }

    public function test_reader_cannot_open_audit_list(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'lecteur']))->get('/audits')->assertForbidden();
    }
}
