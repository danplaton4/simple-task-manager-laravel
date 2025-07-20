import React, { useState } from 'react';
import { Task, TaskFormData } from '@/types';
import { Button } from '@/components/ui/button';
import TaskForm from './TaskForm';

interface SubtaskManagerProps {
  parentTaskId: number;
  subtasks: Task[];
  onAddSubtask: (subtaskData: TaskFormData) => void;
  onUpdateSubtask: (subtaskId: number, data: TaskFormData) => void;
  onDeleteSubtask: (subtaskId: number) => void;
  loading?: boolean;
}

const SubtaskManager: React.FC<SubtaskManagerProps> = ({
  parentTaskId,
  subtasks,
  onAddSubtask,
  onUpdateSubtask,
  onDeleteSubtask,
  loading = false
}) => {
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [editingSubtask, setEditingSubtask] = useState<Task | null>(null);

  const handleAddSubtask = (subtaskData: TaskFormData) => {
    const dataWithParent = {
      ...subtaskData,
      parent_id: parentTaskId
    };
    onAddSubtask(dataWithParent);
    setIsAddModalOpen(false);
  };

  const handleUpdateSubtask = (subtaskData: TaskFormData) => {
    if (editingSubtask) {
      onUpdateSubtask(editingSubtask.id, subtaskData);
      setEditingSubtask(null);
    }
  };

  const handleDeleteSubtask = (subtaskId: number) => {
    if (confirm('Are you sure you want to delete this subtask?')) {
      onDeleteSubtask(subtaskId);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-medium text-gray-900">
          Subtasks ({subtasks.length})
        </h3>
        <Button
          onClick={() => setIsAddModalOpen(true)}
          size="sm"
          disabled={loading}
        >
          Add Subtask
        </Button>
      </div>

      {subtasks.length === 0 ? (
        <div className="text-center py-8 text-gray-500 bg-gray-50 rounded-lg">
          <p>No subtasks yet. Add your first subtask to break down this task.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {subtasks.map((subtask) => (
            <div
              key={subtask.id}
              className="bg-gray-50 rounded-lg p-4 border border-gray-200"
            >
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <h4 className="font-medium text-gray-900 mb-1">
                    {subtask.name}
                  </h4>
                  {subtask.description && (
                    <p className="text-sm text-gray-600 mb-2">
                      {subtask.description}
                    </p>
                  )}
                  <div className="flex items-center space-x-3 text-sm">
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                      subtask.status === 'completed' ? 'bg-green-100 text-green-800' :
                      subtask.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                      subtask.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {subtask.status.replace('_', ' ')}
                    </span>
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                      subtask.priority === 'urgent' ? 'bg-red-100 text-red-800' :
                      subtask.priority === 'high' ? 'bg-orange-100 text-orange-800' :
                      subtask.priority === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {subtask.priority}
                    </span>
                    {subtask.due_date && (
                      <span className="text-gray-500">
                        Due: {new Date(subtask.due_date).toLocaleDateString()}
                      </span>
                    )}
                  </div>
                </div>
                <div className="flex items-center space-x-2 ml-4">
                  <button
                    onClick={() => setEditingSubtask(subtask)}
                    className="text-blue-600 hover:text-blue-800 text-sm font-medium"
                    disabled={loading}
                  >
                    Edit
                  </button>
                  <button
                    onClick={() => handleDeleteSubtask(subtask.id)}
                    className="text-red-600 hover:text-red-800 text-sm font-medium"
                    disabled={loading}
                  >
                    Delete
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* TODO: Add modals back with shadcn/ui dialog component */}
    </div>
  );
};

export default SubtaskManager;