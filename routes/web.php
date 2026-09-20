<?php

use App\Http\Controllers\ProjectTerminalController;
use App\Livewire\Projects\Deploy;
use App\Livewire\Projects\Index;
use App\Livewire\Projects\Show;
use App\Livewire\Settings\PanelSettings;
use App\Models\Project;
use App\Services\ProjectSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Projects
    Route::get('projects', Index::class)->name('projects.index');
    Route::get('projects/deploy', Deploy::class)->name('projects.deploy');
    Route::post('projects/{project}/github/webhook', function (
        Project $project,
        Request $request,
        ProjectSyncService $projectSyncService,
    ) {
        $gitBranch = $project->git_branch ?? 'main';
        $ref = $request->input('ref');

        if (! $project->github_auto_update || blank($project->git_repo) || $ref !== 'refs/heads/'.$gitBranch) {
            return response()->noContent();
        }

        try {
            $projectSyncService->sync($project);

            return response()->noContent();
        } catch (Throwable $e) {
            $project->update(['status' => 'failed', 'deployment_error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 200);
        }
    })->name('projects.github.webhook');
    Route::get('projects/{project}', Show::class)->name('projects.show');
    Route::post('projects/{project}/terminal', [ProjectTerminalController::class, 'start'])->name('projects.terminal.start');
    Route::post('projects/{project}/terminal/{token}/input', [ProjectTerminalController::class, 'write'])->name('projects.terminal.input');
    Route::get('projects/{project}/terminal/{token}/output', [ProjectTerminalController::class, 'output'])->name('projects.terminal.output');
    Route::delete('projects/{project}/terminal/{token}', [ProjectTerminalController::class, 'close'])->name('projects.terminal.close');

    // Panel Settings
    Route::get('settings/panel', PanelSettings::class)->name('settings.panel');
});

require __DIR__.'/settings.php';
