import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Task, TaskFilters } from '@/types';
import { TaskList } from '@/components';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent } from '@/components/ui/card';

const TasksPage: React.FC = () => {
  const [tasks] = useState<Task[]>([]); // TODO: Replace with actual data
  const [loading] = useState(false);
  const [filters, setFilters] = useState<TaskFilters>({});

  const handleFilterChange = (field: keyof TaskFilters, value: string) => {
    setFilters(prev => ({
      ...prev,
      [field]: value || undefined
    }));
  };

  const handleEdit = (task: Task) => {
    // TODO: Navigate to edit page or open modal
    console.log('Edit task:', task);
  };

  const handleDelete = (taskId: number) => {
    // TODO: Implement delete functionality
    console.log('Delete task:', taskId);
  };

  const handleToggleStatus = (taskId: number) => {
    // TODO: Implement status toggle
    console.log('Toggle status for task:', taskId);
  };



  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold text-gray-900">Tasks</h1>
        <Link to="/tasks/new">
          <Button>New Task</Button>
        </Link>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <Input
              placeholder="Search tasks..."
              value={filters.search || ''}
              onChange={(e) => handleFilterChange('search', e.target.value)}
            />
            
            <Select
              value={filters.status || ''}
              onValueChange={(value) => handleFilterChange('status', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Filter by status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">All Statuses</SelectItem>
                <SelectItem value="pending">Pending</SelectItem>
                <SelectItem value="in_progress">In Progress</SelectItem>
                <SelectItem value="completed">Completed</SelectItem>
                <SelectItem value="cancelled">Cancelled</SelectItem>
              </SelectContent>
            </Select>
            
            <Select
              value={filters.priority || ''}
              onValueChange={(value) => handleFilterChange('priority', value)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Filter by priority" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">All Priorities</SelectItem>
                <SelectItem value="low">Low</SelectItem>
                <SelectItem value="medium">Medium</SelectItem>
                <SelectItem value="high">High</SelectItem>
                <SelectItem value="urgent">Urgent</SelectItem>
              </SelectContent>
            </Select>
            
            <Button
              variant="outline"
              onClick={() => setFilters({})}
            >
              Clear Filters
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Task List */}
      <TaskList
        tasks={tasks}
        onEdit={handleEdit}
        onDelete={handleDelete}
        onToggleStatus={handleToggleStatus}
        loading={loading}
      />
    </div>
  );
};

export default TasksPage;