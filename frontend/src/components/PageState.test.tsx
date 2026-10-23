import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { ApiError } from '../api/client';
import { ToastProvider } from './Toasts';
import { EmptyState, isNotFound, LoadingOrError } from './PageState';

describe('PageState', () => {
  it('recognises only 404 and 403 as "not found"', () => {
    expect(isNotFound(new ApiError(404, 'x', []))).toBe(true);
    expect(isNotFound(new ApiError(403, 'x', []))).toBe(true);
    expect(isNotFound(new ApiError(500, 'x', []))).toBe(false);
    expect(isNotFound(new Error('x'))).toBe(false);
    expect(isNotFound(null)).toBe(false);
  });

  it('renders the message with a back link', () => {
    render(<MemoryRouter><EmptyState message="Nic tu není." backTo="/games" backLabel="Zpět" /></MemoryRouter>);
    expect(screen.getByText('Nic tu není.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Zpět' })).toHaveAttribute('href', '/games');
  });

  it('shows the skeleton while loading and the error message when it failed', () => {
    const { rerender } = render(<ToastProvider><LoadingOrError error={null} rows={2} /></ToastProvider>);
    expect(screen.getByLabelText('Načítám')).toBeInTheDocument();
    rerender(<ToastProvider><LoadingOrError error={new ApiError(500, 'Selhalo to', [])} /></ToastProvider>);
    expect(screen.getByRole('alert')).toHaveTextContent('Selhalo to');
  });
});
