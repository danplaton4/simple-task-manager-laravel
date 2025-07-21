import React, { useEffect } from 'react';
import { useTask } from '@/contexts/TaskContext';
import { useTaskOperations } from '@/hooks/useTaskOperations';
import { useTaskFilters } from '@/hooks/useTaskFilters';
import { TaskFormData } from '@/types';

/**
 * Example component demonstrating TaskService and TaskContext usage
 * This shows how to implement task operations with optimistic updates
 */
const TaskServiceExample: React.FC = () => {
  const {
    tasks,
    currentTask,
    isLoading,
    error,
    pagination,
    fetchTasks,
    fetchTask,
    clearError
  } = useTask();

  const {
    isCreating,
    isUpdating,
    isDeleting,
    isToggling,
    createTaskWithLoading,
    updateTaskWithLoading,
    deleteTaskWithLoading,
    toggleTaskStatusWithLoading
  } = useTaskOperations();

  const {
    activeFilters,
    setStatusFilter,
    setPriorityFilter,
    setSearchFilter,
    clearAllFilters,
    applyFilters,
    hasActiveFilters,
    currentPage,
    totalPages,
    goToPage,
    nextPage,
    previousPage,
    canGoNext,
    canGoPrevious
  } = useTaskFilters();

  // Load tasks on component mount
  useEffect(() => {
    fetchTasks();
  }, [fetchTasks]);

  // Example: Create a new task
  const handleCreateTask = async () => {
    const taskData: TaskFormData = {
      name: 'Example Task',
      description: 'This is an example task created via TaskService',
      status: 'pending',
      priority: 'medium',
      due_date: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0] // 7 days from now
    };

    const newTask = await createTaskWithLoading(taskData);
    if (newTask) {
      console.log('Task created successfully:', newTask);
    }
  };

  // Example: Update a task
  const handleUpdateTask = async (taskId: number) => {
    const updates = {
      name: 'Updated Task Name',
      priority: 'high' as const
    };

    const updatedTask = await updateTaskWithLoading(taskId, updates);
    if (updatedTask) {
      console.log('Task updated successfully:', updatedTask);
    }
  };

  // Example: Toggle task status
  const handleToggleStatus = async (taskId: number, currentStatus: string) => {
    const newStatus = currentStatus === 'pending' ? 'completed' : 'pending';
    const updatedTask = await toggleTaskStatusWithLoading(taskId, newStatus as any);
    if (updatedTask) {
      console.log('Task status toggled:', updatedTask);
    }
  };

  // Example: Delete a task
  const handleDeleteTask = async (taskId: number) => {
    const success = await deleteTaskWithLoading(taskId);
    if (success) {
      console.log('Task deleted successfully');
    }
  };

  // Example: Apply filters
  const handleApplyFilters = () => {
    setStatusFilter('pending');
    setPriorityFilter('high');
    setSearchFilter('important');
    applyFilters();
  };

  return (
    <div className="p-6 max-w-4xl mx-auto">
      <h1 className="text-2xl font-bold mb-6">Task Service Example</h1>

      {/* Error Display */}
      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <p>{error}</p>
          <button
            onClick={clearError}
            className="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600"
          >
            Clear Error
          </button>
        </div>
      )}

      {/* Action Buttons */}
      <div className="mb-6 space-x-2">
        <button
          onClick={handleCreateTask}
          disabled={isCreating}
          className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 disabled:opacity-50"
        >
          {isCreating ? 'Creating...' : 'Create Example Task'}
        </button>

        <button
          onClick={handleApplyFilters}
          className="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"
        >
          Apply Example Filters
        </button>

        {hasActiveFilters && (
          <button
            onClick={clearAllFilters}
            className="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
          >
            Clear Filters
          </button>
        )}
      </div>

      {/* Filter Display */}
      {hasActiveFilters && (
        <div className="mb-4 p-3 bg-gray-100 rounded">
          <h3 className="font-semibold mb-2">Active Filters:</h3>
          <div className="space-y-1 text-sm">
            {activeFilters.status && <p>Status: {activeFilters.status}</p>}
            {activeFilters.priority && <p>Priority: {activeFilters.priority}</p>}
            {activeFilters.search && <p>Search: {activeFilters.search}</p>}
          </div>
        </div>
      )}

      {/* Loading State */}
      {isLoading && (
        <div className="text-center py-4">
          <p>Loading tasks...</p>
        </div>
      )}

      {/* Tasks List */}
      {!isLoading && (
        <div className="space-y-4">
          <h2 className="text-xl font-semibold">
            Tasks ({pagination.total} total)
          </h2>

          {tasks.length === 0 ? (
            <p className="text-gray-500">No tasks found.</p>
          ) : (
            <div className="space-y-3">
              {tasks.map((task) => (
                <div
                  key={task.id}
                  className="border rounded-lg p-4 bg-white shadow-sm"
                >
                  <div className="flex justify-between items-start">
                    <div className="flex-1">
                      <h3 className="font-semibold">{task.name}</h3>
                      {task.description && (
                        <p className="text-gray-600 text-sm mt-1">
                          {task.description}
                        </p>
                      )}
                      <div className="flex space-x-4 mt-2 text-sm">
                        <span className={`px-2 py-1 rounded text-xs ${
                          task.status === 'completed' 
                            ? 'bg-green-100 text-green-800'
                            : task.status === 'in_progress'
                            ? 'bg-blue-100 text-blue-800'
                            : 'bg-gray-100 text-gray-800'
                        }`}>
                          {task.status}
                        </span>
                        <span className={`px-2 py-1 rounded text-xs ${
                          task.priority === 'urgent'
                            ? 'bg-red-100 text-red-800'
                            : task.priority === 'high'
                            ? 'bg-orange-100 text-orange-800'
                            : task.priority === 'medium'
                            ? 'bg-yellow-100 text-yellow-800'
                            : 'bg-gray-100 text-gray-800'
                        }`}>
                          {task.priority}
                        </span>
                      </div>
                    </div>

                    <div className="flex space-x-2 ml-4">
                      <button
                        onClick={() => handleToggleStatus(task.id, task.status)}
                        disabled={isToggling}
                        className="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 disabled:opacity-50"
                      >
                        {isToggling ? '...' : 'Toggle'}
                      </button>
                      <button
                        onClick={() => handleUpdateTask(task.id)}
                        disabled={isUpdating}
                        className="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 disabled:opacity-50"
                      >
                        {isUpdating ? '...' : 'Update'}
                      </button>
                      <button
                        onClick={() => handleDeleteTask(task.id)}
                        disabled={isDeleting}
                        className="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 disabled:opacity-50"
                      >
                        {isDeleting ? '...' : 'Delete'}
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex justify-center items-center space-x-2 mt-6">
              <button
                onClick={previousPage}
                disabled={!canGoPrevious}
                className="px-3 py-1 border rounded disabled:opacity-50 hover:bg-gray-50"
              >
                Previous
              </button>
              
              <span className="px-3 py-1">
                Page {currentPage} of {totalPages}
              </span>
              
              <button
                onClick={nextPage}
                disabled={!canGoNext}
                className="px-3 py-1 border rounded disabled:opacity-50 hover:bg-gray-50"
              >
                Next
              </button>
            </div>
          )}
        </div>
      )}

      {/* Current Task Display */}
      {currentTask && (
        <div className="mt-8 p-4 border rounded-lg bg-blue-50">
          <h3 className="font-semibold mb-2">Current Task:</h3>
          <p><strong>Name:</strong> {currentTask.name}</p>
          <p><strong>Status:</strong> {currentTask.status}</p>
          <p><strong>Priority:</strong> {currentTask.priority}</p>
          {currentTask.description && (
            <p><strong>Description:</strong> {currentTask.description}</p>
          )}
        </div>
      )}
    </div>
  );
};

export default TaskServiceExample;