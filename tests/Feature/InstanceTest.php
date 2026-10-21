<?php

namespace Tests\Feature;

use App\Models\Instance;
use App\Models\User;
use App\Services\InstanceConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class InstanceTest extends TestCase
{
    use RefreshDatabase;

    private array $form = [
        'name' => 'SRV-TEST-01',
        'host' => '10.0.0.15',
        'port' => 1433,
        'environment' => 'production',
        'username' => 'sqlaudit_collect',
        'password' => 'Secret!123',
    ];

    public function test_instances_page_is_reserved_to_dba(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dba']))->get('/instances')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'auditeur']))->get('/instances')->assertForbidden();
    }

    public function test_dba_adds_instance_with_encrypted_password(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dba']))
            ->post('/instances', $this->form)
            ->assertRedirect('/instances');

        $raw = DB::table('instances')->value('password');
        $this->assertNotSame('Secret!123', $raw);
        $this->assertSame('Secret!123', Instance::first()->password);
        $this->assertArrayNotHasKey('password', Instance::first()->toArray());
    }

    public function test_instance_form_is_validated(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dba']))
            ->post('/instances', ['port' => 70000, 'host' => "10.0.0.1'; DROP TABLE x"] + $this->form)
            ->assertSessionHasErrors(['port', 'host']);

        $this->assertSame(0, Instance::count());
    }

    public function test_unreachable_instance_gives_failed_audit(): void
    {
        // Connecteur simulé : la connexion échoue toujours
        $this->mock(InstanceConnector::class, function ($mock) {
            $mock->shouldReceive('connect')->andThrow(new \RuntimeException('SQLSTATE[08001]: TCP Provider: refusé'));
            $mock->shouldReceive('friendlyError')->andReturn('Instance injoignable');
            $mock->shouldReceive('disconnect');
        });
        $dba = User::factory()->create(['role' => 'dba']);
        $instance = Instance::create($this->form);

        $response = $this->actingAs($dba)->post("/instances/{$instance->id}/audit");

        $run = $instance->auditRuns()->first();
        $response->assertRedirect("/audits/{$run->id}");
        $this->assertSame('failed', $run->status);
        $this->assertSame('Instance injoignable', $run->error_message);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Audit échoué']);
    }

    public function test_auditor_cannot_launch_audit(): void
    {
        $instance = Instance::create($this->form);

        $this->actingAs(User::factory()->create(['role' => 'auditeur']))
            ->post("/instances/{$instance->id}/audit")
            ->assertForbidden();
    }

    public function test_connector_refuses_non_select_queries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InstanceConnector)->select(DB::connection(), 'UPDATE users SET role = ?', ['admin']);
    }
}
