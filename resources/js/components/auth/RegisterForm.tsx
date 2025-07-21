import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { RegisterData } from '@/types';
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useAuth } from '@/contexts/AuthContext';
import { useNotifications } from '@/components/ui/notification';

interface RegisterFormProps {
  onSuccess?: () => void;
}

const RegisterForm: React.FC<RegisterFormProps> = ({ onSuccess }) => {
  const { register, isLoading, error, fieldErrors, clearError } = useAuth();
  const { addNotification } = useNotifications();
  
  const form = useForm<RegisterData>({
    defaultValues: {
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      preferred_language: 'en',
      timezone: 'UTC'
    }
  });

  // Clear error when component mounts or form values change
  useEffect(() => {
    clearError();
  }, [clearError]);

  // Set backend field errors on the form when they change
  useEffect(() => {
    if (fieldErrors) {
      Object.entries(fieldErrors).forEach(([field, messages]) => {
        form.setError(field as keyof RegisterData, {
          type: 'server',
          message: messages.join(' ')
        });
      });
    }
  }, [fieldErrors, form]);

  // Show toast for non-field errors
  useEffect(() => {
    if (error && !fieldErrors) {
      addNotification({
        type: 'error',
        title: 'Registration Failed',
        message: error,
        duration: 5000
      });
    }
  }, [error, fieldErrors, addNotification]);

  const handleSubmit = async (data: RegisterData) => {
    try {
      await register(data);
      onSuccess?.();
    } catch (error) {
      // Error is handled by AuthContext and field errors are set via useEffect
      // Only log unexpected errors
      if (!(error && (error as any).errors)) {
        console.error('Registration failed:', error);
      }
    }
  };

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(handleSubmit)} className="space-y-6 bg-white/90 dark:bg-white/10 p-8 rounded-xl shadow-lg border border-gray-200 dark:border-gray-600 dark:text-white backdrop-blur">
        {/* Show only non-field errors here */}
        {error && !fieldErrors && (
          <div className="bg-destructive/15 border border-destructive/20 text-destructive px-4 py-3 rounded-md dark:bg-red-900/30 dark:border-red-800 dark:text-red-300">
            {error}
          </div>
        )}

        <FormField
          control={form.control}
          name="name"
          rules={{
            required: 'Name is required'
          }}
          render={({ field }) => (
            <FormItem>
              <FormLabel>Full Name</FormLabel>
              <FormControl>
                <Input
                  placeholder="Enter your full name"
                  {...field}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

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

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <FormField
            control={form.control}
            name="password"
            rules={{
              required: 'Password is required',
              minLength: {
                value: 8,
                message: 'Password must be at least 8 characters'
              }
            }}
            render={({ field }) => (
              <FormItem>
                <FormLabel>Password</FormLabel>
                <FormControl>
                  <Input
                    type="password"
                    placeholder="Enter your password"
                    autoComplete="new-password"
                    aria-invalid={!!form.formState.errors.password}
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />

          <FormField
            control={form.control}
            name="password_confirmation"
            rules={{
              required: 'Please confirm your password',
              validate: (value) => {
                const password = form.getValues('password');
                return value === password || 'Passwords do not match';
              }
            }}
            render={({ field }) => (
              <FormItem>
                <FormLabel>Confirm Password</FormLabel>
                <FormControl>
                  <Input
                    type="password"
                    placeholder="Confirm your password"
                    autoComplete="new-password"
                    aria-invalid={!!form.formState.errors.password_confirmation}
                    {...field}
                  />
                </FormControl>
                <FormMessage />
              </FormItem>
            )}
          />
        </div>

        <FormField
          control={form.control}
          name="preferred_language"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Preferred Language</FormLabel>
              <Select onValueChange={field.onChange} defaultValue={field.value}>
                <FormControl>
                  <SelectTrigger>
                    <SelectValue placeholder="Select a language" />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  <SelectItem value="en">English</SelectItem>
                  <SelectItem value="fr">Français</SelectItem>
                  <SelectItem value="de">Deutsch</SelectItem>
                </SelectContent>
              </Select>
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
          Create Account
        </Button>
      </form>
    </Form>
  );
};

export default RegisterForm;