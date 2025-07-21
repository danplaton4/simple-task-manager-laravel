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
        Schema::table('tasks', function (Blueprint $table) {
            // Composite index for user_id and status (common filtering combination)
            $table->index(['user_id', 'status'], 'tasks_user_status_index');
            
            // Composite index for user_id and priority (common filtering combination)
            $table->index(['user_id', 'priority'], 'tasks_user_priority_index');
            
            // Index for due_date (for overdue task queries)
            $table->index('due_date', 'tasks_due_date_index');
            
            // Composite index for parent_id and status (for subtask queries)
            $table->index(['parent_id', 'status'], 'tasks_parent_status_index');
            
            // Composite index for user_id, status, and due_date (for dashboard queries)
            $table->index(['user_id', 'status', 'due_date'], 'tasks_user_status_due_index');
            
            // Index for created_at (for recent tasks queries)
            $table->index('created_at', 'tasks_created_at_index');
            
            // Index for updated_at (for recently modified tasks)
            $table->index('updated_at', 'tasks_updated_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_status_index');
            $table->dropIndex('tasks_user_priority_index');
            $table->dropIndex('tasks_due_date_index');
            $table->dropIndex('tasks_parent_status_index');
            $table->dropIndex('tasks_user_status_due_index');
            $table->dropIndex('tasks_created_at_index');
            $table->dropIndex('tasks_updated_at_index');
        });
    }
};
