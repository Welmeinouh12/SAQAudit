<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyntheseTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;
    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = User::factory()->create(['role' => 'lecteur']);
        $this->auditor = User::factory()->create(['role' => 'auditeur']);
        File::ensureDirectoryExists(storage_path('app/reports'));
    }

    protected function tearDown(): void
    {
        File::delete(glob(storage_path('app/reports/test-*')));

        parent::tearDown();
    }

    private function export(array $attributes): ReportExport
    {
        $path = 'reports/test-'.uniqid().'.'.($attributes['format'] ?? 'pdf');
        File::put(storage_path('app/'.$path), '%PDF-test');

        $export = $this->auditor->reportExports()->create($attributes + [
            'type' => 'synthese', 'format' => 'pdf', 'scope' => 'Toutes les instances', 'file_path' => $path,
        ]);
        if (isset($attributes['created_at'])) {
            $export->forceFill(['created_at' => $attributes['created_at']])->save();
        }

        return $export;
    }

    public function test_reader_sees_synthesis_cards_with_trend(): void
    {
        $this->export(['findings_count' => 33, 'critical_count' => 5, 'instances_count' => 3, 'created_at' => now()->subMonth()]);
        $this->export(['findings_count' => 27, 'critical_count' => 4, 'instances_count' => 3]);
        // Ne doivent jamais apparaître : rapport détaillé et synthèse CSV
        $this->export(['type' => 'detail', 'findings_count' => 4242, 'scope' => 'RAPPORT-DETAILLE']);
        $this->export(['format' => 'csv', 'findings_count' => 4343, 'scope' => 'SYNTHESE-CSV']);

        $response = $this->actingAs($this->reader)->get('/syntheses')->assertOk();

        $response->assertSee('Rapports de synthèse');
        $response->assertSee('2 rapports');
        $response->assertSee('−6 constats');
        $response->assertSee('Télécharger le PDF');
        $response->assertDontSee('4242');
        $response->assertDontSee('4343');
    }

    public function test_filters_monthly_and_by_instance(): void
    {
        $instance = Instance::create([
            'name' => 'SRV-PROD-01', 'host' => '10.0.0.15', 'port' => 1433,
            'environment' => 'production', 'username' => 'x', 'password' => 'x',
        ]);
        $this->export(['findings_count' => 27]);
        $this->export(['instance_id' => $instance->id, 'scope' => 'SRV-PROD-01 · #12', 'findings_count' => 14]);

        $this->actingAs($this->reader)->get('/syntheses?filter=instance')
            ->assertSee('Synthèse SRV-PROD-01')->assertSee('1 rapport')->assertDontSee('Synthèse de conformité');
        $this->actingAs($this->reader)->get('/syntheses?filter=monthly')
            ->assertSee('Synthèse de conformité')->assertDontSee('Synthèse SRV-PROD-01');
    }

    public function test_download_is_protected_and_logged(): void
    {
        $synthesis = $this->export(['findings_count' => 27]);
        $detail = $this->export(['type' => 'detail']);

        $this->actingAs($this->reader)->get("/syntheses/{$synthesis->id}/download")->assertOk()->assertDownload();
        $this->assertDatabaseHas('activity_logs', ['type' => 'export', 'action' => 'Synthèse téléchargée']);

        // Un rapport détaillé n'est jamais téléchargeable par le lecteur
        $this->actingAs($this->reader)->get("/syntheses/{$detail->id}/download")->assertForbidden();
        // Les autres rôles n'ont pas accès à cette page
        $this->actingAs($this->auditor)->get('/syntheses')->assertForbidden();
    }

    public function test_empty_state(): void
    {
        $this->actingAs($this->reader)->get('/syntheses')
            ->assertOk()
            ->assertSee('Aucun rapport de synthèse publié pour le moment.');
    }
}
