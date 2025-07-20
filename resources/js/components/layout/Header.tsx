import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Navigation from './Navigation';
import { useAuth } from '@/contexts/AuthContext';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/ui/theme-toggle';

const Header: React.FC = () => {
  const navigate = useNavigate();
  const { user, logout } = useAuth();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <header className="bg-background shadow-sm border-b">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center space-x-4 sm:space-x-8">
            <Link 
              to="/" 
              className="text-lg sm:text-xl font-bold text-foreground hover:text-primary transition-colors"
            >
              <span className="hidden sm:inline">Task Manager</span>
              <span className="sm:hidden">Tasks</span>
            </Link>
            <div className="hidden md:block">
              <Navigation />
            </div>
          </div>
          
          <div className="flex items-center space-x-2 sm:space-x-4">
            {user && (
              <span className="hidden sm:inline text-sm text-muted-foreground">
                Welcome, {user.name}
              </span>
            )}
            <ThemeToggle />
            <Button
              variant="ghost"
              onClick={handleLogout}
              className="text-sm"
            >
              <span className="hidden sm:inline">Logout</span>
              <span className="sm:hidden">Exit</span>
            </Button>
          </div>
        </div>
        
        {/* Mobile Navigation */}
        <div className="md:hidden pb-4">
          <Navigation />
        </div>
      </div>
    </header>
  );
};

export default Header;