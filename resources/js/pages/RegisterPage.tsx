import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { RegisterData } from '@/types';
import { RegisterForm } from '@/components';
import { useAuth } from '@/contexts/AuthContext';

const RegisterPage: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string>('');
  const navigate = useNavigate();
  const { register } = useAuth();

  const handleRegister = async (data: RegisterData) => {
    setLoading(true);
    setError('');

    try {
      await register(data);
      navigate('/');
    } catch (err) {
      setError('Registration failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-background py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight">
            Create your account
          </h2>
          <p className="mt-2 text-center text-sm text-muted-foreground">
            Or{' '}
            <Link
              to="/login"
              className="font-medium text-primary hover:text-primary/80"
            >
              sign in to your existing account
            </Link>
          </p>
        </div>

        <div className="bg-card py-8 px-6 shadow-lg rounded-lg border">
          <RegisterForm
            onSubmit={handleRegister}
            loading={loading}
            error={error}
          />
        </div>
      </div>
    </div>
  );
};

export default RegisterPage;