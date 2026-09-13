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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('git_repo')->nullable();
            $table->enum('web_server', ['nginx', 'apache']);
            $table->string('domain')->nullable();
            $table->string('document_root')->nullable();
            $table->enum('status', ['pending', 'cloning', 'installing', 'generating', 'building', 'starting', 'deploying', 'active', 'stopped', 'failed'])->default('pending');
            $table->text('deployment_error')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
