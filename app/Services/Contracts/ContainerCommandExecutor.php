<?php

namespace App\Services\Contracts;

use App\Models\Project;

interface ContainerCommandExecutor
{
    public function execCommand(Project $project, string $command): string;
}
