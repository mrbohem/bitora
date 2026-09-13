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
        Schema::table('projects', function (Blueprint $table) {
            // Remove old web_server column (nginx/apache)
            $table->dropColumn('web_server');

            // Add Docker-specific columns
            $table->string('php_version')->default('8.4')->after('git_repo');
            $table->string('git_branch')->default('main')->after('git_repo');
            $table->enum('git_auth_type', ['ssh', 'token', 'none'])->default('none')->after('git_repo');
            $table->text('git_credentials')->nullable()->after('git_auth_type'); // encrypted SSH key or token

            // Docker container info
            $table->string('container_id')->nullable()->after('document_root');
            $table->string('docker_network')->nullable()->after('container_id');

            // Features toggles
            $table->boolean('reverb_enabled')->default(false)->after('docker_network');
            $table->boolean('octane_enabled')->default(false)->after('reverb_enabled');
            $table->enum('octane_server', ['swoole', 'roadrunner'])->nullable()->after('octane_enabled');
            $table->boolean('queue_enabled')->default(false)->after('octane_server');
            $table->string('queue_connection')->nullable()->after('queue_enabled');
            $table->integer('queue_workers')->default(1)->after('queue_connection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('web_server', ['nginx', 'apache'])->after('git_repo');

            $table->dropColumn([
                'php_version',
                'git_branch',
                'git_auth_type',
                'git_credentials',
                'container_id',
                'docker_network',
                'reverb_enabled',
                'octane_enabled',
                'octane_server',
                'queue_enabled',
                'queue_connection',
                'queue_workers',
            ]);
        });
    }
};
