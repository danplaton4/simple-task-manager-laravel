import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Menu, X } from 'lucide-react';
import Navigation from './Navigation';
import { useAuth } from '@/contexts/AuthContext';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import LanguageSwitcher from '@/components/ui/LanguageSwitcher';

const Header: React.FC = () => {
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <header className="bg-background shadow-sm border-b sticky top-0 z-40">
      <div className="container mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16">
          {/* Left Section: Logo and Desktop Navigation */}
          <div className="flex items-center space-x-4 sm:space-x-8">
            <Link 
              to="/" 
              className="text-lg sm:text-xl font-bold text-foreground hover:text-primary transition-colors"
              onClick={() => setIsMenuOpen(false)}
            >
              Task Manager
            </Link>
            <div className="hidden md:block">
              <Navigation />
            </div>
          </div>
          
          {/* Right Section: User Info, Theme, Logout, and Mobile Menu Button */}
          <div className="flex items-center space-x-2 sm:space-x-4">
            {user && (
              <span className="hidden sm:inline text-sm text-muted-foreground">
                Welcome, {user.name}
              </span>
            )}
            <ThemeToggle />
            <LanguageSwitcher />
            <Button
              variant="ghost"
              onClick={handleLogout}
              className="text-sm text-foreground"
            >
              <span className="text-foreground">Logout</span>
            </Button>
            <div className="md:hidden">
              <Button
                variant="ghost"
                size="icon"
                onClick={() => setIsMenuOpen(!isMenuOpen)}
              >
                {isMenuOpen ? <X className="h-6 w-6 text-foreground" /> : <Menu className="h-6 w-6 text-foreground" />}
                <span className="sr-only">Toggle menu</span>
              </Button>
            </div>
          </div>
        </div>
      </div>

      {/* Mobile Navigation Panel */}
      {isMenuOpen && (
        <div className="md:hidden bg-background border-t">
          <div className="container mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <Navigation onLinkClick={() => setIsMenuOpen(false)} />
          </div>
        </div>
      )}
    </header>
  );
};

export default Header;