import React, { useState, useMemo } from 'react';
import { Task, TaskFilters, Language } from '@/types';
import TaskCard from './TaskCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Search, Filter, SortAsc, SortDesc, List, Grid, Globe } from 'lucide-react';
import { useLanguage } from '@/contexts/LanguageContext';

interface TaskListProps {
  tasks: Task[];
  onEdit?: (task: Task) => void;
  onDelete?: (taskId: number) => void;
  onToggleStatus?: (taskId: number) => void;
  onViewSubtasks?: (task: Task) => void;
  loading?: boolean;
  showFilters?: boolean;
  showSearch?: boolean;
  viewMode?: 'list' | 'grid';
}

type SortOption = 'name' | 'created_at' | 'due_date' | 'priority' | 'status';
type SortDirection = 'asc' | 'desc';

const TaskList: React.FC<TaskListProps> = ({
  tasks,
  onEdit,
  onDelete,
  onToggleStatus,
  onViewSubtasks,
  loading = false,
  showFilters = true,
  showSearch = true,
  viewMode = 'list'
}) => {
  const { language } = useLanguage();
  const [filters, setFilters] = useState<TaskFilters>({});
  const [searchTerm, setSearchTerm] = useState('');
  const [sortBy, setSortBy] = useState<SortOption>('created_at');
  const [sortDirection, setSortDirection] = useState<SortDirection>('desc');
  const [showCompleted, setShowCompleted] = useState(true);
  const [currentViewMode, setCurrentViewMode] = useState<'list' | 'grid'>(viewMode);
  const [searchInAllLanguages, setSearchInAllLanguages] = useState(false);

  // Helper function to get localized text with fallback
  const getLocalizedText = (field: string | Record<string, string> | undefined, fallbackLang: Language = 'en'): string => {
    if (!field) return '';
    
    if (typeof field === 'string') {
      return field;
    }
    
    if (typeof field === 'object') {
      // Try current language first
      if (field[language] && field[language].trim()) {
        return field[language];
      }
      
      // Fallback to English
      if (field[fallbackLang] && field[fallbackLang].trim()) {
        return field[fallbackLang];
      }
      
      // Fallback to first available translation
      const firstAvailable = Object.values(field).find(val => val && val.trim());
      return firstAvailable || '';
    }
    
    return '';
  };

  // Helper function to search in all languages or current language only
  const searchInText = (field: string | Record<string, string> | undefined, searchTerm: string): boolean => {
    if (!field || !searchTerm) return true;
    
    const lowerSearchTerm = searchTerm.toLowerCase();
    
    if (typeof field === 'string') {
      return field.toLowerCase().includes(lowerSearchTerm);
    }
    
    if (typeof field === 'object') {
      if (searchInAllLanguages) {
        // Search in all available translations
        return Object.values(field).some(text => 
          text && text.toLowerCase().includes(lowerSearchTerm)
        );
      } else {
        // Search only in current language with fallback
        const localizedText = getLocalizedText(field);
        return localizedText.toLowerCase().includes(lowerSearchTerm);
      }
    }
    
    return false;
  };

  // Filter and sort tasks
  const filteredAndSortedTasks = useMemo(() => {
    let filtered = tasks.filter(task => {
      // Filter by search term (locale-aware)
      if (searchTerm && !searchInText(task.name, searchTerm) && !searchInText(task.description, searchTerm)) {
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

    // Sort tasks
    filtered.sort((a, b) => {
      let aValue: any;
      let bValue: any;

      switch (sortBy) {
        case 'name':
          aValue = getLocalizedText(a.name).toLowerCase();
          bValue = getLocalizedText(b.name).toLowerCase();
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

    return filtered;
  }, [tasks, searchTerm, filters, sortBy, sortDirection, showCompleted, language, searchInAllLanguages]);

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

  if (loading) {
    return (
      <div className="flex justify-center items-center py-12">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span className="ml-3 text-gray-600">Loading tasks...</span>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Search and Filters */}
      {(showSearch || showFilters) && (
        <div className="bg-white p-4 rounded-lg border border-gray-200 space-y-4">
          {showSearch && (
            <div className="space-y-3">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <Input
                  placeholder={`Search tasks in ${searchInAllLanguages ? 'all languages' : language.toUpperCase()}...`}
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-10"
                />
              </div>
              <div className="flex items-center gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setSearchInAllLanguages(!searchInAllLanguages)}
                  className={`flex items-center gap-2 ${searchInAllLanguages ? 'bg-blue-50 border-blue-200' : ''}`}
                >
                  <Globe className="h-3 w-3" />
                  {searchInAllLanguages ? 'Search all languages' : `Search ${language.toUpperCase()} only`}
                </Button>
                {searchTerm && (
                  <span className="text-xs text-gray-500">
                    {searchInAllLanguages 
                      ? 'Searching across all language translations'
                      : `Searching in ${language.toUpperCase()} with English fallback`
                    }
                  </span>
                )}
              </div>
            </div>
          )}

          {showFilters && (
            <div className="flex flex-wrap items-center gap-4">
              <div className="flex items-center space-x-2">
                <Filter className="h-4 w-4 text-gray-500" />
                <span className="text-sm font-medium text-gray-700">Filters:</span>
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
                className={showCompleted ? '' : 'bg-gray-100'}
              >
                {showCompleted ? 'Hide' : 'Show'} Completed
              </Button>

              <div className="flex items-center space-x-2 ml-auto">
                <span className="text-sm text-gray-600">Sort by:</span>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => toggleSort('name')}
                  className={sortBy === 'name' ? 'bg-gray-100' : ''}
                >
                  Name {sortBy === 'name' && (sortDirection === 'asc' ? <SortAsc className="ml-1 h-3 w-3" /> : <SortDesc className="ml-1 h-3 w-3" />)}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => toggleSort('due_date')}
                  className={sortBy === 'due_date' ? 'bg-gray-100' : ''}
                >
                  Due Date {sortBy === 'due_date' && (sortDirection === 'asc' ? <SortAsc className="ml-1 h-3 w-3" /> : <SortDesc className="ml-1 h-3 w-3" />)}
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => toggleSort('priority')}
                  className={sortBy === 'priority' ? 'bg-gray-100' : ''}
                >
                  Priority {sortBy === 'priority' && (sortDirection === 'asc' ? <SortAsc className="ml-1 h-3 w-3" /> : <SortDesc className="ml-1 h-3 w-3" />)}
                </Button>

                <div className="border-l border-gray-300 pl-2 ml-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setCurrentViewMode('list')}
                    className={currentViewMode === 'list' ? 'bg-gray-100' : ''}
                  >
                    <List className="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setCurrentViewMode('grid')}
                    className={currentViewMode === 'grid' ? 'bg-gray-100' : ''}
                  >
                    <Grid className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Task Count and Translation Summary */}
      <div className="flex items-center justify-between">
        <p className="text-sm text-gray-600">
          Showing {filteredAndSortedTasks.length} of {tasks.length} tasks
        </p>
        <div className="flex items-center gap-4 text-xs text-gray-500">
          <div className="flex items-center gap-1">
            <Globe className="h-3 w-3" />
            <span>Current: {language.toUpperCase()}</span>
          </div>
          {filteredAndSortedTasks.length > 0 && (
            <div className="flex items-center gap-2">
              <span>Translation status:</span>
              <div className="flex items-center gap-1">
                {(() => {
                  const withTranslation = filteredAndSortedTasks.filter(task => {
                    const name = typeof task.name === 'object' ? task.name : { en: task.name };
                    return name[language] && name[language].trim();
                  }).length;
                  const percentage = Math.round((withTranslation / filteredAndSortedTasks.length) * 100);
                  
                  return (
                    <>
                      <span className={percentage === 100 ? 'text-green-600' : percentage > 50 ? 'text-yellow-600' : 'text-red-600'}>
                        {withTranslation}/{filteredAndSortedTasks.length} ({percentage}%)
                      </span>
                    </>
                  );
                })()}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Tasks Display */}
      {filteredAndSortedTasks.length === 0 ? (
        <div className="text-center py-12 bg-gray-50 rounded-lg">
          <div className="text-gray-400 mb-4">
            <List className="h-12 w-12 mx-auto" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">No tasks found</h3>
          <p className="text-gray-500">
            {searchTerm || filters.status || filters.priority 
              ? 'Try adjusting your search or filters to find tasks.'
              : 'Create your first task to get started!'
            }
          </p>
        </div>
      ) : (
        <div className={currentViewMode === 'grid' 
          ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6' 
          : 'space-y-4'
        }>
          {parentTasks.map((task) => (
            <TaskCard
              key={task.id}
              task={task}
              onEdit={onEdit}
              onDelete={onDelete}
              onToggleStatus={onToggleStatus}
              onViewSubtasks={onViewSubtasks}
              showSubtasks={true}
            />
          ))}
        </div>
      )}
    </div>
  );
};

export default TaskList;