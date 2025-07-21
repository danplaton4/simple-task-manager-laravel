# Laravel Sanctum SPA Authentication Documentation

## Overview

This document explains the proper SPA authentication implementation using Laravel Sanctum according to the official Laravel documentation: https://laravel.com/docs/12.x/sanctum#spa-authentication

## Laravel Sanctum SPA Authentication

### How Sanctum SPA Authentication Works

According to the Laravel Sanctum documentation, SPAs should use **stateful authentication** with CSRF protection:

1. **First Request**: SPA requests CSRF cookie from `/sanctum/csrf-cookie`
2. **Authentication**: Login/register requests include CSRF token in headers
3. **Session-Based**: Authentication uses session cookies (not Bearer tokens)
4. **CSRF Protection**: All requests include CSRF token to prevent CSRF attacks
5. **Stateful Domains**: Requests from configured domains receive stateful authentication

### Why SPAs Need CSRF Protection with Sanctum

**Contrary to common belief**, SPAs using Laravel Sanctum **DO need CSRF protection** because:

1. **Session-Based Authentication**: Sanctum uses session cookies for SPAs
2. **Automatic Cookie Transmission**: Browsers automatically send session cookies
3. **CSRF Vulnerability**: Without CSRF tokens, malicious sites could forge requests
4. **Stateful Authentication**: SPAs are treated as "first-party" applications

### Laravel Sanctum: Two Authentication Modes

Laravel Sanctum supports different authentication methods for different use cases:

1. **SPA Authentication (Stateful)**: Uses session cookies + CSRF protection
2. **API Authentication (Stateless)**: Uses Bearer tokens without CSRF

## Architecture Components

### 1. Backend (Laravel + Sanctum)

#### Configuration
- **Pure token-based authentication** without session middleware
- **No CSRF protection** for API routes
- **Bearer token validation** for all protected endpoints

#### Key Files:
- `bootstrap/app.php`: Middleware configuration
- `routes/auth.php`: Authentication routes
- `app/Http/Controllers/AuthController.php`: Authentication logic
- `config/sanctum.php`: Sanctum configuration

### 2. Frontend (React + TypeScript)

#### Core Components:

**AuthService (`resources/js/services/AuthService.ts`)**
- Handles all API authentication calls
- Manages token storage in localStorage
- Provides methods for login, register, logout, refresh

**AuthContext (`resources/js/contexts/AuthContext.tsx`)**
- Provides authentication state to the entire app
- Manages user data and loading states
- Handles authentication errors

**useAuthState Hook (`resources/js/hooks/useAuthState.ts`)**
- Custom hook for centralized auth state management
- Handles token validation and refresh

**Axios Configuration (`resources/js/bootstrap.js`)**
- Automatically adds Bearer tokens to requests
- Handles 401 responses with automatic token refresh
- Provides request/response interceptors

### 3. Security Features

#### Token Management
- **Automatic token attachment** to all API requests
- **Token refresh** on 401 responses
- **Token validation** on app initialization
- **Multi-tab logout** support via localStorage events

#### Error Handling
- **Graceful error handling** for network failures
- **Automatic logout** on token expiration
- **User-friendly error messages**

#### Best Practices
- **Secure token storage** in localStorage
- **Token expiration handling**
- **Request retry logic** for failed authentication
- **Proper error boundaries**

## Authentication Flow

### 1. Login Process
```typescript
// User submits login form
const credentials = { email, password };

// AuthService makes API call
const response = await AuthService.login(credentials);

// Token is stored in localStorage
localStorage.setItem('auth_token', response.token);

// User data is stored in context
setUser(response.user);
```

### 2. Authenticated Requests
```typescript
// Axios interceptor automatically adds token
config.headers.Authorization = `Bearer ${token}`;

// API validates token and processes request
// No CSRF token needed
```

### 3. Token Refresh
```typescript
// On 401 response, automatically try to refresh
const newToken = await AuthService.refreshToken();

// Retry original request with new token
return axios(originalRequest);
```

### 4. Logout Process
```typescript
// Call logout API to revoke token
await AuthService.logout();

// Clear token from localStorage
localStorage.removeItem('auth_token');

// Clear user data from context
setUser(null);
```

## API Endpoints

### Public Endpoints
- `POST /api/auth/login` - User authentication
- `POST /api/auth/register` - User registration

### Protected Endpoints
- `POST /api/auth/logout` - Logout current session
- `POST /api/auth/logout-all` - Logout all sessions
- `POST /api/auth/refresh` - Refresh authentication token
- `GET /api/auth/me` - Get current user data

## Security Considerations

### Why This Approach is Secure

1. **Same-Origin Policy**: Malicious sites cannot access localStorage
2. **Manual Token Transmission**: Tokens must be explicitly added to headers
3. **No Automatic Cookies**: No risk of automatic cookie transmission
4. **Token Expiration**: Tokens have limited lifetime
5. **Server-Side Validation**: All tokens are validated server-side

### Additional Security Measures

1. **HTTPS Only**: Always use HTTPS in production
2. **Token Rotation**: Regular token refresh
3. **Secure Headers**: Proper CORS and security headers
4. **Input Validation**: Server-side validation of all inputs
5. **Rate Limiting**: Protection against brute force attacks

## Best Practices Implemented

### Frontend
- ✅ Centralized authentication state management
- ✅ Automatic token refresh
- ✅ Proper error handling
- ✅ Loading states for better UX
- ✅ Multi-tab logout support
- ✅ Token validation on app initialization

### Backend
- ✅ Stateless authentication
- ✅ Proper token expiration
- ✅ Secure token generation
- ✅ Rate limiting on auth endpoints
- ✅ Comprehensive error responses
- ✅ Token revocation support

## Testing the Authentication

### Manual Testing
```bash
# Login
curl -X POST "http://localhost/api/auth/login" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email": "test@gmail.com", "password": "password"}'

# Use token for authenticated request
curl -X GET "http://localhost/api/auth/me" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"

# Logout
curl -X POST "http://localhost/api/auth/logout" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Frontend Testing
The React application automatically handles:
- Token storage and retrieval
- Adding tokens to requests
- Handling authentication errors
- Refreshing expired tokens
- Redirecting unauthenticated users

## Conclusion

This authentication architecture provides:
- **Security**: No CSRF vulnerabilities for SPA
- **Scalability**: Stateless token-based authentication
- **User Experience**: Seamless authentication flow
- **Maintainability**: Clean separation of concerns
- **Flexibility**: Easy to extend and modify

The implementation follows modern best practices for SPA authentication and provides a robust, secure foundation for the application.