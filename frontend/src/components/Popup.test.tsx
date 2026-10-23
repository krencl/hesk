import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Popup } from './Popup';

afterEach(() => document.getElementById('root')?.remove());

function withRoot() {
  const root = document.createElement('div');
  root.id = 'root';
  document.body.appendChild(root);
  const trigger = document.createElement('button');
  root.appendChild(trigger);
  trigger.focus();
  return { root, trigger };
}

describe('Popup', () => {
  it('shows a titled dialog, closes by the cross and by Escape', () => {
    const onClose = vi.fn();
    render(<Popup title="Nadpis" onClose={onClose}><p>Obsah</p></Popup>);
    const dialog = screen.getByRole('dialog', { name: 'Nadpis' });
    expect(dialog).toHaveFocus();
    expect(screen.getByText('Obsah')).toBeInTheDocument();
    fireEvent.keyDown(dialog, { key: 'Escape' });
    fireEvent.click(screen.getByRole('button', { name: 'Zavřít' }));
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('disables the cross while busy and supports the alertdialog role', () => {
    render(<Popup title="Čekej" role="alertdialog" busy onClose={vi.fn()}>x</Popup>);
    expect(screen.getByRole('alertdialog', { name: 'Čekej' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Zavřít' })).toBeDisabled();
  });

  it('makes #root inert while open and returns the focus to the previous element afterwards', () => {
    const { root, trigger } = withRoot();
    const { unmount } = render(<Popup title="A" onClose={vi.fn()}>x</Popup>);
    expect(root.inert).toBe(true);
    unmount();
    expect(root.inert).toBe(false);
    expect(trigger).toHaveFocus();
  });

  it('keeps #root inert until the last of several popups closes', () => {
    const { root } = withRoot();
    const first = render(<Popup title="A" onClose={vi.fn()}>x</Popup>);
    const second = render(<Popup title="B" onClose={vi.fn()}>x</Popup>);
    first.unmount();
    expect(root.inert).toBe(true);
    second.unmount();
    expect(root.inert).toBe(false);
  });
});
