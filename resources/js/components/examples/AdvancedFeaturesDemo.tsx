import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import { useNotifications } from '@/components/ui/notification';
import { useResponsive } from '@/hooks/useResponsive';
import { useTheme } from '@/contexts/ThemeContext';
import { 
  Smartphone, 
  Tablet, 
  Monitor, 
  Palette, 
  Bell, 
  Move3D,
  Wifi,
  Sun,
  Moon,
  MonitorSpeaker
} from 'lucide-react';

const AdvancedFeaturesDemo: React.FC = () => {
  const { addNotification } = useNotifications();
  const { isMobile, isTablet, isDesktop, screenSize } = useResponsive();
  const { theme, actualTheme } = useTheme();
  const [dragDemo, setDragDemo] = useState(['Item 1', 'Item 2', 'Item 3']);

  const showNotification = (type: 'success' | 'error' | 'info' | 'warning') => {
    const messages = {
      success: { title: 'Success!', message: 'Operation completed successfully' },
      error: { title: 'Error!', message: 'Something went wrong' },
      info: { title: 'Info', message: 'Here is some information' },
      warning: { title: 'Warning!', message: 'Please be careful' }
    };

    addNotification({
      type,
      ...messages[type],
      duration: 4000
    });
  };

  const simulateWebSocketUpdate = () => {
    addNotification({
      type: 'info',
      title: 'Real-time Update',
      message: 'Task "Sample Task" has been updated by another user',
      duration: 5000
    });
  };

  const getDeviceIcon = () => {
    if (isMobile) return <Smartphone className="h-5 w-5" />;
    if (isTablet) return <Tablet className="h-5 w-5" />;
    return <Monitor className="h-5 w-5" />;
  };

  const getThemeIcon = () => {
    switch (theme) {
      case 'light':
        return <Sun className="h-5 w-5" />;
      case 'dark':
        return <Moon className="h-5 w-5" />;
      case 'system':
        return <MonitorSpeaker className="h-5 w-5" />;
    }
  };

  return (
    <div className="space-y-6 p-4">
      <div className="text-center">
        <h2 className="text-2xl font-bold mb-2">Advanced UI Features Demo</h2>
        <p className="text-muted-foreground">
          Showcasing the implemented advanced features
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {/* Theme Management */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Palette className="h-5 w-5" />
              Dark Mode & Theming
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-sm">Current theme:</span>
              <div className="flex items-center gap-2">
                {getThemeIcon()}
                <span className="text-sm capitalize">{theme}</span>
              </div>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm">Active theme:</span>
              <span className="text-sm capitalize">{actualTheme}</span>
            </div>
            <div className="flex justify-center">
              <ThemeToggle />
            </div>
            <p className="text-xs text-muted-foreground text-center">
              Click to cycle through light, dark, and system themes
            </p>
          </CardContent>
        </Card>

        {/* Responsive Design */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              {getDeviceIcon()}
              Responsive Design
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <div className="flex justify-between">
                <span className="text-sm">Screen size:</span>
                <span className="text-sm">{screenSize.width}×{screenSize.height}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-sm">Device type:</span>
                <span className="text-sm">
                  {isMobile ? 'Mobile' : isTablet ? 'Tablet' : 'Desktop'}
                </span>
              </div>
            </div>
            <div className="grid grid-cols-3 gap-2 text-xs">
              <div className={`p-2 rounded text-center ${isMobile ? 'bg-primary text-primary-foreground' : 'bg-muted'}`}>
                Mobile
              </div>
              <div className={`p-2 rounded text-center ${isTablet ? 'bg-primary text-primary-foreground' : 'bg-muted'}`}>
                Tablet
              </div>
              <div className={`p-2 rounded text-center ${isDesktop ? 'bg-primary text-primary-foreground' : 'bg-muted'}`}>
                Desktop
              </div>
            </div>
            <p className="text-xs text-muted-foreground text-center">
              Resize your browser to see responsive changes
            </p>
          </CardContent>
        </Card>

        {/* Real-time Notifications */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Bell className="h-5 w-5" />
              Real-time Notifications
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-2 gap-2">
              <Button
                size="sm"
                variant="outline"
                onClick={() => showNotification('success')}
                className="text-xs"
              >
                Success
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => showNotification('error')}
                className="text-xs"
              >
                Error
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => showNotification('info')}
                className="text-xs"
              >
                Info
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() => showNotification('warning')}
                className="text-xs"
              >
                Warning
              </Button>
            </div>
            <Button
              size="sm"
              variant="default"
              onClick={simulateWebSocketUpdate}
              className="w-full"
            >
              <Wifi className="h-4 w-4 mr-2" />
              Simulate WebSocket Update
            </Button>
            <p className="text-xs text-muted-foreground text-center">
              Notifications appear in the top-right corner
            </p>
          </CardContent>
        </Card>

        {/* Drag and Drop Demo */}
        <Card className="md:col-span-2 lg:col-span-3">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Move3D className="h-5 w-5" />
              Drag & Drop Functionality
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">
                The task list supports drag-and-drop reordering when "Custom Order" sorting is selected.
                This demo shows a simplified version:
              </p>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {dragDemo.map((item, index) => (
                  <div
                    key={item}
                    className="p-4 bg-card border rounded-lg cursor-move hover:shadow-md transition-shadow"
                    draggable
                    onDragStart={(e) => e.dataTransfer.setData('text/plain', index.toString())}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                      e.preventDefault();
                      const draggedIndex = parseInt(e.dataTransfer.getData('text/plain'));
                      const newItems = [...dragDemo];
                      const [draggedItem] = newItems.splice(draggedIndex, 1);
                      newItems.splice(index, 0, draggedItem);
                      setDragDemo(newItems);
                    }}
                  >
                    <div className="flex items-center gap-2">
                      <Move3D className="h-4 w-4 text-muted-foreground" />
                      <span>{item}</span>
                    </div>
                  </div>
                ))}
              </div>
              <p className="text-xs text-muted-foreground">
                Try dragging the items above to reorder them. In the actual task list, 
                this works with the @dnd-kit library for better accessibility and touch support.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Feature Summary</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <h4 className="font-semibold mb-2">✅ Implemented Features:</h4>
              <ul className="space-y-1 text-sm text-muted-foreground">
                <li>• Dark mode toggle with system theme support</li>
                <li>• Responsive design for mobile, tablet, and desktop</li>
                <li>• Real-time notifications system</li>
                <li>• Drag-and-drop task reordering</li>
                <li>• WebSocket service for real-time updates</li>
                <li>• Theme persistence in localStorage</li>
                <li>• Responsive navigation and layouts</li>
              </ul>
            </div>
            <div>
              <h4 className="font-semibold mb-2">🔧 Technical Implementation:</h4>
              <ul className="space-y-1 text-sm text-muted-foreground">
                <li>• React Context for theme management</li>
                <li>• @dnd-kit for accessible drag-and-drop</li>
                <li>• Tailwind CSS with dark mode support</li>
                <li>• Custom hooks for responsive design</li>
                <li>• WebSocket service with reconnection logic</li>
                <li>• Notification system with auto-dismiss</li>
                <li>• Optimistic updates for better UX</li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
};

export default AdvancedFeaturesDemo;