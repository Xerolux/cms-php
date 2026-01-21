import { describe, it, expect, beforeEach, vi } from 'vitest';
import axios from 'axios';
import { useAuthStore } from '../authStore';

// Mock axios
vi.mock('axios');
const mockedAxios = axios as any;

// Mock localStorage
const localStorageMock = {
  getItem: vi.fn(),
  setItem: vi.fn(),
  removeItem: vi.fn(),
  clear: vi.fn(),
};

global.localStorage = localStorageMock as any;

describe('AuthStore', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorageMock.getItem.mockReturnValue(null);
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
    expect(localStorageMock.setItem).toHaveBeenCalledWith('auth_token', mockToken);
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
    expect(localStorageMock.removeItem).toHaveBeenCalledWith('auth_token');
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
