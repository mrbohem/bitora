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
        Schema::create('supervisor_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('command');
            $table->integer('processes')->default(1);
            $table->enum('status', ['stopped', 'running', 'failed'])->default('stopped');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisor_workers');
    }
};
