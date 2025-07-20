import React, { useState } from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Task } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ChevronDown, ChevronRight, Calendar, User, Clock, GripVertical } from 'lucide-react';

interface DraggableTaskCardProps {
  task: Task;
  onEdit?: (task: Task) => void;
  onDelete?: (taskId: number) => void;
  onToggleStatus?: (taskId: number) => void;
  onViewSubtasks?: (task: Task) => void;
  depth?: number;
  showSubtasks?: boolean;
  isDragEnabled?: boolean;
  isOverlay?: boolean;
}

const DraggableTaskCard: React.FC<DraggableTaskCardProps> = ({
  task,
  onEdit,
  onDelete,
  onToggleStatus,
  onViewSubtasks,
  depth = 0,
  showSubtasks = true,
  isDragEnabled = false,
  isOverlay = false
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  const hasSubtasks = task.subtasks && task.subtasks.length > 0;

  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({
    id: task.id.toString(),
    disabled: !isDragEnabled || depth > 0, // Only allow dragging parent tasks
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  const getStatusColor = (status: Task['status']) => {
    switch (status) {
      case 'completed':
        return 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800';
      case 'in_progress':
        return 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/20 dark:text-blue-400 dark:border-blue-800';
      case 'cancelled':
        return 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800';
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
    }
  };

  const getPriorityColor = (priority: Task['priority']) => {
    switch (priority) {
      case 'urgent':
        return 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800';
      case 'high':
        return 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/20 dark:text-orange-400 dark:border-orange-800';
      case 'medium':
        return 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-400 dark:border-yellow-800';
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700';
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = date.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays < 0) {
      return { text: `${Math.abs(diffDays)} days overdue`, color: 'text-red-600 dark:text-red-400' };
    } else if (diffDays === 0) {
      return { text: 'Due today', color: 'text-orange-600 dark:text-orange-400' };
    } else if (diffDays === 1) {
      return { text: 'Due tomorrow', color: 'text-yellow-600 dark:text-yellow-400' };
    } else {
      return { text: `Due in ${diffDays} days`, color: 'text-muted-foreground' };
    }
  };

  const toggleExpanded = () => {
    if (hasSubtasks) {
      setIsExpanded(!isExpanded);
    }
  };

  return (
    <div className={`${depth > 0 ? 'ml-6 border-l-2 border-border pl-4' : ''}`}>
      <Card 
        ref={setNodeRef}
        style={style}
        className={`hover:shadow-md transition-all duration-200 ${
          task.status === 'completed' ? 'opacity-75' : ''
        } ${isDragging ? 'shadow-lg' : ''} ${isOverlay ? 'rotate-3 shadow-xl' : ''}`}
      >
        <CardHeader className="pb-3">
          <div className="flex items-start justify-between">
            <div className="flex items-start space-x-3 flex-1">
              {isDragEnabled && depth === 0 && (
                <button
                  {...attributes}
                  {...listeners}
                  className="mt-1 p-1 hover:bg-muted rounded transition-colors cursor-grab active:cursor-grabbing"
                  aria-label="Drag to reorder"
                >
                  <GripVertical className="h-4 w-4 text-muted-foreground" />
                </button>
              )}
              
              {hasSubtasks && (
                <button
                  onClick={toggleExpanded}
                  className="mt-1 p-1 hover:bg-muted rounded transition-colors"
                  aria-label={isExpanded ? 'Collapse subtasks' : 'Expand subtasks'}
                >
                  {isExpanded ? (
                    <ChevronDown className="h-4 w-4 text-muted-foreground" />
                  ) : (
                    <ChevronRight className="h-4 w-4 text-muted-foreground" />
                  )}
                </button>
              )}
              
              <div className="flex-1">
                <CardTitle className={`text-lg ${
                  task.status === 'completed' ? 'line-through text-muted-foreground' : ''
                }`}>
                  {task.name}
                  {hasSubtasks && (
                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                      ({task.subtasks!.length} subtask{task.subtasks!.length !== 1 ? 's' : ''})
                    </span>
                  )}
                </CardTitle>
              </div>
            </div>
            
            {!isOverlay && (
              <div className="flex items-center space-x-2">
                {onToggleStatus && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onToggleStatus(task.id)}
                    className={task.status === 'completed' ? 'text-orange-600 hover:text-orange-800 dark:text-orange-400 dark:hover:text-orange-300' : 'text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300'}
                  >
                    {task.status === 'completed' ? 'Reopen' : 'Complete'}
                  </Button>
                )}
                {onEdit && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onEdit(task)}
                    className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                  >
                    Edit
                  </Button>
                )}
                {onDelete && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onDelete(task.id)}
                    className="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                  >
                    Delete
                  </Button>
                )}
              </div>
            )}
          </div>
        </CardHeader>
        
        <CardContent className="pt-0">
          {task.description && (
            <p className="text-muted-foreground mb-4 leading-relaxed">
              {task.description}
            </p>
          )}
          
          <div className="flex flex-wrap items-center gap-3 text-sm">
            <span className={`px-3 py-1 rounded-full text-xs font-medium border ${getStatusColor(task.status)}`}>
              <Clock className="inline h-3 w-3 mr-1" />
              {task.status.replace('_', ' ')}
            </span>
            
            <span className={`px-3 py-1 rounded-full text-xs font-medium border ${getPriorityColor(task.priority)}`}>
              {task.priority} priority
            </span>
            
            {task.due_date && (
              <span className={`flex items-center ${formatDate(task.due_date).color}`}>
                <Calendar className="h-3 w-3 mr-1" />
                {formatDate(task.due_date).text}
              </span>
            )}
            
            <span className="text-muted-foreground flex items-center">
              <User className="h-3 w-3 mr-1" />
              Created {new Date(task.created_at).toLocaleDateString()}
            </span>
          </div>
          
          {hasSubtasks && onViewSubtasks && !isOverlay && (
            <div className="mt-4 pt-4 border-t border-border">
              <Button
                variant="outline"
                size="sm"
                onClick={() => onViewSubtasks(task)}
                className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
              >
                Manage Subtasks ({task.subtasks!.length})
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
      
      {/* Render subtasks hierarchically */}
      {hasSubtasks && isExpanded && showSubtasks && !isOverlay && (
        <div className="mt-3 space-y-3">
          {task.subtasks!.map((subtask) => (
            <DraggableTaskCard
              key={subtask.id}
              task={subtask}
              onEdit={onEdit}
              onDelete={onDelete}
              onToggleStatus={onToggleStatus}
              onViewSubtasks={onViewSubtasks}
              depth={depth + 1}
              showSubtasks={showSubtasks}
              isDragEnabled={false} // Subtasks are not draggable
            />
          ))}
        </div>
      )}
    </div>
  );
};

export default DraggableTaskCard;