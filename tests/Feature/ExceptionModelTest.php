<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Instance;
use App\Models\ReportExport;
use App\Models\Rule;
use App\Models\User;
use Database\Seeders\RuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExceptionModelTest extends TestCase
{
    use RefreshDatabase;

    private function finding(): Finding
    {
        $this->seed(RuleSeeder::class);
        $instance = Instance::create([
            'name' => 'SRV-PROD-01', 'host' => '10.0.0.15', 'port' => 1433,
            'environment' => 'production', 'username' => 'sqlaudit_collect', 'password' => 'x',
        ]);
        $run = $instance->auditRuns()->create(['status' => 'completed', 'started_at' => now()]);

        return $run->findings()->create([
            'rule_id' => Rule::where('code', 'R04')->value('id'), 'severity' => 'elevee',
            'object_name' => 'report_user', 'evidence' => 'CHECK_POLICY = OFF', 'recommendation' => 'Activer',
        ]);
    }

    public function test_approved_exception_hides_finding_until_expiration(): void
    {
        $finding = $this->finding();
        $dba = User::factory()->create(['role' => 'dba']);
        $auditor = User::factory()->create(['role' => 'auditeur']);

        $exception = $finding->exceptions()->create([
            'requested_by' => $dba->id, 'reason' => 'Outil historique', 'requested_expiration' => now()->addMonth(),
        ]);

        // En attente : ne masque pas encore le constat
        $this->assertNull($finding->fresh()->activeException);
        $this->assertSame('pending', $exception->fresh()->status);

        $exception->update(['status' => 'approved', 'reviewed_by' => $auditor->id, 'reviewed_at' => now()]);
        $this->assertTrue($finding->fresh()->activeException->is($exception));
        $this->assertSame($dba->id, $exception->requester->id);
        $this->assertSame($auditor->id, $exception->reviewer->id);

        // Expirée : le constat redevient ouvert
        $exception->update(['requested_expiration' => now()->subDay()]);
        $this->assertNull($finding->fresh()->activeException);
    }

    public function test_report_export_belongs_to_user(): void
    {
        $user = User::factory()->create(['role' => 'auditeur']);
        $export = $user->reportExports()->create([
            'type' => 'detail', 'scope' => 'SRV-PROD-01 · #12', 'format' => 'pdf', 'file_path' => 'reports/x.pdf',
        ]);

        $this->assertSame('Rapport détaillé d’audit', $export->typeLabel());
        $this->assertSame('PDF', $export->formatLabel());
        $this->assertTrue($export->user->is($user));
    }
}
