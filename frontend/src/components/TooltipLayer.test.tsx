import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { TOOLTIP_DELAY_MS, TooltipLayer } from './TooltipLayer';

beforeEach(() => { vi.useFakeTimers(); });
afterEach(() => { vi.useRealTimers(); });

function Page() {
  return (
    <>
      <button aria-label="Smazat hosta Bob"><span className="icon">delete</span></button>
      <button aria-label="Uložit"><span className="icon">save</span> Uložit</button>
      <TooltipLayer />
    </>
  );
}

describe('TooltipLayer', () => {
  it('shows a tooltip with the label after a short delay when the mouse is over an icon button, and hides it on leaving', () => {
    render(<Page />);
    const button = screen.getByRole('button', { name: 'Smazat hosta Bob' });
    fireEvent.pointerOver(button);
    act(() => { vi.advanceTimersByTime(TOOLTIP_DELAY_MS - 50); });
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
    act(() => { vi.advanceTimersByTime(100); });
    expect(screen.getByRole('tooltip')).toHaveTextContent('Smazat hosta Bob');

    fireEvent.pointerOver(document.body);
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
  });

  it('does not show it for a button with text, for touch, and hides it on press and Escape', () => {
    render(<Page />);
    fireEvent.pointerOver(screen.getByRole('button', { name: 'Uložit' }));
    act(() => { vi.advanceTimersByTime(1000); });
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();

    const icon = screen.getByRole('button', { name: 'Smazat hosta Bob' });
    const touch = new Event('pointerover', { bubbles: true });
    Object.defineProperty(touch, 'pointerType', { value: 'touch' });
    fireEvent(icon, touch);
    act(() => { vi.advanceTimersByTime(1000); });
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();

    fireEvent.pointerOver(icon);
    act(() => { vi.advanceTimersByTime(500); });
    expect(screen.getByRole('tooltip')).toBeInTheDocument();
    fireEvent.pointerDown(icon);
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();

    fireEvent.pointerOver(icon);
    act(() => { vi.advanceTimersByTime(500); });
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
  });

  it('does not keep a tooltip after a mouse click focused the button', () => {
    render(<Page />);
    const button = screen.getByRole('button', { name: 'Smazat hosta Bob' });
    fireEvent.pointerDown(button);
    button.focus();
    act(() => { vi.advanceTimersByTime(1000); });
    expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
  });

  it('shows it immediately on keyboard focus', () => {
    render(<Page />);
    screen.getByRole('button', { name: 'Smazat hosta Bob' }).focus();
    act(() => { vi.advanceTimersByTime(0); });
    expect(screen.getByRole('tooltip')).toHaveTextContent('Smazat hosta Bob');
  });
});
