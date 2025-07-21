<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add additional performance indexes for common query patterns
        Schema::table('tasks', function (Blueprint $table) {
            // Check if indexes don't already exist before creating them
            $indexes = collect(DB::select("SHOW INDEX FROM tasks"))->pluck('Key_name');
            
            // Composite index for user_id and status (common filtering combination)
            if (!$indexes->contains('tasks_user_status_index')) {
                $table->index(['user_id', 'status'], 'tasks_user_status_index');
            }
            
            // Composite index for user_id and priority (common filtering combination)
            if (!$indexes->contains('tasks_user_priority_index')) {
                $table->index(['user_id', 'priority'], 'tasks_user_priority_index');
            }
            
            // Index for due_date (for overdue task queries)
            if (!$indexes->contains('tasks_due_date_index')) {
                $table->index('due_date', 'tasks_due_date_index');
            }
            
            // Composite index for parent_id and status (for subtask queries)
            if (!$indexes->contains('tasks_parent_status_index')) {
                $table->index(['parent_id', 'status'], 'tasks_parent_status_index');
            }
            
            // Composite index for user_id, status, and due_date (for dashboard queries)
            if (!$indexes->contains('tasks_user_status_due_index')) {
                $table->index(['user_id', 'status', 'due_date'], 'tasks_user_status_due_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Drop the additional performance indexes
            try {
                $table->dropIndex('tasks_user_status_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            try {
                $table->dropIndex('tasks_user_priority_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            try {
                $table->dropIndex('tasks_due_date_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            try {
                $table->dropIndex('tasks_parent_status_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            try {
                $table->dropIndex('tasks_user_status_due_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
        });
        
        // Drop JSON indexes using raw SQL
        try {
            DB::statement("ALTER TABLE tasks DROP INDEX tasks_name_en_index");
            DB::statement("ALTER TABLE tasks DROP INDEX tasks_name_fr_index");
            DB::statement("ALTER TABLE tasks DROP INDEX tasks_name_de_index");
            DB::statement("ALTER TABLE tasks DROP INDEX tasks_description_en_index");
            DB::statement("ALTER TABLE tasks DROP INDEX tasks_description_fr_index");
            DB::statement("ALTER TABLE tasks DROP INDEX tasks_description_de_index");
        } catch (\Exception $e) {
            // Indexes might not exist, continue
        }
    }
};
