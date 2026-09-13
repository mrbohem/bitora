<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;

class ActivityLogService
{
    /**
     * Log an activity
     */
    public function log(?Project $project, User $user, string $action, ?string $description = null, ?array $metadata = null): ActivityLog
    {
        return ActivityLog::create([
            'project_id' => $project?->id,
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get recent activities for a project
     */
    public function getProjectActivities(Project $project, int $limit = 50)
    {
        return ActivityLog::where('project_id', $project->id)
            ->with('user')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activities for a user
     */
    public function getUserActivities(User $user, int $limit = 50)
    {
        return ActivityLog::where('user_id', $user->id)
            ->with(['project', 'user'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get system-wide recent activities
     */
    public function getRecentActivities(int $limit = 100)
    {
        return ActivityLog::with(['project', 'user'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
