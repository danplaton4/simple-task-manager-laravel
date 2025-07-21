import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { LoginCredentials } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { 
  Form, 
  FormControl, 
  FormField, 
  FormItem, 
  FormLabel, 
  FormMessage 
} from '@/components/ui/form';
import { Spinner } from '@/components/ui/spinner';
import { useAuth } from '@/contexts/AuthContext';

interface LoginFormProps {
  onSuccess?: () => void;
}

const LoginForm: React.FC<LoginFormProps> = ({ onSuccess }) => {
  const { login, isLoading, error, clearError } = useAuth();
  
  const form = useForm<LoginCredentials>({
    defaultValues: {
      email: '',
      password: ''
    }
  });

  // Clear error when component mounts or form values change
  useEffect(() => {
    clearError();
  }, [clearError]);

  const handleSubmit = async (data: LoginCredentials) => {
    try {
      await login(data);
      onSuccess?.();
    } catch (error) {
      // Error is handled by AuthContext
      console.error('Login failed:', error);
    }
  };

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6 bg-white/90 dark:bg-white/10 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-600 dark:text-white backdrop-blur">
        {error && (
          <div className="bg-destructive/15 border border-destructive/20 text-destructive px-4 py-3 rounded-md dark:bg-red-900/30 dark:border-red-800 dark:text-red-300">
            {error}
          </div>
        )}

        <FormField
          control={form.control}
          name="email"
          rules={{
            required: 'Email is required',
            pattern: {
              value: /\S+@\S+\.\S+/,
              message: 'Email is invalid'
            }
          }}
          render={({ field }) => (
            <FormItem>
              <FormLabel>Email</FormLabel>
              <FormControl>
                <Input
                  type="email"
                  placeholder="Enter your email"
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="password"
          rules={{
            required: 'Password is required'
          }}
          render={({ field }) => (
            <FormItem>
              <FormLabel>Password</FormLabel>
              <FormControl>
                <Input
                  type="password"
                  placeholder="Enter your password"
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <Button
          type="submit"
          className="w-full"
          disabled={isLoading}
        >
          {isLoading && <Spinner size="sm" className="mr-2" />}
          Sign In
        </Button>
      </form>
    </Form>
  );
};

export default LoginForm;