import React, { useState } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { LoginCredentials } from '@/types';
import { LoginForm } from '@/components';
import { useAuth } from '@/contexts/AuthContext';

const LoginPage: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string>('');
  const navigate = useNavigate();
  const location = useLocation();
  const { login } = useAuth();

  const from = location.state?.from?.pathname || '/';

  const handleLogin = async (credentials: LoginCredentials) => {
    setLoading(true);
    setError('');

    try {
      await login(credentials);
      navigate(from, { replace: true });
    } catch (err) {
      setError('Invalid email or password');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-background py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight">
            Sign in to your account
          </h2>
          <p className="mt-2 text-center text-sm text-muted-foreground">
            Or{' '}
            <Link
              to="/register"
              className="font-medium text-primary hover:text-primary/80"
            >
              create a new account
            </Link>
          </p>
        </div>

        <div className="bg-card py-8 px-6 shadow-lg rounded-lg border">
          <LoginForm
            onSubmit={handleLogin}
            loading={loading}
            error={error}
          />
        </div>
      </div>
    </div>
  );
};

export default LoginPage;