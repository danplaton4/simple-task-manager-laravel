import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Task } from '@/types';
import { useTask } from '@/contexts/TaskContext';
import { useTaskOperations } from '@/hooks/useTaskOperations';
import DraggableTaskList from '@/components/tasks/DraggableTaskList';
import SubtaskManager from '@/components/tasks/SubtaskManager';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Plus, ArrowLeft } from 'lucide-react';


const TasksPage: React.FC = () => {
  const { 
    tasks, 
    isLoading, 
    error, 
    fetchTasks, 
    fetchSubtasks,
    createSubtask,
    reorderTasks,
    clearError 
  } = useTask();
  
  const {
    updateTaskWithLoading,
    deleteTaskWithLoading,
    toggleTaskStatusWithLoading
  } = useTaskOperations();
  
  const [selectedTaskForSubtasks, setSelectedTaskForSubtasks] = useState<Task | null>(null);

  // Load tasks on component mount
  useEffect(() => {
    fetchTasks();
  }, [fetchTasks]);

  const handleEdit = (task: Task) => {
    console.log('Edit task:', task);
    // TODO: Navigate to edit page or open modal
  };

  const handleDelete = async (taskId: number) => {
    if (confirm('Are you sure you want to delete this task?')) {
      await deleteTaskWithLoading(taskId);
    }
  };

  const handleToggleStatus = async (taskId: number) => {
    const task = tasks.find(t => t.id === taskId);
    if (task) {
      const newStatus = task.status === 'completed' ? 'pending' : 'completed';
      await toggleTaskStatusWithLoading(taskId, newStatus);
    }
  };

  const handleViewSubtasks = async (task: Task) => {
    setSelectedTaskForSubtasks(task);
    // Load subtasks if not already loaded
    if (!task.subtasks || task.subtasks.length === 0) {
      await fetchSubtasks(task.id);
    }
  };

  const handleAddSubtask = async (subtaskData: any) => {
    if (!selectedTaskForSubtasks) return;
    
    try {
      await createSubtask(selectedTaskForSubtasks.id, subtaskData);
    } catch (error) {
      console.error('Failed to create subtask:', error);
    }
  };

  const handleUpdateSubtask = async (subtaskId: number, subtaskData: any) => {
    try {
      await updateTaskWithLoading(subtaskId, subtaskData);
    } catch (error) {
      console.error('Failed to update subtask:', error);
    }
  };

  const handleDeleteSubtask = async (subtaskId: number) => {
    if (confirm('Are you sure you want to delete this subtask?')) {
      try {
        await deleteTaskWithLoading(subtaskId);
      } catch (error) {
        console.error('Failed to delete subtask:', error);
      }
    }
  };

  const handleToggleSubtaskStatus = async (subtaskId: number) => {
    const parentTask = tasks.find(t => t.id === selectedTaskForSubtasks?.id);
    const subtask = parentTask?.subtasks?.find(st => st.id === subtaskId);
    
    if (subtask) {
      const newStatus = subtask.status === 'completed' ? 'pending' : 'completed';
      try {
        await toggleTaskStatusWithLoading(subtaskId, newStatus);
      } catch (error) {
        console.error('Failed to toggle subtask status:', error);
      }
    }
  };

  const handleReorderTasks = async (taskIds: number[]) => {
    try {
      await reorderTasks(taskIds);
    } catch (error) {
      console.error('Failed to reorder tasks:', error);
    }
  };

  // Error handling
  if (error) {
    return (
      <div className="space-y-6">
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
          <p className="font-semibold">Error loading tasks:</p>
          <p>{error}</p>
          <div className="mt-2 space-x-2">
            <Button onClick={clearError} variant="outline" size="sm">
              Dismiss
            </Button>
            <Button onClick={() => fetchTasks()} variant="outline" size="sm">
              Retry
            </Button>
          </div>
        </div>
      </div>
    );
  }

  if (selectedTaskForSubtasks) {
    const currentTask = tasks.find(t => t.id === selectedTaskForSubtasks.id);
    if (!currentTask) {
      setSelectedTaskForSubtasks(null);
      return null;
    }

    return (
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <Button
            variant="outline"
            onClick={() => setSelectedTaskForSubtasks(null)}
            className="flex items-center gap-2"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to Tasks
          </Button>
          <h1 className="text-2xl font-bold text-gray-900">Subtask Management</h1>
        </div>

        <SubtaskManager
          parentTask={currentTask}
          subtasks={currentTask.subtasks || []}
          onAddSubtask={handleAddSubtask}
          onUpdateSubtask={handleUpdateSubtask}
          onDeleteSubtask={handleDeleteSubtask}
          onToggleSubtaskStatus={handleToggleSubtaskStatus}
          loading={isLoading}
          allowNesting={false}
        />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold">Task Management</h1>
          <p className="text-muted-foreground mt-1 text-sm sm:text-base">
            Organize and track your tasks with hierarchical subtask support
          </p>
        </div>
        <Link to="/tasks/new">
          <Button className="flex items-center gap-2 w-full sm:w-auto">
            <Plus className="h-4 w-4" />
            <span className="sm:inline">New Task</span>
          </Button>
        </Link>
      </div>

      {/* Loading State */}
      {isLoading && tasks.length === 0 && (
        <Card>
          <CardContent className="flex items-center justify-center py-8">
            <div className="text-center">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
              <p className="text-gray-600">Loading tasks...</p>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Task List with Enhanced Features */}
      <DraggableTaskList
        tasks={tasks}
        onEdit={handleEdit}
        onDelete={handleDelete}
        onToggleStatus={handleToggleStatus}
        onViewSubtasks={handleViewSubtasks}
        onReorder={handleReorderTasks}
        loading={isLoading}
        showFilters={true}
        showSearch={true}
        viewMode="list"
        enableDragAndDrop={true}
      />
    </div>
  );
};

export default TasksPage;