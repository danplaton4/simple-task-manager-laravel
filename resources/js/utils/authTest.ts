/**
 * Authentication System Test Utilities
 * 
 * These utilities can be used to test the authentication system
 * in development or for debugging purposes.
 */

import AuthService from '@/services/AuthService';
import { LoginCredentials, RegisterData } from '@/types';

/**
 * Test the complete authentication flow
 */
export const testAuthFlow = async () => {
  console.group('🔐 Authentication System Test');
  
  try {
    // Test 1: Check initial state
    console.log('1. Initial auth state:', {
      isAuthenticated: AuthService.isAuthenticated(),
      hasToken: !!AuthService.getToken()
    });

    // Test 2: Login with test credentials
    console.log('2. Testing login...');
    const loginCredentials: LoginCredentials = {
      email: 'test@gmail.com',
      password: 'password'
    };

    const loginResponse = await AuthService.login(loginCredentials);
    console.log('✅ Login successful:', {
      user: loginResponse.user.name,
      email: loginResponse.user.email,
      tokenType: loginResponse.token_type,
      expiresAt: loginResponse.expires_at
    });

    // Test 3: Check authenticated state
    console.log('3. Post-login state:', {
      isAuthenticated: AuthService.isAuthenticated(),
      hasToken: !!AuthService.getToken()
    });

    // Test 4: Get current user
    console.log('4. Testing getCurrentUser...');
    const currentUser = await AuthService.getCurrentUser();
    console.log('✅ Current user retrieved:', {
      id: currentUser.id,
      name: currentUser.name,
      email: currentUser.email
    });

    // Test 5: Validate token
    console.log('5. Testing token validation...');
    const isValid = await AuthService.validateToken();
    console.log('✅ Token validation:', isValid);

    // Test 6: Refresh token
    console.log('6. Testing token refresh...');
    const newToken = await AuthService.refreshToken();
    console.log('✅ Token refreshed:', !!newToken);

    // Test 7: Logout
    console.log('7. Testing logout...');
    await AuthService.logout();
    console.log('✅ Logout successful');

    // Test 8: Check final state
    console.log('8. Post-logout state:', {
      isAuthenticated: AuthService.isAuthenticated(),
      hasToken: !!AuthService.getToken()
    });

    console.log('🎉 All authentication tests passed!');
    
  } catch (error) {
    console.error('❌ Authentication test failed:', error);
  } finally {
    console.groupEnd();
  }
};

/**
 * Test registration flow
 */
export const testRegistration = async () => {
  console.group('📝 Registration Test');
  
  try {
    const registerData: RegisterData = {
      name: 'Test User 2',
      email: `test${Date.now()}@example.com`, // Unique email
      password: 'password123',
      password_confirmation: 'password123',
      preferred_language: 'en',
      timezone: 'UTC'
    };

    console.log('Testing registration with:', {
      name: registerData.name,
      email: registerData.email
    });

    const response = await AuthService.register(registerData);
    console.log('✅ Registration successful:', {
      user: response.user.name,
      email: response.user.email,
      tokenType: response.token_type
    });

    // Clean up - logout after test
    await AuthService.logout();
    console.log('✅ Test cleanup completed');
    
  } catch (error) {
    console.error('❌ Registration test failed:', error);
  } finally {
    console.groupEnd();
  }
};

/**
 * Test error handling
 */
export const testErrorHandling = async () => {
  console.group('⚠️ Error Handling Test');
  
  try {
    // Test invalid login
    console.log('1. Testing invalid login...');
    try {
      await AuthService.login({
        email: 'invalid@example.com',
        password: 'wrongpassword'
      });
      console.error('❌ Should have thrown an error');
    } catch (error) {
      console.log('✅ Invalid login properly rejected:', (error as Error).message);
    }

    // Test invalid token
    console.log('2. Testing with invalid token...');
    localStorage.setItem('auth_token', 'invalid-token');
    
    try {
      await AuthService.getCurrentUser();
      console.error('❌ Should have thrown an error');
    } catch (error) {
      console.log('✅ Invalid token properly rejected');
    }

    // Clean up
    AuthService.clearAuth();
    console.log('✅ Error handling tests completed');
    
  } catch (error) {
    console.error('❌ Error handling test failed:', error);
  } finally {
    console.groupEnd();
  }
};

/**
 * Run all authentication tests
 */
export const runAllAuthTests = async () => {
  console.log('🚀 Starting comprehensive authentication tests...');
  
  await testAuthFlow();
  await testRegistration();
  await testErrorHandling();
  
  console.log('✨ All authentication tests completed!');
};

// Make tests available globally in development
if (import.meta.env.DEV) {
  (window as any).authTests = {
    testAuthFlow,
    testRegistration,
    testErrorHandling,
    runAllAuthTests
  };
  
  console.log('🔧 Auth tests available globally: window.authTests');
}