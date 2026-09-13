<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('schedule');
            $table->text('command');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
