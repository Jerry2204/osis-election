<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('election_settings', function (Blueprint $table) {
            $table->boolean('results_published')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('election_settings', function (Blueprint $table) {
            $table->dropColumn('results_published');
        });
    }
};
