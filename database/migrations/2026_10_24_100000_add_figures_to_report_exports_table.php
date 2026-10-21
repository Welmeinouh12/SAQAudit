<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chiffres figés au moment de la génération (affichés sur les cartes du lecteur)
        Schema::table('report_exports', function (Blueprint $table) {
            $table->foreignId('instance_id')->nullable()->constrained('instances')->nullOnDelete(); // null = toutes les instances
            $table->unsignedInteger('findings_count')->nullable();
            $table->unsignedInteger('critical_count')->nullable();
            $table->unsignedInteger('instances_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('report_exports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instance_id');
            $table->dropColumn(['findings_count', 'critical_count', 'instances_count']);
        });
    }
};
