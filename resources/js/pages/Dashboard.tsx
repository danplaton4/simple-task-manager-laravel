import React from 'react';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTask } from '@/contexts/TaskContext';
import { useTaskOperations } from '@/hooks/useTaskOperations';
import Modal from '@/components/ui/Modal';
import TaskForm from '@/components/tasks/TaskForm';
import { Task } from '@/types';
import { useLanguage } from '@/contexts/LanguageContext';

const Dashboard: React.FC = () => {
  const { tasks, isLoading } = useTask();
  const { updateTaskWithLoading } = useTaskOperations();
  const [modalTask, setModalTask] = React.useState<Task | null>(null);
  const [modalMode, setModalMode] = React.useState<'view' | 'edit' | null>(null);
  const [modalOpen, setModalOpen] = React.useState(false);

  const { language } = useLanguage();
  const getTranslation = (field: any, lang?: string) => {
    const l = lang || language;
    if (typeof field === 'object' && field !== null && field[l]) {
      return field[l];
    }
    return typeof field === 'string' ? field : '';
  };

  const handleOpenTask = (task: Task) => {
    setModalTask(task);
    setModalMode('view');
    setModalOpen(true);
  };

  const handleEditTask = () => {
    setModalMode('edit');
  };

  const handleUpdateTask = async (taskData: any) => {
    if (!modalTask) return;
    await updateTaskWithLoading(modalTask.id, taskData);
    handleModalClose();
  };

  const handleModalClose = () => {
    setModalOpen(false);
    setTimeout(() => {
      setModalTask(null);
      setModalMode(null);
    }, 200);
  };

  // Calculate stats - filter out any incomplete tasks first
  const validTasks = tasks.filter(t => t && t.status);
  const totalTasks = validTasks.length;
  const inProgressTasks = validTasks.filter(t => t.status === 'in_progress').length;
  const completedTasks = validTasks.filter(t => t.status === 'completed').length;

  // Sort by updated_at or created_at for recent tasks - filter out incomplete tasks
  const recentTasks = [...validTasks]
    .sort((a, b) => new Date(b.updated_at || b.created_at).getTime() - new Date(a.updated_at || a.created_at).getTime())
    .slice(0, 5);

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-3xl font-bold tracking-tight text-foreground">Dashboard</h1>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Tasks</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{isLoading ? '...' : totalTasks}</div>
            <p className="text-xs text-muted-foreground">All tasks</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">In Progress</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{isLoading ? '...' : inProgressTasks}</div>
            <p className="text-xs text-muted-foreground">Active tasks</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Completed</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{isLoading ? '...' : completedTasks}</div>
            <p className="text-xs text-muted-foreground">Finished tasks</p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Recent Tasks</CardTitle>
          <CardDescription>Your latest task activity</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="text-center py-8 text-muted-foreground">Loading...</div>
          ) : recentTasks.length === 0 ? (
            <div className="text-center py-8 text-muted-foreground">
              <p>
                No tasks yet.{' '}
                <Link to="/tasks/new" className="text-primary hover:underline">
                  Create your first task
                </Link>{' '}
                to get started!
              </p>
            </div>
          ) : (
            <ul className="divide-y divide-gray-200">
              {recentTasks.map(task => (
                <li key={task.id} className="py-3 flex items-center justify-between cursor-pointer hover:bg-muted/30 rounded transition"
                  onClick={() => handleOpenTask(task)}
                >
                  <div>
                    <span className="font-medium text-primary hover:underline">
                      {getTranslation(task.name)}
                    </span>
                    <span className="ml-2 text-xs text-muted-foreground">{task.status.replace('_', ' ')}</span>
                  </div>
                  <span className="text-xs text-gray-500">{new Date(task.updated_at || task.created_at).toLocaleString()}</span>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
      <Modal
        open={modalOpen}
        onOpenChange={setModalOpen}
        title={modalMode === 'edit' ? 'Edit Task' : 'Task Details'}
        description={modalMode === 'view' ? 'View details of your task' : undefined}
        footer={modalTask && modalMode === 'view' && (
          <>
            <Button variant="outline" size="sm" onClick={handleEditTask}>Edit</Button>
            <Button variant="ghost" size="sm" onClick={handleModalClose}>Close</Button>
          </>
        )}
      >
        {modalTask && modalMode === 'view' && (
          <div>
            <h2 className="text-xl font-bold mb-2">{getTranslation(modalTask.name)}</h2>
            <p className="mb-2 text-muted-foreground">{getTranslation(modalTask.description)}</p>
            <div className="mb-2 flex flex-wrap gap-2 text-sm">
              <span>Status: <b>{modalTask.status}</b></span>
              <span>Priority: <b>{modalTask.priority}</b></span>
              {modalTask.due_date && <span>Due: <b>{modalTask.due_date}</b></span>}
            </div>
          </div>
        )}
        {modalTask && modalMode === 'edit' && (
          <TaskForm
            task={modalTask}
            onSubmit={handleUpdateTask}
            onCancel={handleModalClose}
            loading={isLoading}
          />
        )}
      </Modal>
    </div>
  );
};

export default Dashboard;