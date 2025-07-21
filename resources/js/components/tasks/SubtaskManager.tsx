import React, { useState, useMemo } from 'react';
import { Task, TaskFormData } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import TaskForm from './TaskForm';
import TaskCard from './TaskCard';
import { Plus, CheckCircle, Circle, Clock, AlertCircle, Trash2, Edit3 } from 'lucide-react';

interface SubtaskManagerProps {
  parentTask: Task;
  subtasks: Task[];
  onAddSubtask: (subtaskData: TaskFormData) => void;
  onUpdateSubtask: (subtaskId: number, data: TaskFormData) => void;
  onDeleteSubtask: (subtaskId: number) => void;
  onToggleSubtaskStatus?: (subtaskId: number) => void;
  loading?: boolean;
  allowNesting?: boolean;
}

const SubtaskManager: React.FC<SubtaskManagerProps> = ({
  parentTask,
  subtasks,
  onAddSubtask,
  onUpdateSubtask,
  onDeleteSubtask,
  onToggleSubtaskStatus,
  loading = false,
  allowNesting = false
}) => {
  const [isAddFormVisible, setIsAddFormVisible] = useState(false);
  const [editingSubtask, setEditingSubtask] = useState<Task | null>(null);
  const [viewMode, setViewMode] = useState<'list' | 'cards'>('list');

  // Calculate progress statistics
  const progressStats = useMemo(() => {
    const total = subtasks.length;
    const completed = subtasks.filter(task => task.status === 'completed').length;
    const inProgress = subtasks.filter(task => task.status === 'in_progress').length;
    const pending = subtasks.filter(task => task.status === 'pending').length;
    const cancelled = subtasks.filter(task => task.status === 'cancelled').length;
    
    return {
      total,
      completed,
      inProgress,
      pending,
      cancelled,
      completionPercentage: total > 0 ? Math.round((completed / total) * 100) : 0
    };
  }, [subtasks]);

  const handleAddSubtask = (subtaskData: TaskFormData) => {
    const dataWithParent = {
      ...subtaskData,
      parent_id: parentTask.id
    };
    onAddSubtask(dataWithParent);
    setIsAddFormVisible(false);
  };

  const handleUpdateSubtask = (subtaskData: TaskFormData) => {
    if (editingSubtask) {
      onUpdateSubtask(editingSubtask.id, subtaskData);
      setEditingSubtask(null);
    }
  };

  const handleDeleteSubtask = (subtaskId: number) => {
    if (confirm('Are you sure you want to delete this subtask? This action cannot be undone.')) {
      onDeleteSubtask(subtaskId);
    }
  };

  const handleCancelEdit = () => {
    setEditingSubtask(null);
    setIsAddFormVisible(false);
  };

  const getStatusIcon = (status: Task['status']) => {
    switch (status) {
      case 'completed':
        return <CheckCircle className="h-4 w-4 text-green-600" />;
      case 'in_progress':
        return <Clock className="h-4 w-4 text-blue-600" />;
      case 'cancelled':
        return <AlertCircle className="h-4 w-4 text-red-600" />;
      default:
        return <Circle className="h-4 w-4 text-gray-400" />;
    }
  };

  return (
    <div className="space-y-6">
      {/* Header with Parent Task Info */}
      <Card>
        <CardHeader>
          <div className="flex items-start justify-between">
            <div>
              <CardTitle className="text-xl text-gray-900">
                Managing Subtasks for: {parentTask.name}
              </CardTitle>
              <p className="text-sm text-gray-600 mt-1">
                Break down this task into smaller, manageable pieces
              </p>
            </div>
            <Button
              onClick={() => setIsAddFormVisible(true)}
              disabled={loading || isAddFormVisible || editingSubtask !== null}
              className="flex items-center gap-2"
            >
              <Plus className="h-4 w-4" />
              Add Subtask
            </Button>
          </div>
        </CardHeader>
        
        {/* Progress Overview */}
        {subtasks.length > 0 && (
          <CardContent className="pt-0">
            <div className="bg-gray-50 rounded-lg p-4">
              <div className="flex items-center justify-between mb-3">
                <h4 className="font-medium text-gray-900">Progress Overview</h4>
                <span className="text-2xl font-bold text-blue-600">
                  {progressStats.completionPercentage}%
                </span>
              </div>
              
              <div className="w-full bg-gray-200 rounded-full h-2 mb-4">
                <div 
                  className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                  style={{ width: `${progressStats.completionPercentage}%` }}
                ></div>
              </div>
              
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div className="text-center">
                  <div className="font-semibold text-gray-900">{progressStats.total}</div>
                  <div className="text-gray-600">Total</div>
                </div>
                <div className="text-center">
                  <div className="font-semibold text-green-600">{progressStats.completed}</div>
                  <div className="text-gray-600">Completed</div>
                </div>
                <div className="text-center">
                  <div className="font-semibold text-blue-600">{progressStats.inProgress}</div>
                  <div className="text-gray-600">In Progress</div>
                </div>
                <div className="text-center">
                  <div className="font-semibold text-gray-600">{progressStats.pending}</div>
                  <div className="text-gray-600">Pending</div>
                </div>
              </div>
            </div>
          </CardContent>
        )}
      </Card>

      {/* Add Subtask Form */}
      {isAddFormVisible && (
        <Card>
          <CardHeader>
            <CardTitle>Add New Subtask</CardTitle>
          </CardHeader>
          <CardContent>
            <TaskForm
              onSubmit={handleAddSubtask}
              onCancel={handleCancelEdit}
              loading={loading}
            />
          </CardContent>
        </Card>
      )}

      {/* Edit Subtask Form */}
      {editingSubtask && (
        <Card>
          <CardHeader>
            <CardTitle>Edit Subtask</CardTitle>
          </CardHeader>
          <CardContent>
            <TaskForm
              task={editingSubtask}
              onSubmit={handleUpdateSubtask}
              onCancel={handleCancelEdit}
              loading={loading}
            />
          </CardContent>
        </Card>
      )}

      {/* Subtasks List */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>
              Subtasks ({subtasks.length})
            </CardTitle>
            {subtasks.length > 0 && (
              <div className="flex items-center space-x-2">
                <Button
                  variant={viewMode === 'list' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => setViewMode('list')}
                >
                  List
                </Button>
                <Button
                  variant={viewMode === 'cards' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => setViewMode('cards')}
                >
                  Cards
                </Button>
              </div>
            )}
          </div>
        </CardHeader>
        
        <CardContent>
          {subtasks.length === 0 ? (
            <div className="text-center py-12 bg-gray-50 rounded-lg">
              <div className="text-gray-400 mb-4">
                <Plus className="h-12 w-12 mx-auto" />
              </div>
              <h3 className="text-lg font-medium text-gray-900 mb-2">No subtasks yet</h3>
              <p className="text-gray-500 mb-4">
                Break down "{parentTask.name}" into smaller, manageable tasks.
              </p>
              <Button onClick={() => setIsAddFormVisible(true)}>
                Add Your First Subtask
              </Button>
            </div>
          ) : viewMode === 'cards' ? (
            <div className="space-y-4">
              {subtasks.map((subtask) => (
                <TaskCard
                  key={subtask.id}
                  task={subtask}
                  onEdit={setEditingSubtask}
                  onDelete={handleDeleteSubtask}
                  onToggleStatus={onToggleSubtaskStatus}
                  showSubtasks={allowNesting}
                />
              ))}
            </div>
          ) : (
            <div className="space-y-3">
              {subtasks.map((subtask, index) => (
                <div
                  key={subtask.id}
                  className="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors"
                >
                  <div className="flex-shrink-0">
                    {getStatusIcon(subtask.status)}
                  </div>
                  
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <h4 className={`font-medium ${
                          subtask.status === 'completed' 
                            ? 'line-through text-gray-500' 
                            : 'text-gray-900'
                        }`}>
                          {subtask.name}
                        </h4>
                        {subtask.description && (
                          <p className="text-sm text-gray-600 mt-1 line-clamp-2">
                            {subtask.description}
                          </p>
                        )}
                        <div className="flex items-center space-x-3 mt-2 text-xs">
                          <span className={`px-2 py-1 rounded-full font-medium ${
                            subtask.status === 'completed' ? 'bg-green-100 text-green-800' :
                            subtask.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                            subtask.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                            'bg-gray-100 text-gray-800'
                          }`}>
                            {subtask.status.replace('_', ' ')}
                          </span>
                          <span className={`px-2 py-1 rounded-full font-medium ${
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
                        {onToggleSubtaskStatus && (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => onToggleSubtaskStatus(subtask.id)}
                            disabled={loading}
                            className={subtask.status === 'completed' 
                              ? 'text-orange-600 hover:text-orange-800' 
                              : 'text-green-600 hover:text-green-800'
                            }
                          >
                            {subtask.status === 'completed' ? 'Reopen' : 'Complete'}
                          </Button>
                        )}
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => setEditingSubtask(subtask)}
                          disabled={loading || editingSubtask !== null || isAddFormVisible}
                          className="text-blue-600 hover:text-blue-800"
                        >
                          <Edit3 className="h-3 w-3" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDeleteSubtask(subtask.id)}
                          disabled={loading}
                          className="text-red-600 hover:text-red-800"
                        >
                          <Trash2 className="h-3 w-3" />
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
};

export default SubtaskManager;