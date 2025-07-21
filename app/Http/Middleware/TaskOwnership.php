<?php

namespace App\Http\Middleware;

use App\Models\Task;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TaskOwnership
{
    /**
     * Handle an incoming request.
     *
     * Verify that the authenticated user owns the task being accessed.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the task ID from the route parameter
        $taskId = $request->route('task') ?? $request->route('id');
        
        // If no task ID is provided, continue (this might be a create operation)
        if (!$taskId) {
            return $next($request);
        }

        // Find the task
        $task = Task::find($taskId);
        
        // If task doesn't exist, return 404
        if (!$task) {
            return response()->json([
                'error' => [
                    'message' => 'Task not found.',
                    'code' => 'TASK_NOT_FOUND'
                ]
            ], 404);
        }

        // Check if the authenticated user owns the task
        if (Auth::id() !== $task->user_id) {
            return response()->json([
                'error' => [
                    'message' => 'You are not authorized to access this task.',
                    'code' => 'UNAUTHORIZED_TASK_ACCESS'
                ]
            ], 403);
        }

        // If parent_id is being set in the request, verify the user owns the parent task too
        if ($request->has('parent_id') && $request->input('parent_id')) {
            $parentTask = Task::find($request->input('parent_id'));
            
            if (!$parentTask) {
                return response()->json([
                    'error' => [
                        'message' => 'Parent task not found.',
                        'code' => 'PARENT_TASK_NOT_FOUND'
                    ]
                ], 404);
            }

            if (Auth::id() !== $parentTask->user_id) {
                return response()->json([
                    'error' => [
                        'message' => 'You are not authorized to assign this parent task.',
                        'code' => 'UNAUTHORIZED_PARENT_TASK_ACCESS'
                    ]
                ], 403);
            }
        }

        return $next($request);
    }
}
