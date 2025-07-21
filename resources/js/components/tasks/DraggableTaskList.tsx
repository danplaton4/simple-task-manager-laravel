import React, { useState, useMemo } from 'react';
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  DragEndEvent,
  DragOverlay,
  DragStartEvent,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {
  restrictToVerticalAxis,
  restrictToWindowEdges,
} from '@dnd-kit/modifiers';

import { Task, TaskFilters } from '@/types';
import DraggableTaskCard from './DraggableTaskCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Search, Filter, SortAsc, SortDesc, List, Grid, GripVertical } from 'lucide-react';
import { useResponsive } from '@/hooks/useResponsive';

interface DraggableTaskListProps {
  tasks: Task[];
  onEdit?: (task: Task) => void;
  onDelete?: (taskId: number) => void;
  onToggleStatus?: (taskId: number) => void;
  onViewSubtasks?: (task: Task) => void;
  onReorder?: (taskIds: number[]) => void;
  loading?: boolean;
  showFilters?: boolean;
  showSearch?: boolean;
  viewMode?: 'list' | 'grid';
  enableDragAndDrop?: boolean;
  onOpenTask?: (task: Task) => void;
}

type SortOption = 'name' | 'created_at' | 'due_date' | 'priority' | 'status' | 'custom';
type SortDirection = 'asc' | 'desc';

