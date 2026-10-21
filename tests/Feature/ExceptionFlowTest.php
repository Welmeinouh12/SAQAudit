<?php

namespace Tests\Feature;

use App\Models\AuditRun;
use App\Models\Finding;
use App\Models\FindingException;
use App\Models\Instance;
use App\Models\Rule;
use App\Models\User;
use Database\Seeders\RuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExceptionFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $dba;
    private User $auditor;
    private AuditRun $run;
    private Finding $finding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RuleSeeder::class);
        $this->dba = User::factory()->create(['role' => 'dba']);
        $this->auditor = User::factory()->create(['role' => 'auditeur']);

        $instance = Instance::create([
            'name' => 'SRV-PROD-01', 'host' => '10.0.0.15', 'port' => 1433,
            'environment' => 'production', 'username' => 'sqlaudit_collect', 'password' => 'x',
        ]);
        $this->run = $instance->auditRuns()->create([
            'status' => 'completed', 'started_at' => now(), 'ended_at' => now(), 'user_id' => $this->dba->id,
            'logins_count' => 38, 'databases_count' => 11, 'fingerprint' => str_repeat('a', 64),
        ]);
        $this->finding = $this->run->findings()->create([
            'rule_id' => Rule::where('code', 'R04')->value('id'), 'severity' => 'elevee',
            'object_name' => 'report_user', 'evidence' => 'CHECK_POLICY = OFF', 'recommendation' => 'Activer CHECK_POLICY sur le login',
        ]);
    }

    public function test_results_page_shows_findings_and_role_specific_buttons(): void
    {
        $this->actingAs($this->dba)->get("/audits/{$this->run->id}")
            ->assertOk()
            ->assertSee('report_user')
            ->assertSee("Relancer l'audit", false)
            ->assertSee('Proposer une exception');

        $this->actingAs($this->auditor)->get("/audits/{$this->run->id}")
            ->assertOk()
            ->assertDontSee("Relancer l'audit", false)
            ->assertDontSee('Proposer une exception');
    }

    public function test_csv_export_is_logged_and_has_bom(): void
    {
        $response = $this->actingAs($this->auditor)->get("/audits/{$this->run->id}/csv")->assertOk();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());
        $this->assertStringContainsString('report_user', $response->streamedContent());
        $this->assertDatabaseHas('activity_logs', ['type' => 'export', 'action' => 'Constats exportés']);
    }

    public function test_dba_proposes_exception_with_future_date(): void
    {
        // Date passée : refusée
        $this->actingAs($this->dba)
            ->post("/findings/{$this->finding->id}/exceptions", ['reason' => 'Outil historique', 'requested_expiration' => now()->subDay()->toDateString()])
            ->assertSessionHasErrorsIn("exception{$this->finding->id}", 'requested_expiration');

        $this->actingAs($this->dba)
            ->post("/findings/{$this->finding->id}/exceptions", ['reason' => 'Outil historique', 'requested_expiration' => now()->addMonth()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame('pending', $this->finding->exceptions()->first()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Exception proposée']);

        // L'auditeur ne peut pas proposer
        $this->actingAs($this->auditor)
            ->post("/findings/{$this->finding->id}/exceptions", ['reason' => 'x', 'requested_expiration' => now()->addMonth()->toDateString()])
            ->assertForbidden();
    }

    public function test_auditor_approves_and_finding_is_masked(): void
    {
        $exception = $this->finding->exceptions()->create([
            'requested_by' => $this->dba->id, 'reason' => 'Outil historique', 'requested_expiration' => now()->addMonth(),
        ]);

        $this->actingAs($this->auditor)->get('/exceptions')->assertOk()->assertSee("Valider l'exception", false);
        $this->actingAs($this->auditor)->patch("/exceptions/{$exception->id}/approve")->assertSessionHasNoErrors();

        $this->assertSame('approved', $exception->fresh()->status);
        $this->assertSame($this->auditor->id, $exception->fresh()->reviewed_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Exception validée']);

        $this->actingAs($this->dba)->get("/audits/{$this->run->id}")->assertSee("Exception validée jusqu'au", false);
    }

    public function test_reject_requires_comment(): void
    {
        $exception = $this->finding->exceptions()->create([
            'requested_by' => $this->dba->id, 'reason' => 'x', 'requested_expiration' => now()->addMonth(),
        ]);

        $this->actingAs($this->auditor)->patch("/exceptions/{$exception->id}/reject", ['review_comment' => ''])
            ->assertSessionHasErrorsIn("review{$exception->id}", 'review_comment');
        $this->assertSame('pending', $exception->fresh()->status);

        $this->actingAs($this->auditor)->patch("/exceptions/{$exception->id}/reject", ['review_comment' => 'Risque trop élevé']);
        $this->assertSame('rejected', $exception->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Exception refusée']);
    }

    public function test_user_cannot_review_own_request(): void
    {
        // Demande au nom de l'auditeur lui-même (ex. ancien DBA devenu auditeur)
        $exception = FindingException::create([
            'finding_id' => $this->finding->id, 'requested_by' => $this->auditor->id,
            'reason' => 'x', 'requested_expiration' => now()->addMonth(),
        ]);

        $this->actingAs($this->auditor)->patch("/exceptions/{$exception->id}/approve")->assertForbidden();
        $this->assertSame('pending', $exception->fresh()->status);
    }

    public function test_dba_cannot_open_exceptions_page(): void
    {
        $this->actingAs($this->dba)->get('/exceptions')->assertForbidden();
    }
}
