import React from 'react';
import { Task } from '@/types';

interface TaskCardProps {
  task: Task;
  onEdit?: (task: Task) => void;
  onDelete?: (taskId: number) => void;
  onToggleStatus?: (taskId: number) => void;
}

const TaskCard: React.FC<TaskCardProps> = ({
  task,
  onEdit,
  onDelete,
  onToggleStatus
}) => {
  return (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow">
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
          {onToggleStatus && (
            <button
              onClick={() => onToggleStatus(task.id)}
              className="text-green-600 hover:text-green-800 text-sm font-medium"
            >
              {task.status === 'completed' ? 'Reopen' : 'Complete'}
            </button>
          )}
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
    </div>
  );
};

export default TaskCard;