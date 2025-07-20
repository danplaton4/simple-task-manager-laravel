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
];