<?php

use App\Livewire\Projects\Deploy;
use App\Livewire\Projects\Index;
use App\Livewire\Projects\Show;
use App\Livewire\Settings\PanelSettings;
use App\Models\Project;
use App\Services\ContainerManagementService;
use App\Services\DeploymentService;
use App\Services\GitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Projects
    Route::get('projects', Index::class)->name('projects.index');
    Route::get('projects/deploy', Deploy::class)->name('projects.deploy');
    Route::post('projects/{project}/github/webhook', function (Project $project, Request $request) {
        $gitBranch = $project->git_branch ?? 'main';
        $ref = $request->input('ref');

        if (! $project->github_auto_update || blank($project->git_repo) || $ref !== 'refs/heads/'.$gitBranch) {
            return response()->noContent();
        }

        try {
            $projectPath = app(DeploymentService::class)->getProjectPath($project);
            app(GitService::class)->pullRepository($project, $projectPath);
            app(ContainerManagementService::class)->restartContainer($project, $projectPath);
            $project->update(['status' => 'active']);

            return response()->noContent();
        } catch (\Throwable $e) {
            $project->update(['status' => 'failed', 'deployment_error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 200);
        }
    })->name('projects.github.webhook');
    Route::get('projects/{project}', Show::class)->name('projects.show');

    // Panel Settings
    Route::get('settings/panel', PanelSettings::class)->name('settings.panel');
});

require __DIR__.'/settings.php';
