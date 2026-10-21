<?php

namespace Tests\Unit;

use App\Rules\Audit\R01SysadminOutsideWhitelist;
use App\Rules\Audit\R03SaAccount;
use App\Rules\Audit\R04PasswordPolicy;
use App\Rules\Audit\R07OrphanUsers;
use App\Rules\Audit\R16DangerousOptions;
use PHPUnit\Framework\TestCase;

class AuditRulesTest extends TestCase
{
    public function test_r01_ignores_whitelist_and_sa(): void
    {
        $findings = (new R01SysadminOutsideWhitelist)->evaluate([
            'whitelist' => ['DOMAINE\\Equipe-DBA'],
            'sysadmins' => [
                ['principal_id' => 1, 'name' => 'sa', 'type_desc' => 'SQL_LOGIN', 'is_disabled' => '0'],
                ['principal_id' => 260, 'name' => 'domaine\\equipe-dba', 'type_desc' => 'WINDOWS_GROUP', 'is_disabled' => '0'],
                ['principal_id' => 270, 'name' => 'test_sysadmin', 'type_desc' => 'SQL_LOGIN', 'is_disabled' => '0'],
            ],
        ]);

        $this->assertSame(['test_sysadmin'], array_column($findings, 'object'));
    }

    public function test_r03_detects_enabled_sa(): void
    {
        $rule = new R03SaAccount;

        $this->assertSame('Compte activé et non renommé', $rule->evaluate(['sa' => ['name' => 'sa', 'is_disabled' => '0']])[0]['evidence']);
        $this->assertSame([], $rule->evaluate(['sa' => ['name' => 'admin_renomme', 'is_disabled' => '1']]));
    }

    public function test_r04_detects_logins_without_policy(): void
    {
        $findings = (new R04PasswordPolicy)->evaluate(['sql_logins' => [
            ['name' => 'test_sans_policy', 'is_policy_checked' => '0', 'is_disabled' => '0'],
            ['name' => 'app_ok', 'is_policy_checked' => '1', 'is_disabled' => '0'],
            ['name' => '##MS_PolicyEventProcessingLogin##', 'is_policy_checked' => '0', 'is_disabled' => '1'],
        ]]);

        $this->assertSame(['test_sans_policy'], array_column($findings, 'object'));
    }

    public function test_r07_lists_orphans_with_database_name(): void
    {
        $findings = (new R07OrphanUsers)->evaluate(['orphans' => ['SQLAuditTest' => [['name' => 'test_orphelin']]]]);

        $this->assertSame(['SQLAuditTest.test_orphelin'], array_column($findings, 'object'));
    }

    public function test_r16_detects_enabled_options_only(): void
    {
        $findings = (new R16DangerousOptions)->evaluate(['configurations' => [
            ['name' => 'xp_cmdshell', 'value_in_use' => 1],
            ['name' => 'clr enabled', 'value_in_use' => 0],
            ['name' => 'Ole Automation Procedures', 'value_in_use' => '1'],
        ]]);

        $this->assertSame(['xp_cmdshell', 'Ole Automation Procedures'], array_column($findings, 'object'));
    }
}
