<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exceptions (acceptations de risque) : le DBA propose, l'auditeur valide ou refuse
        Schema::create('exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained('findings')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');         // DBA qui propose
            $table->text('reason');                                          // motif
            $table->date('requested_expiration');
            $table->string('status', 10)->default('pending');                // pending, approved, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users'); // auditeur qui décide
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();                      // obligatoire en cas de refus
            $table->timestamps();

            $table->index(['status', 'requested_expiration']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exceptions');
    }
};
