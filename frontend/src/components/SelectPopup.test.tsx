import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { SelectPopup } from './SelectPopup';

const OPTIONS = [{ value: 69 as number | null, label: '69' }, { value: 12, label: '12' }, { value: 3, label: '3' }];

function Harness({ initial = null as number | null }) {
  const [value, setValue] = useState<number | null>(initial);
  return <SelectPopup title="Sezení" value={value} options={OPTIONS} pinned={{ value: null, label: 'Všechny' }} onChange={setValue} />;
}

const open = () => fireEvent.click(screen.getByRole('button', { name: 'Sezení' }));

describe('SelectPopup', () => {
  it('shows the current choice on the button and opens a popup with the search focused', () => {
    render(<Harness initial={12} />);
    expect(screen.getByRole('button', { name: 'Sezení' })).toHaveTextContent('12');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    open();
    expect(screen.getByRole('dialog', { name: 'Sezení' })).toBeInTheDocument();
    expect(screen.getByRole('searchbox', { name: /Hledat/ })).toHaveFocus();
    expect(screen.getByRole('option', { name: '12' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getAllByRole('option').map((o) => o.textContent?.replace(/radio_button_(un)?checked/, ''))).toEqual(['Všechny', '69', '12', '3']);
  });

  it('narrows the options by the search (keeping the pinned one and the faded selection) and picks one', () => {
    render(<Harness initial={69} />);
    open();
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: '1' } });
    expect(screen.getAllByRole('option').map((o) => o.textContent?.replace(/radio_button_(un)?checked/, ''))).toEqual(['Všechny', '12', '69']);
    expect(screen.getByRole('option', { name: '69' })).toHaveClass('faded');

    fireEvent.click(screen.getByRole('option', { name: '12' }));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Sezení' })).toHaveTextContent('12');
    // po novém otevření je hledání prázdné
    open();
    expect(screen.getByRole('searchbox')).toHaveValue('');
  });

  it('can pick the pinned "all" choice and says when nothing matches', () => {
    render(<Harness initial={3} />);
    open();
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'xyz' } });
    expect(screen.getByText('Nic nenalezeno.')).toBeInTheDocument();
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('option', { name: 'Všechny' }));
    expect(screen.getByRole('button', { name: 'Sezení' })).toHaveTextContent('Všechny');
  });

  it('closes by the cross and by Escape without changing the value, returning the focus to the button', () => {
    render(<Harness initial={12} />);
    open();
    fireEvent.click(screen.getByRole('button', { name: 'Zavřít výběr' }));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Sezení' })).toHaveFocus();
    expect(screen.getByRole('button', { name: 'Sezení' })).toHaveTextContent('12');

    open();
    fireEvent.keyDown(screen.getByRole('searchbox'), { key: 'Escape' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Sezení' })).toHaveTextContent('12');
  });
});
