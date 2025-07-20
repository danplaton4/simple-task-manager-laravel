import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Task, TaskFormData } from '@/types';
import { useTask } from '@/contexts/TaskContext';
import { useTaskOperations } from '@/hooks/useTaskOperations';
import TaskForm from '@/components/tasks/TaskForm';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

const NewTaskPage: React.FC = () => {
  const { tasks, fetchTasks, error, clearError } = useTask();
  const { isCreating, createTaskWithLoading } = useTaskOperations();
  const navigate = useNavigate();

  // Load tasks for parent selection
  useEffect(() => {
    fetchTasks();
  }, [fetchTasks]);

  // Filter tasks that can be parents (exclude completed and cancelled tasks)
  const availableParents = tasks.filter(task => 
    task.status !== 'completed' && 
    task.status !== 'cancelled' &&
    !task.parent_id // Only top-level tasks can be parents
  );

  const handleSubmit = async (taskData: TaskFormData) => {
    try {
      const newTask = await createTaskWithLoading(taskData);
      
      if (newTask) {
        // Show success message (in a real app, you'd use a toast notification)
        alert('Task created successfully!');
        
        // Navigate back to tasks page
        navigate('/tasks');
      }
    } catch (error) {
      console.error('Failed to create task:', error);
      alert('Failed to create task. Please try again.');
    }
  };

  const handleCancel = () => {
    navigate('/tasks');
  };

  // Error handling
  if (error) {
    return (
      <div className="space-y-6">
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
          <p className="font-semibold">Error loading data:</p>
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

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <Button
          variant="outline"
          onClick={() => navigate('/tasks')}
          className="flex items-center gap-2"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Tasks
        </Button>
        <div>
          <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2">
            <Plus className="h-8 w-8 text-blue-600" />
            Create New Task
          </h1>
          <p className="text-gray-600 mt-1">
            Fill in the details below to create a new task. You can also make it a subtask of an existing task.
          </p>
        </div>
      </div>

      <TaskForm
        onSubmit={handleSubmit}
        onCancel={handleCancel}
        availableParents={availableParents}
        loading={isCreating}
        showCard={true}
      />
    </div>
  );
};

export default NewTaskPage;