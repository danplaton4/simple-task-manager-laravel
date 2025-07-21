import { useState, useCallback, useEffect } from 'react';
import { useTask } from '@/contexts/TaskContext';
import { TaskFilters, Task } from '@/types';

interface UseTaskFiltersReturn {
  // Current filter state
  activeFilters: TaskFilters;
  
  // Filter functions
  setStatusFilter: (status: Task['status'] | undefined) => void;
  setPriorityFilter: (priority: Task['priority'] | undefined) => void;
  setSearchFilter: (search: string | undefined) => void;
  setParentFilter: (parentId: number | undefined) => void;
  
  // Utility functions
  clearAllFilters: () => void;
  applyFilters: () => void;
  hasActiveFilters: boolean;
  
  // Pagination
  currentPage: number;
  totalPages: number;
  goToPage: (page: number) => void;
  nextPage: () => void;
  previousPage: () => void;
  canGoNext: boolean;
  canGoPrevious: boolean;
}

export const useTaskFilters = (): UseTaskFiltersReturn => {
  const { 
    filters, 
    pagination, 
    setFilters, 
    fetchTasks 
  } = useTask();

  const [localFilters, setLocalFilters] = useState<TaskFilters>(filters);

  // Sync local filters with context when context changes
  useEffect(() => {
    setLocalFilters(filters);
  }, [filters]);

  const setStatusFilter = useCallback((status: Task['status'] | undefined) => {
    setLocalFilters(prev => ({ ...prev, status }));
  }, []);

  const setPriorityFilter = useCallback((priority: Task['priority'] | undefined) => {
    setLocalFilters(prev => ({ ...prev, priority }));
  }, []);

  const setSearchFilter = useCallback((search: string | undefined) => {
    setLocalFilters(prev => ({ ...prev, search }));
  }, []);

  const setParentFilter = useCallback((parentId: number | undefined) => {
    setLocalFilters(prev => ({ ...prev, parent_id: parentId }));
  }, []);

  const clearAllFilters = useCallback(() => {
    const clearedFilters = {};
    setLocalFilters(clearedFilters);
    setFilters(clearedFilters);
    fetchTasks(1, clearedFilters);
  }, [setFilters, fetchTasks]);

  const applyFilters = useCallback(() => {
    setFilters(localFilters);
    fetchTasks(1, localFilters);
  }, [localFilters, setFilters, fetchTasks]);

  const hasActiveFilters = Object.keys(localFilters).some(key => {
    const value = localFilters[key as keyof TaskFilters];
    return value !== undefined && value !== '' && value !== null;
  });

  // Pagination functions
  const goToPage = useCallback((page: number) => {
    if (page >= 1 && page <= pagination.lastPage) {
      fetchTasks(page, filters);
    }
  }, [fetchTasks, filters, pagination.lastPage]);

  const nextPage = useCallback(() => {
    if (pagination.currentPage < pagination.lastPage) {
      goToPage(pagination.currentPage + 1);
    }
  }, [pagination.currentPage, pagination.lastPage, goToPage]);

  const previousPage = useCallback(() => {
    if (pagination.currentPage > 1) {
      goToPage(pagination.currentPage - 1);
    }
  }, [pagination.currentPage, goToPage]);

  const canGoNext = pagination.currentPage < pagination.lastPage;
  const canGoPrevious = pagination.currentPage > 1;

  return {
    activeFilters: localFilters,
    setStatusFilter,
    setPriorityFilter,
    setSearchFilter,
    setParentFilter,
    clearAllFilters,
    applyFilters,
    hasActiveFilters,
    currentPage: pagination.currentPage,
    totalPages: pagination.lastPage,
    goToPage,
    nextPage,
    previousPage,
    canGoNext,
    canGoPrevious
  };
};