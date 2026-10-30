<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election_settings', function (Blueprint $table) {
            $table->string('school_name', 120)->default('');
            $table->string('election_title', 160)->default('');
            $table->string('osis_period', 40)->default('');
            $table->string('logo_path')->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('report_place', 120)->default('');
            $table->string('committee_chair', 120)->default('');
            $table->string('osis_advisor', 120)->default('');
            $table->string('principal_name', 120)->default('');
        });
    }

    public function down(): void
    {
        Schema::table('election_settings', function (Blueprint $table) {
            $table->dropColumn([
                'school_name', 'election_title', 'osis_period', 'logo_path',
                'opens_at', 'closes_at', 'opened_at', 'closed_at',
                'report_place', 'committee_chair', 'osis_advisor', 'principal_name',
            ]);
        });
    }
};
