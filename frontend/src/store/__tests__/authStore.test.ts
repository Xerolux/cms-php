import { describe, it, expect, beforeEach, vi } from 'vitest';
import axios from 'axios';
import { useAuthStore } from '../authStore';

// Mock axios
vi.mock('axios');
const mockedAxios = axios as any;

describe('AuthStore', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Clear localStorage using the global mock from setup.ts
    window.localStorage.clear();
    // Reset store state
    useAuthStore.setState({ user: null, token: null, isAuthenticated: false });
  });

  it('should initialize with empty state', () => {
    const authStore = useAuthStore.getState();

    expect(authStore.user).toBeNull();
    expect(authStore.token).toBeNull();
    expect(authStore.isAuthenticated).toBe(false);
  });

  it('should login user successfully', async () => {
    const mockUser = { id: 1, email: 'test@example.com', name: 'Test User', role: 'admin' };
    const mockToken = 'test-token';

    mockedAxios.post.mockResolvedValue({
      data: { user: mockUser, token: mockToken },
    });

    await useAuthStore.getState().login('test@example.com', 'password');

    const authStore = useAuthStore.getState();
    expect(authStore.user).toEqual(mockUser);
    expect(authStore.token).toBe(mockToken);
    expect(authStore.isAuthenticated).toBe(true);
    expect(window.localStorage.getItem('auth_token')).toBe(mockToken);
  });

  it('should handle login failure', async () => {
    mockedAxios.post.mockRejectedValue({
      response: { data: { message: 'Invalid credentials' } },
    });

    await expect(useAuthStore.getState().login('test@example.com', 'wrongpassword')).rejects.toThrow();

    const authStore = useAuthStore.getState();
    expect(authStore.isAuthenticated).toBe(false);
  });

  it('should logout user', () => {
    // Set initial state
    useAuthStore.setState({
        user: { id: 1, email: 'test@example.com' } as any,
        token: 'test-token',
        isAuthenticated: true
    });

    useAuthStore.getState().logout();

    const authStore = useAuthStore.getState();
    expect(authStore.user).toBeNull();
    expect(authStore.token).toBeNull();
    expect(authStore.isAuthenticated).toBe(false);
    expect(window.localStorage.getItem('auth_token')).toBeNull();
  });

  it('should update user profile locally', () => {
    useAuthStore.setState({
        user: { id: 1, email: 'test@example.com', name: 'Old Name' } as any
    });

    const updatedUser = { id: 1, email: 'test@example.com', name: 'New Name' };

    useAuthStore.getState().updateUser(updatedUser as any);

    const authStore = useAuthStore.getState();
    expect(authStore.user?.name).toBe('New Name');
  });

  it('should handle remember me in login', async () => {
    const mockUser = { id: 1, email: 'test@example.com', name: 'Test User' };
    const mockToken = 'test-token';

    mockedAxios.post.mockResolvedValue({
      data: { user: mockUser, token: mockToken },
    });

    await useAuthStore.getState().login('test@example.com', 'password', true);

    expect(mockedAxios.post).toHaveBeenCalledWith(expect.stringContaining('/auth/login'), {
      email: 'test@example.com',
      password: 'password',
      remember_me: true,
    });
  });
});
