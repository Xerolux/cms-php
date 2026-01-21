import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import LoginPage from '../LoginPage'; // Default import
import { useAuthStore } from '../../store/authStore';

// Mock authStore
vi.mock('../../store/authStore', () => ({
  useAuthStore: vi.fn(),
}));

// Mock navigate
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

describe('LoginPage', () => {
  const loginMock = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    (useAuthStore as any).mockReturnValue({
      login: loginMock,
      isAuthenticated: false,
      error: null,
    });
  });

  const renderLoginPage = () => {
    return render(
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    );
  };

  it('should render login form', () => {
    renderLoginPage();

    expect(screen.getByLabelText(/Email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Passwort/i)).toBeInTheDocument(); // German label
    expect(screen.getByRole('button', { name: /Einloggen/i })).toBeInTheDocument(); // German label
  });

  it('should show validation errors for empty fields', async () => {
    renderLoginPage();

    const loginButton = screen.getByRole('button', { name: /Einloggen/i });
    fireEvent.click(loginButton);

    await waitFor(() => {
      expect(screen.getByText(/Bitte Email eingeben/i)).toBeInTheDocument();
      expect(screen.getByText(/Bitte Passwort eingeben/i)).toBeInTheDocument();
    });
  });

  it('should show validation error for invalid email', async () => {
    renderLoginPage();

    const emailInput = screen.getByLabelText(/Email/i);
    fireEvent.change(emailInput, { target: { value: 'invalidemail' } });

    const loginButton = screen.getByRole('button', { name: /Einloggen/i });
    fireEvent.click(loginButton);

    await waitFor(() => {
      expect(screen.getByText(/Ungültige Email/i)).toBeInTheDocument();
    });
  });

  it('should submit login form with valid credentials', async () => {
    loginMock.mockResolvedValue({ user: { id: 1, email: 'test@example.com' } });

    renderLoginPage();

    const emailInput = screen.getByLabelText(/Email/i);
    const passwordInput = screen.getByLabelText(/Passwort/i);

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.change(passwordInput, { target: { value: 'password123' } });

    const loginButton = screen.getByRole('button', { name: /Einloggen/i });
    fireEvent.click(loginButton);

    await waitFor(() => {
      expect(loginMock).toHaveBeenCalledWith('test@example.com', 'password123', false);
    });
  });

  it('should remember me checkbox', async () => {
    loginMock.mockResolvedValue({ user: { id: 1, email: 'test@example.com' } });

    renderLoginPage();

    const rememberMeCheckbox = screen.getByRole('checkbox', { name: /Angemeldet bleiben/i });
    expect(rememberMeCheckbox).toBeInTheDocument();

    fireEvent.click(rememberMeCheckbox);

    const emailInput = screen.getByLabelText(/Email/i);
    const passwordInput = screen.getByLabelText(/Passwort/i);

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.change(passwordInput, { target: { value: 'password123' } });

    const loginButton = screen.getByRole('button', { name: /Einloggen/i });
    fireEvent.click(loginButton);

    await waitFor(() => {
      expect(loginMock).toHaveBeenCalledWith('test@example.com', 'password123', true);
    });
  });

  it('should display security alert for remember me', () => {
    renderLoginPage();

    expect(screen.getByText(/Sicherheitshinweis/i)).toBeInTheDocument();
    expect(screen.getByText(/Die "Angemeldet bleiben" Funktion speichert ein sicheres Token/i)).toBeInTheDocument();
  });

  it.skip('should show loading state during login', async () => {
    loginMock.mockImplementation(() => new Promise(() => {})); // Never resolves

    renderLoginPage();

    const emailInput = screen.getByLabelText(/Email/i);
    const passwordInput = screen.getByLabelText(/Passwort/i);

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.change(passwordInput, { target: { value: 'password123' } });

    const loginButton = screen.getByRole('button', { name: /Einloggen/i });
    fireEvent.click(loginButton);

    await waitFor(() => {
      expect(loginButton).toBeDisabled();
    });
  });

  it('should handle login failure', async () => {
    loginMock.mockRejectedValue({
      response: { data: { message: 'Invalid credentials' } },
    });

    renderLoginPage();

    const emailInput = screen.getByLabelText(/Email/i);
    const passwordInput = screen.getByLabelText(/Passwort/i);

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.change(passwordInput, { target: { value: 'wrongpassword' } });

    const loginButton = screen.getByRole('button', { name: /Einloggen/i });
    fireEvent.click(loginButton);

    await waitFor(() => {
      expect(screen.getByText(/Invalid credentials/i)).toBeInTheDocument();
    });
  });

  it('should have link to forgot password page', async () => {
    renderLoginPage();

    const forgotPasswordLink = screen.getByText(/Passwort vergessen/i);
    expect(forgotPasswordLink).toBeInTheDocument();
    // Use closest 'button' because Ant Design might wrap it in a button, or just check that click triggers navigate
    fireEvent.click(forgotPasswordLink);
    expect(mockNavigate).toHaveBeenCalledWith('/forgot-password');
  });

  it('should have link to register page', async () => {
    renderLoginPage();

    const registerLink = screen.getByText(/Registrieren/i);
    expect(registerLink).toBeInTheDocument();
    fireEvent.click(registerLink);
    expect(mockNavigate).toHaveBeenCalledWith('/register');
  });

  it('should have link to home page', async () => {
    renderLoginPage();

    const homeLink = screen.getByText(/Startseite/i);
    expect(homeLink).toBeInTheDocument();
    fireEvent.click(homeLink);
    expect(mockNavigate).toHaveBeenCalledWith('/');
  });
});
