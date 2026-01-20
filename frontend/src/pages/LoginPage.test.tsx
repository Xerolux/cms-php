import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { describe, it, expect, vi } from 'vitest';
import LoginPage from './LoginPage';

// Mock the auth store
vi.mock('../store/authStore', () => ({
  useAuthStore: () => ({
    login: vi.fn(),
    isLoading: false,
    error: null,
    isAuthenticated: false,
  }),
}));

// Mock the API service
vi.mock('../services/api', () => ({
  authService: {
    login: vi.fn(),
  },
}));

describe('LoginPage', () => {
  it('renders login form', () => {
    render(
      <BrowserRouter>
        <LoginPage />
      </BrowserRouter>
    );

    // Check for main title
    expect(screen.getByText(/Admin Login/i)).toBeInTheDocument();

    // Check for inputs by placeholder or label
    expect(screen.getByPlaceholderText(/deine@email.com/i)).toBeInTheDocument();
    expect(screen.getByPlaceholderText(/Dein Passwort/i)).toBeInTheDocument();

    // Check for submit button
    expect(screen.getByRole('button', { name: /Einloggen/i })).toBeInTheDocument();

    // Check for default credential hint
    expect(screen.getByText(/admin@example.com/i)).toBeInTheDocument();
  });
});
