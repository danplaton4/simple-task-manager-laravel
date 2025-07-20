import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { Task, TaskFormData } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { 
  Form, 
  FormControl, 
  FormField, 
  FormItem, 
  FormLabel, 
  FormMessage,
  FormDescription 
} from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, Clock, Flag, User, AlertCircle, Save, X } from 'lucide-react';

interface TaskFormProps {
  task?: Task;
  onSubmit: (taskData: TaskFormData) => void;
  onCancel: () => void;
  availableParents?: Task[];
  loading?: boolean;
  showCard?: boolean;
}

const TaskForm: React.FC<TaskFormProps> = ({
  task,
  onSubmit,
  onCancel,
  availableParents = [],
  loading = false,
  showCard = false
}) => {
  const form = useForm<TaskFormData>({
    defaultValues: {
      name: task?.name || '',
      description: task?.description || '',
      status: task?.status || 'pending',
      priority: task?.priority || 'medium',
      due_date: task?.due_date ? task.due_date.split('T')[0] : '',
      parent_id: task?.parent_id || undefined
    }
  });

  // Reset form when task changes
  useEffect(() => {
    if (task) {
      form.reset({
        name: task.name,
        description: task.description || '',
        status: task.status,
        priority: task.priority,
        due_date: task.due_date ? task.due_date.split('T')[0] : '',
        parent_id: task.parent_id || undefined
      });
    }
  }, [task, form]);

  const handleSubmit = (data: TaskFormData) => {
    // Clean up the data before submitting
    const cleanedData = {
      ...data,
      description: data.description?.trim() || undefined,
      due_date: data.due_date || undefined,
      parent_id: data.parent_id || undefined
    };
    onSubmit(cleanedData);
  };

  const validateDueDate = (value: string) => {
    if (!value) return true;
    const selectedDate = new Date(value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate < today) {
      return 'Due date cannot be in the past';
    }
    return true;
  };

  const getStatusDescription = (status: Task['status']) => {
    switch (status) {
      case 'pending':
        return 'Task is waiting to be started';
      case 'in_progress':
        return 'Task is currently being worked on';
      case 'completed':
        return 'Task has been finished';
      case 'cancelled':
        return 'Task has been cancelled and will not be completed';
      default:
        return '';
    }
  };

  const getPriorityDescription = (priority: Task['priority']) => {
    switch (priority) {
      case 'low':
        return 'Can be done when time permits';
      case 'medium':
        return 'Normal priority task';
      case 'high':
        return 'Should be completed soon';
      case 'urgent':
        return 'Requires immediate attention';
      default:
        return '';
    }
  };

  const formContent = (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6">
        {/* Task Name */}
        <FormField
          control={form.control}
          name="name"
          rules={{
            required: 'Task name is required',
            minLength: {
              value: 3,
              message: 'Task name must be at least 3 characters long'
            },
            maxLength: {
              value: 255,
              message: 'Task name cannot exceed 255 characters'
            }
          }}
          render={({ field }) => (
            <FormItem>
              <FormLabel className="flex items-center gap-2">
                <User className="h-4 w-4" />
                Task Name *
              </FormLabel>
              <FormControl>
                <Input
                  placeholder="Enter a clear, descriptive task name"
                  {...field}
                  className="text-base"
                />
              </FormControl>
              <FormDescription>
                Choose a name that clearly describes what needs to be done
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        {/* Description */}
        <FormField
          control={form.control}
          name="description"
          rules={{
            maxLength: {
              value: 1000,
              message: 'Description cannot exceed 1000 characters'
            }
          }}
          render={({ field }) => (
            <FormItem>
              <FormLabel>Description</FormLabel>
              <FormControl>
                <Textarea
                  placeholder="Provide additional details about this task (optional)"
                  rows={4}
                  {...field}
                  className="resize-none"
                />
              </FormControl>
              <FormDescription>
                Add any important details, requirements, or notes about this task
              </FormDescription>
              <FormMessage />
            </FormItem>
          )}
        />

        {/* Status and Priority Row */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <FormField
            control={form.control}
            name="status"
            render={({ field }) => (
              <FormItem>
                <FormLabel className="flex items-center gap-2">
                  <Clock className="h-4 w-4" />
                  Status
                </FormLabel>
                <Select onValueChange={field.onChange} value={field.value}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="pending">
                      <div className="flex items-center gap-2">
                        <div className="w-2 h-2 rounded-full bg-gray-400"></div>
                        Pending
                      </div>
                    </SelectItem>
                    <SelectItem value="in_progress">
                      <div className="flex items-center gap-2">
                        <div className="w-2 h-2 rounded-full bg-blue-500"></div>
                        In Progress
                      </div>
                    </SelectItem>
                    <SelectItem value="completed">
                      <div className="flex items-center gap-2">
                        <div className="w-2 h-2 rounded-full bg-green-500"></div>
                        Completed
                      </div>
                    </SelectItem>
                    <SelectItem value="cancelled">
                      <div className="flex items-center gap-2">
                        <div className="w-2 h-2 rounded-full bg-red-500"></div>
                        Cancelled
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
                <FormDescription>
                  {getStatusDescription(form.watch('status'))}
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="priority"
            render={({ field }) => (
              <FormItem>
                <FormLabel className="flex items-center gap-2">
                  <Flag className="h-4 w-4" />
                  Priority
                </FormLabel>
                <Select onValueChange={field.onChange} value={field.value}>
                  <FormControl>
                    <SelectTrigger>
                      <SelectValue placeholder="Select priority" />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    <SelectItem value="low">
                      <div className="flex items-center gap-2">
                        <Flag className="h-3 w-3 text-gray-400" />
                        Low
                      </div>
                    </SelectItem>
                    <SelectItem value="medium">
                      <div className="flex items-center gap-2">
                        <Flag className="h-3 w-3 text-yellow-500" />
                        Medium
                      </div>
                    </SelectItem>
                    <SelectItem value="high">
                      <div className="flex items-center gap-2">
                        <Flag className="h-3 w-3 text-orange-500" />
                        High
                      </div>
                    </SelectItem>
                    <SelectItem value="urgent">
                      <div className="flex items-center gap-2">
                        <Flag className="h-3 w-3 text-red-500" />
                        Urgent
                      </div>
                    </SelectItem>
                  </SelectContent>
                </Select>
                <FormDescription>
                  {getPriorityDescription(form.watch('priority'))}
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        {/* Due Date and Parent Task Row */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <FormField
            control={form.control}
            name="due_date"
            rules={{
              validate: validateDueDate
            }}
            render={({ field }) => (
              <FormItem>
                <FormLabel className="flex items-center gap-2">
                  <Calendar className="h-4 w-4" />
                  Due Date
                </FormLabel>
                <FormControl>
                  <Input
                    type="date"
                    {...field}
                    min={new Date().toISOString().split('T')[0]}
                  />
                </FormControl>
                <FormDescription>
                  When should this task be completed? (optional)
                </FormDescription>
                <FormMessage />
              </FormItem>
            )}
          />

          {availableParents.length > 0 && (
            <FormField
              control={form.control}
              name="parent_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Parent Task</FormLabel>
                  <Select 
                    onValueChange={(value) => field.onChange(value ? parseInt(value) : undefined)} 
                    value={field.value?.toString() || ''}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="Select parent task (optional)" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value="">
                        <span className="text-gray-500">No parent task</span>
                      </SelectItem>
                      {availableParents.map((parent) => (
                        <SelectItem key={parent.id} value={parent.id.toString()}>
                          <div className="flex items-center gap-2">
                            <div className={`w-2 h-2 rounded-full ${
                              parent.status === 'completed' ? 'bg-green-500' :
                              parent.status === 'in_progress' ? 'bg-blue-500' :
                              'bg-gray-400'
                            }`}></div>
                            {parent.name}
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    Make this task a subtask of another task (optional)
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
          )}
        </div>

        {/* Form Actions */}
        <div className="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t border-gray-200">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={loading}
            className="flex items-center gap-2"
          >
            <X className="h-4 w-4" />
            Cancel
          </Button>
          <Button
            type="submit"
            disabled={loading || !form.formState.isValid}
            className="flex items-center gap-2"
          >
            <Save className="h-4 w-4" />
            {loading ? 'Saving...' : (task ? 'Update Task' : 'Create Task')}
          </Button>
        </div>

        {/* Validation Summary */}
        {Object.keys(form.formState.errors).length > 0 && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4">
            <div className="flex items-center gap-2 text-red-800 mb-2">
              <AlertCircle className="h-4 w-4" />
              <span className="font-medium">Please fix the following errors:</span>
            </div>
            <ul className="text-sm text-red-700 space-y-1">
              {Object.entries(form.formState.errors).map(([field, error]) => (
                <li key={field}>• {error.message}</li>
              ))}
            </ul>
          </div>
        )}
      </form>
    </Form>
  );

  if (showCard) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>
            {task ? 'Edit Task' : 'Create New Task'}
          </CardTitle>
        </CardHeader>
        <CardContent>
          {formContent}
        </CardContent>
      </Card>
    );
  }

  return formContent;
};

export default TaskForm;