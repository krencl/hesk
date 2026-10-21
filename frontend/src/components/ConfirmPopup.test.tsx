import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ConfirmPopup } from './ConfirmPopup';

describe('ConfirmPopup', () => {
  it('shows the explanation, confirms with the main button and cancels with Zrušit, the cross and Escape', () => {
    const onConfirm = vi.fn();
    const onCancel = vi.fn();
    render(
      <ConfirmPopup title="Smazat?" confirmLabel="Smazat" destructive onConfirm={onConfirm} onCancel={onCancel}>
        <p>Vysvětlení, co se stane.</p>
      </ConfirmPopup>,
    );
    expect(screen.getByRole('alertdialog', { name: 'Smazat?' })).toBeInTheDocument();
    expect(screen.getByText('Vysvětlení, co se stane.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Smazat' })).toHaveClass('destructive');
    expect(screen.getByRole('button', { name: 'Zrušit' })).toHaveFocus();

    fireEvent.click(screen.getByRole('button', { name: 'Smazat' }));
    expect(onConfirm).toHaveBeenCalledTimes(1);
    expect(onCancel).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Zrušit' }));
    fireEvent.click(screen.getByRole('button', { name: 'Zavřít' }));
    fireEvent.keyDown(screen.getByRole('alertdialog'), { key: 'Escape' });
    expect(onCancel).toHaveBeenCalledTimes(3);
  });
});
