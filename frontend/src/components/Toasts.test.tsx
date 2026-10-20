import { act, fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../api/client';
import { ErrorMessage } from './ErrorMessage';
import { TOAST_DURATION_MS, ToastProvider, useToasts } from './Toasts';

beforeEach(() => { vi.useFakeTimers(); });
afterEach(() => { vi.useRealTimers(); });

function Trigger({ messages }: { messages: string[] }) {
  const { showError } = useToasts();
  return <button onClick={() => messages.forEach((m) => showError(m))}>show</button>;
}
const renderToasts = (messages: string[]) => render(<ToastProvider><Trigger messages={messages} /></ToastProvider>);

describe('toasts', () => {
  it('shows several messages at once, each with a cross, and dismisses one by its cross', () => {
    renderToasts(['První chyba', 'Druhá chyba']);
    fireEvent.click(screen.getByText('show'));
    const alerts = screen.getAllByRole('alert');
    expect(alerts.map((a) => a.textContent?.replace('close', ''))).toEqual(['První chyba', 'Druhá chyba']);

    fireEvent.click(screen.getAllByRole('button', { name: 'Zavřít oznámení' })[0]);
    act(() => { vi.advanceTimersByTime(300); }); // doběhne animace odjezdu
    expect(screen.queryByText('První chyba')).not.toBeInTheDocument();
    expect(screen.getByText('Druhá chyba')).toBeInTheDocument();
  });

  it('disappears by itself after a while', () => {
    renderToasts(['Zmizím']);
    fireEvent.click(screen.getByText('show'));
    act(() => { vi.advanceTimersByTime(TOAST_DURATION_MS - 100); });
    expect(screen.getByText('Zmizím')).toBeInTheDocument();
    act(() => { vi.advanceTimersByTime(500); });
    expect(screen.queryByText('Zmizím')).not.toBeInTheDocument();
  });

  it('does not stack the same message twice and stays while the mouse is over it', () => {
    renderToasts(['Stejná', 'Stejná']);
    fireEvent.click(screen.getByText('show'));
    expect(screen.getAllByRole('alert')).toHaveLength(1);

    fireEvent.mouseEnter(screen.getByRole('alert'));
    act(() => { vi.advanceTimersByTime(TOAST_DURATION_MS * 2); });
    expect(screen.getByText('Stejná')).toBeInTheDocument();
    fireEvent.mouseLeave(screen.getByRole('alert'));
    act(() => { vi.advanceTimersByTime(TOAST_DURATION_MS + 500); });
    expect(screen.queryByText('Stejná')).not.toBeInTheDocument();
  });
});

describe('ErrorMessage', () => {
  const apiError = new ApiError(422, 'Nelze odebrat jediného admina.', [{ field: 'name', message: 'Detail 1' }]);

  function Page({ inline = false }: { inline?: boolean }) {
    const [error, setError] = useState<unknown>(null);
    return <><button onClick={() => setError(apiError)}>fail</button><ErrorMessage error={error} inline={inline} /></>;
  }

  it('shows an API error as a toast with its violations and renders nothing in the page', () => {
    const { container } = render(<ToastProvider><Page /></ToastProvider>);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    fireEvent.click(screen.getByText('fail'));
    expect(screen.getByRole('alert')).toHaveTextContent('Nelze odebrat jediného admina.');
    expect(screen.getByText('Detail 1')).toBeInTheDocument();
    expect(container.querySelector('.error')).toBeNull();
  });

  it('keeps the message on the page in the inline variant', () => {
    const { container } = render(<ToastProvider><Page inline /></ToastProvider>);
    fireEvent.click(screen.getByText('fail'));
    expect(container.querySelector('.error')).toHaveTextContent('Nelze odebrat jediného admina.');
    expect(container.querySelector('.toast')).toBeNull();
  });

  it('uses a generic text for an unknown error', () => {
    function Unknown() { return <ErrorMessage error={new Error('boom')} />; }
    render(<ToastProvider><Unknown /></ToastProvider>);
    expect(screen.getByRole('alert')).toHaveTextContent('Něco se pokazilo');
  });
});