const DraggableTaskList: React.FC<DraggableTaskListProps> = ({
  tasks,
  onEdit,
  onDelete,
  onToggleStatus,
  onViewSubtasks,
  onReorder,
  loading = false,
  showFilters = true,
  showSearch = true,
  viewMode = 'list',
  enableDragAndDrop = true,
  onOpenTask
}) => {
  const [filters, setFilters] = useState<TaskFilters>({});
  const [searchTerm, setSearchTerm] = useState('');
  const [sortBy, setSortBy] = useState<SortOption>('created_at');
  const [sortDirection, setSortDirection] = useState<SortDirection>('desc');
  const [showCompleted, setShowCompleted] = useState(true);
  const [currentViewMode, setCurrentViewMode] = useState<'list' | 'grid'>(viewMode);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [localTasks, setLocalTasks] = useState<Task[]>(tasks);
  const { isMobile, isTablet } = useResponsive();

  // Update local tasks when props change
  React.useEffect(() => {
    setLocalTasks(tasks);
  }, [tasks]);

  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    })
  );

  // Filter and sort tasks
  const filteredAndSortedTasks = useMemo(() => {
    let filtered = localTasks.filter(task => {
      // Filter by search term
      if (searchTerm && !task.name.toLowerCase().includes(searchTerm.toLowerCase()) &&
          !task.description?.toLowerCase().includes(searchTerm.toLowerCase())) {
        return false;
      }

      // Filter by status
      if (filters.status && task.status !== filters.status) {
        return false;
      }

      // Filter by priority
      if (filters.priority && task.priority !== filters.priority) {
        return false;
      }

      // Filter completed tasks
      if (!showCompleted && task.status === 'completed') {
        return false;
      }

      return true;
    });

    // Sort tasks (skip sorting if using custom order and drag-and-drop is enabled)
    if (sortBy !== 'custom' || !enableDragAndDrop) {
      filtered.sort((a, b) => {
        let aValue: any;
        let bValue: any;

        switch (sortBy) {
          case 'name':
            aValue = a.name.toLowerCase();
            bValue = b.name.toLowerCase();
            break;
          case 'created_at':
            aValue = new Date(a.created_at);
            bValue = new Date(b.created_at);
            break;
          case 'due_date':
            aValue = a.due_date ? new Date(a.due_date) : new Date('9999-12-31');
            bValue = b.due_date ? new Date(b.due_date) : new Date('9999-12-31');
            break;
          case 'priority':
            const priorityOrder = { 'urgent': 4, 'high': 3, 'medium': 2, 'low': 1 };
            aValue = priorityOrder[a.priority];
            bValue = priorityOrder[b.priority];
            break;
          case 'status':
            const statusOrder = { 'pending': 1, 'in_progress': 2, 'completed': 3, 'cancelled': 4 };
            aValue = statusOrder[a.status];
            bValue = statusOrder[b.status];
            break;
          default:
            return 0;
        }

        if (aValue < bValue) return sortDirection === 'asc' ? -1 : 1;
        if (aValue > bValue) return sortDirection === 'asc' ? 1 : -1;
        return 0;
      });
    }

    return filtered;
  }, [localTasks, searchTerm, filters, sortBy, sortDirection, showCompleted, enableDragAndDrop]);

  // Separate parent tasks and subtasks for hierarchical display
  const parentTasks = useMemo(() => {
    return filteredAndSortedTasks.filter(task => !task.parent_id);
  }, [filteredAndSortedTasks]);

  const toggleSort = (newSortBy: SortOption) => {
    if (sortBy === newSortBy) {
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
      setSortBy(newSortBy);
      setSortDirection('asc');
    }
  };

  const handleDragStart = (event: DragStartEvent) => {
    setActiveId(event.active.id as string);
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    setActiveId(null);

    if (!over || active.id === over.id) {
      return;
    }

    const oldIndex = parentTasks.findIndex(task => task.id.toString() === active.id);
    const newIndex = parentTasks.findIndex(task => task.id.toString() === over.id);

    if (oldIndex !== -1 && newIndex !== -1) {
      const newOrder = arrayMove(parentTasks, oldIndex, newIndex);
      const newTaskOrder = newOrder.map(task => task.id);
      
      // Update local state immediately for smooth UX
      const reorderedTasks = arrayMove(localTasks, oldIndex, newIndex);
      setLocalTasks(reorderedTasks);
      
      // Notify parent component
      if (onReorder) {
        onReorder(newTaskOrder);
      }
    }
  };

  const activeTask = parentTasks.find(task => task.id.toString() === activeId);

  if (loading) {
    return (
      <div className="flex justify-center items-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        <span className="ml-3 text-muted-foreground">Loading tasks...</span>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Search and Filters */}
      {(showSearch || showFilters) && (
        <div className="bg-card p-4 rounded-lg border space-y-4">
          {showSearch && (
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search tasks..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
          )}

          {showFilters && (
            <div className="flex flex-wrap items-center gap-2 sm:gap-4">
              <div className="flex items-center space-x-2">
                <Filter className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium hidden sm:inline">Filters:</span>
              </div>

              <Select value={filters.status || 'all'} onValueChange={(value) => 
                setFilters(prev => ({ ...prev, status: value === 'all' ? undefined : (value as Task['status']) }))
              }>
                <SelectTrigger className="w-32">
                  <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Status</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="in_progress">In Progress</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
              </Select>

              <Select value={filters.priority || 'all'} onValueChange={(value) => 
                setFilters(prev => ({ ...prev, priority: value === 'all' ? undefined : (value as Task['priority']) }))
              }>
                <SelectTrigger className="w-32">
                  <SelectValue placeholder="Priority" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Priority</SelectItem>
                  <SelectItem value="low">Low</SelectItem>
                  <SelectItem value="medium">Medium</SelectItem>
                  <SelectItem value="high">High</SelectItem>
                  <SelectItem value="urgent">Urgent</SelectItem>
                </SelectContent>
              </Select>

              <Button
                variant="outline"
                size="sm"
                onClick={() => setShowCompleted(!showCompleted)}
                className={showCompleted ? '' : 'bg-muted'}
              >
                {showCompleted ? 'Hide' : 'Show'} Completed
              </Button>

              <div className="flex items-center space-x-2 ml-auto">
                <span className="text-sm text-muted-foreground hidden sm:inline">Sort by:</span>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => toggleSort('name')}
                  className={sortBy === 'name' ? 'bg-muted' : ''}
                >
                  Name {sortBy === 'name' && (sortDirection === 'asc' ? <SortAsc className="ml-1 h-3 w-3" /> : <SortDesc className="ml-1 h-3 w-3" />)}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => toggleSort('due_date')}
                  className={sortBy === 'due_date' ? 'bg-muted' : ''}
                >
                  Due Date {sortBy === 'due_date' && (sortDirection === 'asc' ? <SortAsc className="ml-1 h-3 w-3" /> : <SortDesc className="ml-1 h-3 w-3" />)}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => toggleSort('priority')}
                  className={sortBy === 'priority' ? 'bg-muted' : ''}
                >
                  Priority {sortBy === 'priority' && (sortDirection === 'asc' ? <SortAsc className="ml-1 h-3 w-3" /> : <SortDesc className="ml-1 h-3 w-3" />)}
                </Button>
                
                {enableDragAndDrop && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => toggleSort('custom')}
                    className={sortBy === 'custom' ? 'bg-muted' : ''}
                  >
                    <GripVertical className="h-4 w-4 mr-1" />
                    Custom Order
                  </Button>
                )}

                <div className="border-l border-border pl-2 ml-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setCurrentViewMode('list')}
                    className={currentViewMode === 'list' ? 'bg-muted' : ''}
                  >
                    <List className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setCurrentViewMode('grid')}
                    className={currentViewMode === 'grid' ? 'bg-muted' : ''}
                  >
                    <Grid className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Task Count */}
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">
          Showing {filteredAndSortedTasks.length} of {localTasks.length} tasks
        </p>
        {enableDragAndDrop && sortBy === 'custom' && (
          <p className="text-sm text-muted-foreground">
            <GripVertical className="inline h-4 w-4 mr-1" />
            Drag to reorder tasks
          </p>
        )}
      </div>

      {/* Tasks Display */}
      {filteredAndSortedTasks.length === 0 ? (
        <div className="text-center py-12 bg-muted/50 rounded-lg">
          <div className="text-muted-foreground mb-4">
            <List className="h-12 w-12 mx-auto" />
          </div>
          <h3 className="text-lg font-medium mb-2">No tasks found</h3>
          <p className="text-muted-foreground">
            {searchTerm || filters.status || filters.priority 
              ? 'Try adjusting your search or filters to find tasks.'
              : 'Create your first task to get started!'
            }
          </p>
        </div>
      ) : (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCenter}
          onDragStart={handleDragStart}
          onDragEnd={handleDragEnd}
          modifiers={[restrictToVerticalAxis, restrictToWindowEdges]}
        >
          <SortableContext
            items={parentTasks.map(task => task.id.toString())}
            strategy={verticalListSortingStrategy}
          >
            <div className={currentViewMode === 'grid' 
              ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6' 
              : 'space-y-4'
            }>
              {parentTasks.map((task) => (
                <DraggableTaskCard
                  key={task.id}
                  task={task}
                  onEdit={onEdit}
                  onDelete={onDelete}
                  onToggleStatus={onToggleStatus}
                  onViewSubtasks={onViewSubtasks}
                  showSubtasks={true}
                  isDragEnabled={enableDragAndDrop && sortBy === 'custom'}
                  onOpenTask={onOpenTask}
                />
              ))}
            </div>
          </SortableContext>
          
          <DragOverlay>
            {activeTask ? (
              <DraggableTaskCard
                task={activeTask}
                onEdit={onEdit}
                onDelete={onDelete}
                onToggleStatus={onToggleStatus}
                onViewSubtasks={onViewSubtasks}
                showSubtasks={false}
                isDragEnabled={false}
                isOverlay={true}
              />
            ) : null}
          </DragOverlay>
        </DndContext>
      )}
    </div>
  );
};

export default DraggableTaskList;