import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { TaskFormData } from '@/types';
import { TaskForm } from '@/components';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const NewTaskPage: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (taskData: TaskFormData) => {
    setLoading(true);

    try {
      // TODO: Implement actual task creation
      console.log('Creating task:', taskData);
      
      // Simulate API call
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      // Navigate back to tasks page
      navigate('/tasks');
    } catch (error) {
      console.error('Failed to create task:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    navigate('/tasks');
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Create New Task</h1>
        <p className="text-muted-foreground mt-1">
          Fill in the details below to create a new task.
        </p>
      </div>

      <Card>
        <CardContent className="pt-6">
          <TaskForm
            onSubmit={handleSubmit}
            onCancel={handleCancel}
            loading={loading}
          />
        </CardContent>
      </Card>
    </div>
  );
};

export default NewTaskPage;