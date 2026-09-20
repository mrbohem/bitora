<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ContainerTerminalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectTerminalController extends Controller
{
    public function start(Project $project, ContainerTerminalService $terminal): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json($terminal->start($project));
    }

    public function write(
        Project $project,
        string $token,
        Request $request,
        ContainerTerminalService $terminal
    ): JsonResponse {
        $this->authorizeProject($project);

        $encodedInput = $request->json('input_base64');

        abort_unless(is_string($encodedInput), 422, 'Invalid terminal input.');

        $input = base64_decode($encodedInput, true);

        abort_unless(is_string($input) && Str::length($input) <= 4096, 422, 'Invalid terminal input.');

        $terminal->write($project, $token, $input);

        return response()->json(['ok' => true]);
    }

    public function output(
        Project $project,
        string $token,
        Request $request,
        ContainerTerminalService $terminal
    ): JsonResponse {
        $this->authorizeProject($project);
        $offset = $request->integer('offset', 0);

        return response()->json($terminal->output($project, $token, $offset, true));
    }

    public function close(Project $project, string $token, ContainerTerminalService $terminal): JsonResponse
    {
        $this->authorizeProject($project);
        $terminal->close($project, $token);

        return response()->json(['ok' => true]);
    }

    private function authorizeProject(Project $project): void
    {
        abort_unless($project->user_id === auth()->id(), 403);
    }
}
