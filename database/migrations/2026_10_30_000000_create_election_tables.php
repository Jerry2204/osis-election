<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('nis', 32)->unique();
            $table->string('name', 120);
            $table->string('class_name', 60);
        });
        Schema::create('candidates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedSmallInteger('number')->unique();
            $table->string('name', 120);
            $table->string('photo_path');
            $table->text('vision');
            $table->text('mission');
        });
        Schema::create('participations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('student_id')->primary();
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
        });
        Schema::create('votes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->uuid('id')->primary();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
        });
        Schema::create('election_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedTinyInteger('id')->primary();
            $table->enum('status', ['preparation', 'open', 'closed'])->default('preparation');
        });
        DB::table('election_settings')->insert(['id' => 1, 'status' => 'preparation']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE election_settings ADD CONSTRAINT singleton_election CHECK (id = 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
        Schema::dropIfExists('participations');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('students');
        Schema::dropIfExists('election_settings');
    }
};
