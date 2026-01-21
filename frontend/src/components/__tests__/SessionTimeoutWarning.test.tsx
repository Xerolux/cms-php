import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SessionTimeoutWarning from '../SessionTimeoutWarning'; // Default import
import { useAuthStore } from '../../store/authStore';
import { MemoryRouter } from 'react-router-dom';

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

describe('SessionTimeoutWarning', () => {
  const logoutMock = vi.fn();

  beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    (useAuthStore as any).mockReturnValue({
      logout: logoutMock,
      isAuthenticated: true,
    });
  });

  afterEach(() => {
    vi.runOnlyPendingTimers();
    vi.useRealTimers();
  });

  const renderComponent = (props: any = {}) => {
    return render(
      <MemoryRouter>
        <SessionTimeoutWarning timeout={30} warningTime={5} enabled={true} {...props} />
      </MemoryRouter>
    );
  };

  it('should not show warning when timeout is far away', () => {
    renderComponent();
    expect(screen.queryByText(/Session Timeout Warnung/i)).not.toBeInTheDocument();
  });

  it('should show warning before timeout', async () => {
    renderComponent();

    await act(async () => {
       vi.advanceTimersByTime(25 * 60 * 1000 + 100);
    });

    expect(screen.getByText(/Session Timeout Warnung/i)).toBeInTheDocument();
  });

  it('should not show warning when disabled', async () => {
    renderComponent({ enabled: false });

    await act(async () => {
        vi.advanceTimersByTime(25 * 60 * 1000 + 100);
    });

    expect(screen.queryByText(/Session Timeout Warnung/i)).not.toBeInTheDocument();
  });

  it('should display countdown timer', async () => {
    renderComponent();

    await act(async () => {
        vi.advanceTimersByTime(25 * 60 * 1000 + 100);
    });

    expect(screen.getByText(/Verbleibende Zeit:/i)).toBeInTheDocument();
  });

  it.skip('should reset timer on user activity', async () => {
    renderComponent();

    await act(async () => {
        vi.advanceTimersByTime(10 * 60 * 1000);
    });

    await act(async () => {
        window.dispatchEvent(new MouseEvent('mousemove'));
    });

    await act(async () => {
        vi.advanceTimersByTime(15 * 60 * 1000);
    });

    expect(screen.queryByText(/Session Timeout Warnung/i)).not.toBeInTheDocument();

    await act(async () => {
        vi.advanceTimersByTime(10 * 60 * 1000 + 100);
    });
    expect(screen.getByText(/Session Timeout Warnung/i)).toBeInTheDocument();
  });

  it('should call logout when timer expires', async () => {
    renderComponent();

    await act(async () => {
        vi.advanceTimersByTime(25 * 60 * 1000 + 100);
    });
    expect(screen.getByText(/Session Timeout Warnung/i)).toBeInTheDocument();

    await act(async () => {
        vi.advanceTimersByTime(5 * 60 * 1000 + 1000);
    });

    expect(logoutMock).toHaveBeenCalled();
    expect(mockNavigate).toHaveBeenCalledWith('/login');
  });

  it.skip('should extend session when button is clicked', async () => {
    global.fetch = vi.fn().mockResolvedValue({ ok: true });

    renderComponent();

    await act(async () => {
        vi.advanceTimersByTime(25 * 60 * 1000 + 100);
    });

    const extendButtons = screen.getAllByText(/Session Verlängern/i);
    const extendButton = extendButtons.find(el => el.tagName === 'SPAN' || el.tagName === 'BUTTON')!.closest('button');

    await userEvent.click(extendButton!);

    expect(global.fetch).toHaveBeenCalled();
  });

  it.skip('should logout immediately when logout button is clicked', async () => {
    renderComponent();

    await act(async () => {
        vi.advanceTimersByTime(25 * 60 * 1000 + 100);
    });

    const logoutButton = screen.getAllByText(/Jetzt Abmelden/i).find(el => el.closest('button'))!;
    await userEvent.click(logoutButton);

    expect(logoutMock).toHaveBeenCalled();
  });

  it('should show warning modal when expired', async () => {
    renderComponent();

    await act(async () => {
        vi.advanceTimersByTime(30 * 60 * 1000 + 1000);
    });

    expect(screen.getByText(/Session Abgelaufen/i)).toBeInTheDocument();
  });
});
