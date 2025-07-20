import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Navigation from './Navigation';
import { useAuth } from '@/contexts/AuthContext';
import { Button } from '@/components/ui/button';

const Header: React.FC = () => {
  const navigate = useNavigate();
  const { user, logout } = useAuth();

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <header className="bg-background shadow-sm border-b">
      <div className="container mx-auto px-4">
        <div className="flex items-center justify-between h-16">
          <div className="flex items-center space-x-8">
            <Link 
              to="/" 
              className="text-xl font-bold text-foreground hover:text-primary transition-colors"
            >
              Task Manager
            </Link>
            <Navigation />
          </div>
          
          <div className="flex items-center space-x-4">
            {user && (
              <span className="text-sm text-muted-foreground">
                Welcome, {user.name}
              </span>
            )}
            <Button
              variant="ghost"
              onClick={handleLogout}
              className="text-sm"
            >
              Logout
            </Button>
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;