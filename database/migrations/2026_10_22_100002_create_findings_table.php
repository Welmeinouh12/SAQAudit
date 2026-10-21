<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Constats produits par les règles lors d'un audit
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_run_id')->constrained('audit_runs')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('rules');
            $table->string('severity', 10);         // copiée depuis rules au moment de l'audit
            $table->string('object_name');          // ex. DOMAINE\jdupont, xp_cmdshell
            $table->text('evidence');               // preuve
            $table->text('recommendation');
            $table->timestamps();

            $table->index(['audit_run_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
