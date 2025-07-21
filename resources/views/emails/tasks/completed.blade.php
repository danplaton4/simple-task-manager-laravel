<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('messages.email.task_completed.subject', ['task_name' => $task->getLocalizedName($locale)], $locale) }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #d4edda;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .header h1 {
            color: #155724;
            margin: 0;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .task-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .task-detail-item {
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .task-detail-item:last-child {
            border-bottom: none;
        }
        .task-detail-label {
            font-weight: bold;
            color: #495057;
        }
        .task-detail-value {
            color: #6c757d;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
        }
        .button:hover {
            background-color: #218838;
        }
        .footer {
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .priority-high, .priority-urgent {
            color: #dc3545;
            font-weight: bold;
        }
        .priority-medium {
            color: #fd7e14;
            font-weight: bold;
        }
        .priority-low {
            color: #28a745;
        }
        .status-completed {
            color: #28a745;
            font-weight: bold;
        }
        .completion-badge {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
            margin: 10px 0;
        }
        .celebration {
            text-align: center;
            font-size: 24px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('messages.email.task_completed.subject', ['task_name' => $task->getLocalizedName($locale)], $locale) }}</h1>
        <div class="celebration">🎉 🎊 ✅</div>
    </div>

    <div class="content">
        <p>{{ __('messages.email.task_completed.greeting', ['user_name' => $user->name], $locale) }}</p>
        
        <p>{{ __('messages.email.task_completed.intro', [], $locale) }}</p>

        <div class="completion-badge">
            {{ __('messages.task.status.completed', [], $locale) }}
        </div>

        <div class="task-details">
            <h3>{{ __('messages.email.task_completed.task_details', [], $locale) }}</h3>
            
            <div class="task-detail-item">
                <span class="task-detail-label">{{ __('messages.email.task_completed.name', [], $locale) }}</span>
                <div class="task-detail-value">{{ $task->getLocalizedName($locale) }}</div>
            </div>

            @if($task->getLocalizedDescription($locale))
            <div class="task-detail-item">
                <span class="task-detail-label">{{ __('messages.email.task_completed.description', [], $locale) }}</span>
                <div class="task-detail-value">{{ $task->getLocalizedDescription($locale) }}</div>
            </div>
            @else
            <div class="task-detail-item">
                <span class="task-detail-label">{{ __('messages.email.task_completed.description', [], $locale) }}</span>
                <div class="task-detail-value">{{ __('messages.email.common.no_description', [], $locale) }}</div>
            </div>
            @endif

            <div class="task-detail-item">
                <span class="task-detail-label">{{ __('messages.email.task_completed.completed_at', [], $locale) }}</span>
                <div class="task-detail-value">{{ $task->updated_at->setTimezone($user->getTimezone())->format('Y-m-d H:i T') }}</div>
            </div>

            <div class="task-detail-item">
                <span class="task-detail-label">{{ __('messages.email.task_completed.priority', [], $locale) }}</span>
                <div class="task-detail-value priority-{{ $task->priority }}">
                    {{ __('messages.task.priority.' . $task->priority, [], $locale) }}
                </div>
            </div>

            @if($task->due_date)
            <div class="task-detail-item">
                <span class="task-detail-label">{{ __('messages.email.task_completed.due_date', [], $locale) }}</span>
                <div class="task-detail-value">
                    {{ $task->due_date->setTimezone($user->getTimezone())->format('Y-m-d H:i T') }}
                    @if($task->due_date->isPast())
                        <span style="color: #dc3545;">(Was overdue)</span>
                    @else
                        <span style="color: #28a745;">(Completed on time)</span>
                    @endif
                </div>
            </div>
            @else
            <div class="task-detail-item">
                <span class="task-detail-label">{{ __('messages.email.task_completed.due_date', [], $locale) }}</span>
                <div class="task-detail-value">{{ __('messages.email.common.no_due_date', [], $locale) }}</div>
            </div>
            @endif

            @if($task->parent)
            <div class="task-detail-item">
                <span class="task-detail-label">{{ __('messages.email.task_completed.parent_task', [], $locale) }}</span>
                <div class="task-detail-value">{{ $task->parent->getLocalizedName($locale) }}</div>
            </div>
            @else
            <div class="task-detail-item">
                <span class="task-detail-label">{{ __('messages.email.task_completed.parent_task', [], $locale) }}</span>
                <div class="task-detail-value">{{ __('messages.email.common.no_parent_task', [], $locale) }}</div>
            </div>
            @endif

            @if($task->hasSubtasks())
            <div class="task-detail-item">
                <span class="task-detail-label">Subtasks Completion:</span>
                <div class="task-detail-value">{{ $task->getCompletionPercentage() }}% ({{ $task->subtasks->where('status', 'completed')->count() }}/{{ $task->subtasks->count() }} subtasks completed)</div>
            </div>
            @endif
        </div>

        @if(isset($taskUrl))
        <a href="{{ $taskUrl }}" class="button">{{ __('messages.email.task_completed.view_task', [], $locale) }}</a>
        @endif
    </div>

    <div class="footer">
        <p>{{ __('messages.email.task_completed.footer', [], $locale) }}</p>
        <p style="font-size: 12px; color: #6c757d; margin-top: 20px;">
            <a href="{{ config('app.url') }}/unsubscribe?token={{ App\Http\Controllers\UnsubscribeController::generateUnsubscribeToken($user->id) }}" 
               style="color: #6c757d; text-decoration: underline;">
                Unsubscribe from these notifications
            </a>
        </p>
    </div>
</body>
</html>