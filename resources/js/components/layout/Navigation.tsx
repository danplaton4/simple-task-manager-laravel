import React from 'react';
import { NavLink } from 'react-router-dom';
import { cn } from '@/lib/utils';

interface NavigationProps {
  onLinkClick?: () => void;
}

const Navigation: React.FC<NavigationProps> = ({ onLinkClick }) => {
  const navItems = [
    { to: '/', label: 'Dashboard' },
    { to: '/tasks', label: 'Tasks' },
  ];

  return (
    <nav className="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-6">
      {navItems.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          onClick={onLinkClick}
          className={({ isActive }) =>
            cn(
              "px-3 py-2 rounded-md text-sm font-medium transition-colors",
              isActive
                ? 'text-primary bg-primary/10'
                : 'text-muted-foreground hover:text-foreground hover:bg-accent'
            )
          }
        >
          {item.label}
        </NavLink>
      ))}
    </nav>
  );
};

export default Navigation;