<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\Rule;
use App\Models\User;
use Database\Seeders\RuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_findings_of_latest_audit_only(): void
    {
        $this->seed(RuleSeeder::class);
        $dba = User::factory()->create(['role' => 'dba']);
        $instance = Instance::create([
            'name' => 'SRV-PROD-01', 'host' => '10.0.0.15', 'port' => 1433,
            'environment' => 'production', 'username' => 'sqlaudit_collect', 'password' => 'x',
        ]);
        $r01 = Rule::where('code', 'R01')->first();

        // Ancien audit : 3 constats critiques (remplacés par l'audit suivant)
        $old = $instance->auditRuns()->create(['status' => 'completed', 'started_at' => now()->subDay(), 'user_id' => $dba->id]);
        foreach (range(1, 3) as $i) {
            $old->findings()->create(['rule_id' => $r01->id, 'severity' => 'critique', 'object_name' => "old{$i}", 'evidence' => 'x', 'recommendation' => 'y']);
        }

        // Dernier audit : 1 critique + 1 moyenne
        $latest = $instance->auditRuns()->create(['status' => 'completed', 'started_at' => now(), 'user_id' => $dba->id]);
        $latest->findings()->create(['rule_id' => $r01->id, 'severity' => 'critique', 'object_name' => 'a', 'evidence' => 'x', 'recommendation' => 'y']);
        $latest->findings()->create(['rule_id' => $r01->id, 'severity' => 'moyenne', 'object_name' => 'b', 'evidence' => 'x', 'recommendation' => 'y']);

        $response = $this->actingAs($dba)->get('/dashboard')->assertOk();

        $response->assertViewHas('openFindings', 2);
        $response->assertViewHas('criticalFindings', 1);
        $response->assertViewHas('instancesCount', 1);
        $response->assertSee('SRV-PROD-01');
        $response->assertSee('Lancer un audit');
    }

    public function test_auditor_sees_compliance_dashboard(): void
    {
        $this->seed(RuleSeeder::class);
        $dba = User::factory()->create(['role' => 'dba', 'email' => 'dba@sqlaudit.local']);
        $auditor = User::factory()->create(['role' => 'auditeur']);
        $instance = Instance::create([
            'name' => 'SRV-PROD-01', 'host' => '10.0.0.15', 'port' => 1433,
            'environment' => 'production', 'username' => 'sqlaudit_collect', 'password' => 'x',
        ]);
        $r01 = Rule::where('code', 'R01')->value('id');
        $r04 = Rule::where('code', 'R04')->value('id');
        $make = fn ($run, $rule, $severity, $object) => $run->findings()->create([
            'rule_id' => $rule, 'severity' => $severity, 'object_name' => $object, 'evidence' => 'x', 'recommendation' => 'y',
        ]);

        // Audit précédent : app_compta + ancien_svc
        $previous = $instance->auditRuns()->create(['status' => 'completed', 'started_at' => now()->startOfMonth()->addHour()]);
        $make($previous, $r01, 'critique', 'app_compta');
        $make($previous, $r01, 'critique', 'ancien_svc');

        // Dernier audit : app_compta (inchangé) + jdupont (nouveau) + report_user ; ancien_svc est résolu
        $latest = $instance->auditRuns()->create(['status' => 'completed', 'started_at' => now()]);
        $appCompta = $make($latest, $r01, 'critique', 'app_compta');
        $make($latest, $r01, 'critique', 'jdupont');
        $reportUser = $make($latest, $r04, 'elevee', 'report_user');

        // Exception validée sur app_compta (masque le critique) + exception en attente sur report_user
        $appCompta->exceptions()->create(['requested_by' => $dba->id, 'reason' => 'Éditeur', 'requested_expiration' => now()->addDays(10),
            'status' => 'approved', 'reviewed_by' => $auditor->id, 'reviewed_at' => now()]);
        $reportUser->exceptions()->create(['requested_by' => $dba->id, 'reason' => 'Outil historique', 'requested_expiration' => now()->addMonths(2)]);

        $response = $this->actingAs($auditor)->get('/dashboard')->assertOk();

        $response->assertViewIs('dashboard-auditor');
        $response->assertSee('Suivi de conformité');
        $response->assertViewHas('criticalOpen', 1);        // jdupont (app_compta masqué par l'exception)
        $response->assertViewHas('pendingCount', 1);
        $response->assertViewHas('expiringSoon', 1);        // app_compta expire dans 10 jours
        $response->assertViewHas('resolvedThisMonth', 1);   // ancien_svc
        $response->assertSee('Demandée par dba@sqlaudit.local');
        $response->assertSee('+2');                         // jdupont et report_user sont nouveaux
        $response->assertSee('Auditeur / Contrôle');

        // Le DBA garde sa vue
        $this->actingAs($dba)->get('/dashboard')->assertViewIs('dashboard');
    }

    public function test_reader_sees_global_figures_only(): void
    {
        $this->seed(RuleSeeder::class);
        $reader = User::factory()->create(['role' => 'lecteur']);
        $instance = Instance::create([
            'name' => 'SRV-PROD-01', 'host' => '10.0.0.15', 'port' => 1433,
            'environment' => 'production', 'username' => 'sqlaudit_collect', 'password' => 'x',
        ]);
        $r01 = Rule::where('code', 'R01')->value('id');
        $make = fn ($run, $severity, $object) => $run->findings()->create([
            'rule_id' => $r01, 'severity' => $severity, 'object_name' => $object, 'evidence' => 'preuve-secrete', 'recommendation' => 'y',
        ]);

        // Mois dernier : 3 constats ; ce mois : 2 constats dont 1 critique
        $previous = $instance->auditRuns()->create(['status' => 'completed', 'started_at' => now()->startOfMonth()->subMonthNoOverflow()->addDays(2)]);
        foreach (['a', 'b', 'c'] as $object) {
            $make($previous, 'moyenne', $object);
        }
        $latest = $instance->auditRuns()->create(['status' => 'completed', 'started_at' => now()]);
        $make($latest, 'critique', 'app_compta');
        $make($latest, 'faible', 'report_user');

        $response = $this->actingAs($reader)->get('/dashboard')->assertOk();

        $response->assertViewIs('lecteur.dashboard');
        $response->assertSee('Synthèse de la sécurité SQL Server');
        $response->assertSee('Lecture seule');
        $response->assertViewHas('openFindings', 2);
        $response->assertViewHas('delta', -1);
        $response->assertSee('−1 par rapport au mois dernier');
        $response->assertSee('Élevé');                       // niveau : au moins 1 critique
        $response->assertDontSee('app_compta');              // jamais de nom de login
        $response->assertDontSee('preuve-secrete');          // jamais de preuve
        $response->assertSee('Rapports de synthèse');        // menu lecteur
        $response->assertDontSee('Audits et constats');

        // Les autres pages restent interdites au lecteur
        $this->actingAs($reader)->get('/audits')->assertForbidden();
        $this->actingAs($reader)->get('/rules')->assertForbidden();
        $this->actingAs($reader)->get('/reports')->assertForbidden();
    }
}
