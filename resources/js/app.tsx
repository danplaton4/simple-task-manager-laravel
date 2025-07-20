import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import './bootstrap';
import '../css/app.css';

// Context
import { AuthProvider, useAuth } from '@/contexts/AuthContext';

// Layout
import { Layout, ProtectedRoute } from '@/components';
import { Spinner } from '@/components/ui/spinner';

// Pages
import Dashboard from '@/pages/Dashboard';
import TasksPage from '@/pages/TasksPage';
import NewTaskPage from '@/pages/NewTaskPage';
import LoginPage from '@/pages/LoginPage';
import RegisterPage from '@/pages/RegisterPage';

function AppRoutes() {
    const { isAuthenticated, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <Spinner size="lg" />
            </div>
        );
    }

    return (
        <Routes>
            {/* Public routes */}
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />
            
            {/* Protected routes */}
            <Route path="/" element={
                <ProtectedRoute isAuthenticated={isAuthenticated}>
                    <Layout />
                </ProtectedRoute>
            }>
                <Route index element={<Dashboard />} />
                <Route path="tasks" element={<TasksPage />} />
                <Route path="tasks/new" element={<NewTaskPage />} />
            </Route>
            
            {/* Catch all route */}
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}

function App() {
    return (
        <AuthProvider>
            <Router>
                <AppRoutes />
            </Router>
        </AuthProvider>
    );
}

const container = document.getElementById('app');
if (container) {
    const root = createRoot(container);
    root.render(<App />);
}