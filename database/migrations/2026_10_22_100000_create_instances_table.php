<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Instances SQL Server à auditer
        Schema::create('instances', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();          // ex. SRV-PROD-01
            $table->string('host');
            $table->unsignedInteger('port')->default(1433);
            $table->string('environment', 20);              // production, recette, developpement
            $table->string('username', 128);                // compte de collecte
            $table->text('password');                       // chiffré par Laravel (cast « encrypted »)
            $table->string('last_test_status', 10)->nullable(); // null = non testée, ok, failed
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->text('sql_version')->nullable();        // résultat de SELECT @@VERSION
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instances');
    }
};
