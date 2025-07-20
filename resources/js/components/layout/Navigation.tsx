import React from 'react';
import { NavLink } from 'react-router-dom';
import { cn } from '@/lib/utils';

const Navigation: React.FC = () => {
  const navItems = [
    { to: '/', label: 'Dashboard' },
    { to: '/tasks', label: 'Tasks' },
    { to: '/tasks/new', label: 'New Task' },
  ];

  return (
    <nav className="flex space-x-6">
      {navItems.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
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