import React from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { RegisterForm } from '@/components';

const RegisterPage: React.FC = () => {
  const navigate = useNavigate();

  const handleRegisterSuccess = () => {
    navigate('/');
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-background py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div>
          <h2 className="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
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
          <RegisterForm onSuccess={handleRegisterSuccess} />
        </div>
      </div>
    </div>
  );
};

export default RegisterPage;