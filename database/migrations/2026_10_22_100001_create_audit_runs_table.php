<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une exécution d'audit sur une instance
        Schema::create('audit_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('instances');
            $table->foreignId('user_id')->nullable()->constrained('users'); // qui a lancé l'audit
            $table->string('status', 10)->default('running');               // running, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('logins_count')->nullable();
            $table->unsignedInteger('databases_count')->nullable();
            $table->string('fingerprint', 64)->nullable();                  // SHA-256 des données collectées
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['instance_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_runs');
    }
};
