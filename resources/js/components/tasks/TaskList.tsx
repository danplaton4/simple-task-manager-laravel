import React from 'react';
import { Task } from '@/types';

interface TaskListProps {
  tasks: Task[];
  onEdit?: (task: Task) => void;
  onDelete?: (taskId: number) => void;
  onToggleStatus?: (taskId: number) => void;
  loading?: boolean;
}

const TaskList: React.FC<TaskListProps> = ({
  tasks,
  onEdit,
  onDelete,
  onToggleStatus,
  loading = false
}) => {
  if (loading) {
    return (
      <div className="flex justify-center items-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (tasks.length === 0) {
    return (
      <div className="text-center py-8 text-gray-500">
        <p>No tasks found. Create your first task to get started!</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {tasks.map((task) => (
        <div
          key={task.id}
          className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow"
        >
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <h3 className="text-lg font-medium text-gray-900 mb-2">
                {task.name}
              </h3>
              {task.description && (
                <p className="text-gray-600 mb-3">{task.description}</p>
              )}
              <div className="flex items-center space-x-4 text-sm">
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                  task.status === 'completed' ? 'bg-green-100 text-green-800' :
                  task.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                  task.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                  'bg-gray-100 text-gray-800'
                }`}>
                  {task.status.replace('_', ' ')}
                </span>
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                  task.priority === 'urgent' ? 'bg-red-100 text-red-800' :
                  task.priority === 'high' ? 'bg-orange-100 text-orange-800' :
                  task.priority === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                  'bg-gray-100 text-gray-800'
                }`}>
                  {task.priority}
                </span>
                {task.due_date && (
                  <span className="text-gray-500">
                    Due: {new Date(task.due_date).toLocaleDateString()}
                  </span>
                )}
              </div>
            </div>
            <div className="flex items-center space-x-2 ml-4">
              {onEdit && (
                <button
                  onClick={() => onEdit(task)}
                  className="text-blue-600 hover:text-blue-800 text-sm font-medium"
                >
                  Edit
                </button>
              )}
              {onDelete && (
                <button
                  onClick={() => onDelete(task.id)}
                  className="text-red-600 hover:text-red-800 text-sm font-medium"
                >
                  Delete
                </button>
              )}
            </div>
          </div>
          {task.subtasks && task.subtasks.length > 0 && (
            <div className="mt-4 pl-4 border-l-2 border-gray-200">
              <p className="text-sm text-gray-600 mb-2">Subtasks:</p>
              <div className="space-y-2">
                {task.subtasks.map((subtask) => (
                  <div key={subtask.id} className="text-sm">
                    <span className="font-medium">{subtask.name}</span>
                    <span className={`ml-2 px-2 py-0.5 rounded text-xs ${
                      subtask.status === 'completed' ? 'bg-green-100 text-green-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {subtask.status.replace('_', ' ')}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      ))}
    </div>
  );
};

export default TaskList;