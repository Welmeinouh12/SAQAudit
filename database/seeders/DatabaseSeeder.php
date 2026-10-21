<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin SQLAudit',
                'email' => 'admin@sqlaudit.local',
                'password' => Hash::make('Admin1234!*'),
                'role' => 'admin',
                'is_active' => true,
            ],
            [
                'name' => 'DBA SQLAudit',
                'email' => 'dba@sqlaudit.local',
                'password' => Hash::make('Dba1234!*'),
                'role' => 'dba',
                'is_active' => true,
            ],
            [
                'name' => 'Auditeur SQLAudit',
                'email' => 'auditeur@sqlaudit.local',
                'password' => Hash::make('Auditeur1234!*'),
                'role' => 'auditeur',
                'is_active' => true,
            ],
            [
                'name' => 'Lecteur SQLAudit',
                'email' => 'lecteur@sqlaudit.local',
                'password' => Hash::make('Lecteur1234!*'),
                'role' => 'lecteur',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->call(RuleSeeder::class);
    }
}
