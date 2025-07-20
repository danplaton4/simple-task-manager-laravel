<?php

return [
    'task' => [
        'created' => 'Task created successfully',
        'updated' => 'Task updated successfully',
        'deleted' => 'Task deleted successfully',
        'restored' => 'Task restored successfully',
        'not_found' => 'Task not found',
        'unauthorized' => 'You are not authorized to access this task',
        'status' => [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
        'priority' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ],
        'validation' => [
            'name_required' => 'Task name is required',
            'name_max' => 'Task name cannot exceed 255 characters',
            'description_max' => 'Task description cannot exceed 1000 characters',
            'status_invalid' => 'Invalid task status',
            'priority_invalid' => 'Invalid task priority',
            'due_date_future' => 'Due date must be in the future',
            'parent_exists' => 'Parent task does not exist',
            'circular_reference' => 'Cannot create circular task references',
        ],
    ],
    'auth' => [
        'login_success' => 'Login successful',
        'login_failed' => 'Invalid credentials',
        'logout_success' => 'Logout successful',
        'register_success' => 'Registration successful',
        'token_refresh_success' => 'Token refreshed successfully',
        'unauthorized' => 'Unauthorized access',
        'validation' => [
            'email_required' => 'Email is required',
            'email_invalid' => 'Invalid email format',
            'password_required' => 'Password is required',
            'password_min' => 'Password must be at least 8 characters',
            'name_required' => 'Name is required',
        ],
    ],
    'general' => [
        'success' => 'Operation completed successfully',
        'error' => 'An error occurred',
        'validation_failed' => 'Validation failed',
        'server_error' => 'Internal server error',
        'not_found' => 'Resource not found',
    ],
    'email' => [
        'task_created' => [
            'subject' => 'New Task Created: :task_name',
            'greeting' => 'Hello :user_name,',
            'intro' => 'A new task has been created in your task management system.',
            'task_details' => 'Task Details:',
            'name' => 'Name: :name',
            'description' => 'Description: :description',
            'status' => 'Status: :status',
            'priority' => 'Priority: :priority',
            'due_date' => 'Due Date: :due_date',
            'parent_task' => 'Parent Task: :parent_name',
            'view_task' => 'View Task',
            'footer' => 'Thank you for using our task management system!',
        ],
        'task_updated' => [
            'subject' => 'Task Updated: :task_name',
            'greeting' => 'Hello :user_name,',
            'intro' => 'One of your tasks has been updated.',
            'task_details' => 'Updated Task Details:',
            'name' => 'Name: :name',
            'description' => 'Description: :description',
            'status' => 'Status: :status',
            'priority' => 'Priority: :priority',
            'due_date' => 'Due Date: :due_date',
            'parent_task' => 'Parent Task: :parent_name',
            'view_task' => 'View Task',
            'footer' => 'Thank you for using our task management system!',
        ],
        'task_completed' => [
            'subject' => 'Task Completed: :task_name',
            'greeting' => 'Hello :user_name,',
            'intro' => 'Congratulations! You have completed a task.',
            'task_details' => 'Completed Task Details:',
            'name' => 'Name: :name',
            'description' => 'Description: :description',
            'completed_at' => 'Completed At: :completed_at',
            'priority' => 'Priority: :priority',
            'due_date' => 'Due Date: :due_date',
            'parent_task' => 'Parent Task: :parent_name',
            'view_task' => 'View Task',
            'footer' => 'Keep up the great work!',
        ],
        'task_deleted' => [
            'subject' => 'Task Deleted: :task_name',
            'content' => 'Hello :user_name,

Your task ":task_name" has been deleted from your task management system.

If this was done in error, please contact support.

Best regards,
Task Management Team',
        ],
        'task_due_soon' => [
            'subject' => 'Task Due Soon: :task_name',
            'content' => 'Hello :user_name,

This is a reminder that your task ":task_name" is due soon.

Due Date: :due_date

Please make sure to complete it on time.

Best regards,
Task Management Team',
        ],
        'task_overdue' => [
            'subject' => 'Task Overdue: :task_name',
            'content' => 'Hello :user_name,

Your task ":task_name" is now overdue.

Original Due Date: :due_date
Days Overdue: :days_overdue

Please complete this task as soon as possible.

Best regards,
Task Management Team',
        ],
        'daily_digest' => [
            'subject' => 'Daily Task Summary - :date',
            'greeting' => 'Hello :user_name,',
            'intro' => 'Here\'s your daily task summary for :period:',
            'summary' => 'Summary:',
            'created_tasks' => ':count new tasks created',
            'completed_tasks' => ':count tasks completed',
            'total_active' => ':count active tasks remaining',
            'due_soon_section' => 'Tasks due soon:',
            'overdue_section' => 'Overdue tasks (action required):',
            'completed_section' => 'Recently completed tasks:',
            'footer' => 'View all your tasks at:',
            'unsubscribe' => 'To unsubscribe from daily digests, update your notification preferences in your account settings.',
        ],
        'weekly_digest' => [
            'subject' => 'Weekly Task Summary - :date',
            'greeting' => 'Hello :user_name,',
            'intro' => 'Here\'s your weekly task summary for :period:',
            'summary' => 'Summary:',
            'created_tasks' => ':count new tasks created',
            'completed_tasks' => ':count tasks completed',
            'total_active' => ':count active tasks remaining',
            'due_soon_section' => 'Tasks due soon:',
            'overdue_section' => 'Overdue tasks (action required):',
            'completed_section' => 'Recently completed tasks:',
            'footer' => 'View all your tasks at:',
            'unsubscribe' => 'To unsubscribe from weekly digests, update your notification preferences in your account settings.',
        ],
        'common' => [
            'no_description' => 'No description provided',
            'no_due_date' => 'No due date set',
            'no_parent_task' => 'This is a root task',
        ],
    ],
];