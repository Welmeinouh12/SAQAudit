<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\ReportExport;
use App\Models\Rule;
use App\Models\User;
use Database\Seeders\RuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;
    private Instance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RuleSeeder::class);
        $this->auditor = User::factory()->create(['role' => 'auditeur']);
        $this->instance = Instance::create([
            'name' => 'SRV-PROD-01', 'host' => '10.0.0.15', 'port' => 1433,
            'environment' => 'production', 'username' => 'sqlaudit_collect', 'password' => 'x',
        ]);
        $run = $this->instance->auditRuns()->create([
            'status' => 'completed', 'started_at' => now(), 'ended_at' => now(),
            'logins_count' => 38, 'databases_count' => 11, 'fingerprint' => hash('sha256', 'donnees'),
        ]);
        $run->findings()->create([
            'rule_id' => Rule::where('code', 'R01')->value('id'), 'severity' => 'critique',
            'object_name' => 'app_compta', 'evidence' => 'Membre de sysadmin', 'recommendation' => 'Retirer du rôle',
        ]);
    }

    protected function tearDown(): void
    {
        // Supprime les fichiers créés par les tests
        foreach (ReportExport::all() as $export) {
            File::delete(storage_path('app/'.$export->file_path));
        }

        parent::tearDown();
    }

    public function test_auditor_generates_pdf_and_downloads_it(): void
    {
        $this->actingAs($this->auditor)->get('/reports')->assertOk()->assertSee('Nouveau rapport');

        $this->actingAs($this->auditor)->post('/reports', [
            'type' => 'detail', 'scope' => $this->instance->id, 'format' => 'pdf', 'include_exceptions' => '1',
        ])->assertRedirect('/reports');

        $export = ReportExport::first();
        $this->assertSame('SRV-PROD-01 · #1', $export->scope);
        $this->assertSame(hash('sha256', 'donnees'), $export->fingerprint);
        $this->assertStringStartsWith('%PDF', File::get(storage_path('app/'.$export->file_path)));
        $this->assertDatabaseHas('activity_logs', ['type' => 'export', 'action' => 'Rapport généré']);

        $this->actingAs($this->auditor)->get("/reports/{$export->id}/download")->assertOk()->assertDownload();
    }

    public function test_synthesis_csv_has_bom_and_counts(): void
    {
        $this->actingAs($this->auditor)->post('/reports', ['type' => 'synthese', 'scope' => 'all', 'format' => 'csv']);

        $content = File::get(storage_path('app/'.ReportExport::first()->file_path));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('SRV-PROD-01;#1', $content);
    }

    public function test_excel_is_not_available_yet_and_files_are_not_public(): void
    {
        $this->actingAs($this->auditor)
            ->post('/reports', ['type' => 'detail', 'scope' => 'all', 'format' => 'xlsx'])
            ->assertSessionHasErrors('format');

        // DBA et auditeur ont accès aux rapports, pas le lecteur
        $this->actingAs(User::factory()->create(['role' => 'dba']))->get('/reports')->assertOk()->assertSee('Exports récents');
        $this->actingAs(User::factory()->create(['role' => 'lecteur']))->get('/reports')->assertForbidden();
    }
}
