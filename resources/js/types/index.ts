// Core application types
export interface User {
  id: number;
  name: string;
  email: string;
  preferred_language: string;
  timezone: string;
  created_at: string;
  updated_at: string;
}

export type Translations = {
  [key: string]: string;
};

export interface Task {
  id: number;
  name: string | Translations;
  description?: string | Translations;
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  due_date?: string;
  parent_id?: number;
  user_id: number;
  subtasks?: Task[];
  created_at: string;
  updated_at: string;
}

export interface TaskFormData {
  name: Translations;
  description?: Translations;
  status: Task['status'];
  priority: Task['priority'];
  due_date?: string;
  parent_id?: number;
}

export interface TaskFilters {
  status?: Task['status'];
  priority?: Task['priority'];
  search?: string;
  parent_id?: number;
}

export interface AuthResponse {
  user: User;
  message: string;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface RegisterData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  preferred_language?: string;
  timezone?: string;
}

export interface ApiError {
  message: string;
  code?: string;
  details?: any;
}

export interface ApiResponse<T> {
  data: T;
  message?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface LoadingState {
  isLoading: boolean;
  error: string | null;
}

export interface OptimisticUpdate<T> {
  id: number;
  data: Partial<T>;
  timestamp: number;
}

export interface BulkUpdateRequest {
  id: number;
  data: Partial<TaskFormData>;
}

// Locale-related types
export type Language = 'en' | 'fr' | 'de';

export interface LocaleInfo {
  locale: string;
  user_preference: string | null;
  available_locales: Record<string, string>;
}

export interface LocalePreferenceResponse {
  locale: string;
  message: string;
}