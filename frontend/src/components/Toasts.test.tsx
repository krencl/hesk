import { act, fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '../api/client';
import { ErrorMessage } from './ErrorMessage';
import { TOAST_BASE_MS, toastDuration, ToastProvider, useToasts } from './Toasts';

beforeEach(() => { vi.useFakeTimers(); });
afterEach(() => { vi.useRealTimers(); });

function Trigger({ messages }: { messages: string[] }) {
  const { showError } = useToasts();
  return <button onClick={() => messages.forEach((m) => showError(m))}>show</button>;
}
const renderToasts = (messages: string[]) => render(<ToastProvider><Trigger messages={messages} /></ToastProvider>);

function Detailed() {
  const { showError } = useToasts();
  return <button onClick={() => { showError('Bez detailů'); showError('S detaily', ['a', 'b']); }}>go</button>;
}

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
    act(() => { vi.advanceTimersByTime(TOAST_BASE_MS - 100); });
    expect(screen.getByText('Zmizím')).toBeInTheDocument();
    act(() => { vi.advanceTimersByTime(500); });
    expect(screen.queryByText('Zmizím')).not.toBeInTheDocument();
  });

  it('shows a countdown bar that matches the display time, longer for messages with details', () => {
    const { container } = render(<ToastProvider><Detailed /></ToastProvider>);
    fireEvent.click(screen.getByText('go'));
    const toasts = [...container.ownerDocument.querySelectorAll<HTMLElement>('.toast')];
    expect(toasts.map((t) => t.style.getPropertyValue('--toast-duration'))).toEqual([`${toastDuration(0)}ms`, `${toastDuration(2)}ms`]);
    expect(toasts.every((t) => t.querySelector('.toast-bar'))).toBe(true);
    expect(toastDuration(2)).toBeGreaterThan(toastDuration(0));
  });

  it('collapses the dismissed toast in its slot first, so the others slide up instead of jumping', () => {
    renderToasts(['A', 'B']);
    fireEvent.click(screen.getByText('show'));
    fireEvent.click(screen.getAllByRole('button', { name: 'Zavřít oznámení' })[0]);
    const slots = document.querySelectorAll('.toast-slot');
    expect(slots[0]).toHaveClass('leaving');
    expect(slots[1]).not.toHaveClass('leaving');
    expect(screen.getByText('A')).toBeInTheDocument(); // ještě v DOM, dokud nedoběhne animace
    act(() => { vi.advanceTimersByTime(300); });
    expect(screen.queryByText('A')).not.toBeInTheDocument();
  });

  it('pauses the countdown bar together with the timer', () => {
    renderToasts(['Pauza']);
    fireEvent.click(screen.getByText('show'));
    const toast = screen.getByRole('alert');
    expect(toast).not.toHaveClass('paused');
    fireEvent.mouseEnter(toast);
    expect(toast).toHaveClass('paused');
    fireEvent.mouseLeave(toast);
    expect(toast).not.toHaveClass('paused');
  });

  it('does not stack the same message twice and stays while the mouse is over it', () => {
    renderToasts(['Stejná', 'Stejná']);
    fireEvent.click(screen.getByText('show'));
    expect(screen.getAllByRole('alert')).toHaveLength(1);

    fireEvent.mouseEnter(screen.getByRole('alert'));
    act(() => { vi.advanceTimersByTime(TOAST_BASE_MS * 2); });
    expect(screen.getByText('Stejná')).toBeInTheDocument();
    fireEvent.mouseLeave(screen.getByRole('alert'));
    act(() => { vi.advanceTimersByTime(TOAST_BASE_MS + 500); });
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
