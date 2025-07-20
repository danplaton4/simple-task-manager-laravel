// Layout components
export { default as Layout } from './layout/Layout';
export { default as Header } from './layout/Header';
export { default as Navigation } from './layout/Navigation';

// Task components
export { default as TaskList } from './tasks/TaskList';
export { default as TaskCard } from './tasks/TaskCard';
export { default as DraggableTaskList } from './tasks/DraggableTaskList';
export { default as DraggableTaskCard } from './tasks/DraggableTaskCard';
export { default as TaskForm } from './tasks/TaskForm';
export { default as SubtaskManager } from './tasks/SubtaskManager';

// Auth components
export { default as LoginForm } from './auth/LoginForm';
export { default as RegisterForm } from './auth/RegisterForm';
export { default as ProtectedRoute } from './auth/ProtectedRoute';

// Context exports
export { AuthProvider, useAuth } from '../contexts/AuthContext';