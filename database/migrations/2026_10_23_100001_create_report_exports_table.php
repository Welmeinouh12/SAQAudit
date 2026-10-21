<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rapports générés (fichiers stockés dans storage/app/reports, jamais publics)
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 10);              // detail, synthese
            $table->string('scope');                 // ex. « SRV-PROD-01 · #12 » ou « Toutes les instances »
            $table->string('format', 5);             // pdf, xlsx, csv
            $table->string('file_path');             // chemin relatif dans storage/app
            $table->string('fingerprint', 64)->nullable(); // empreinte SHA-256 de l'audit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
