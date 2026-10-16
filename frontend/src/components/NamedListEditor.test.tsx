import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { type EditItem, toEditItems } from '../lib/namedItems';
import { NamedListEditor } from './NamedListEditor';

function Harness({ initial }: { initial: EditItem[] }) {
  const [items, setItems] = useState(initial);
  return (
    <>
      <NamedListEditor label="Rozšíření" noun="rozšíření" addLabel="Přidat rozšíření" emptyText="Hra nemá rozšíření." items={items} onChange={setItems} />
      <output data-testid="names">{JSON.stringify(items.map((i) => [i.id ?? null, i.name]))}</output>
    </>
  );
}
const names = () => JSON.parse(screen.getByTestId('names').textContent!);

describe('NamedListEditor', () => {
  it('shows a separate input for every item and an empty hint', () => {
    const { unmount } = render(<Harness initial={[]} />);
    expect(screen.getByText('Hra nemá rozšíření.')).toBeInTheDocument();
    unmount();
    render(<Harness initial={toEditItems([{ id: 1, name: 'Řeka' }, { id: 2, name: 'Opat' }])} />);
    expect(screen.getByLabelText('Rozšíření 1')).toHaveValue('Řeka');
    expect(screen.getByLabelText('Rozšíření 2')).toHaveValue('Opat');
    expect(screen.queryByText('Hra nemá rozšíření.')).not.toBeInTheDocument();
  });

  it('renames in place (keeping the id), removes and adds items', () => {
    render(<Harness initial={toEditItems([{ id: 1, name: 'Řeka' }, { id: 2, name: 'Opat' }])} />);
    fireEvent.change(screen.getByLabelText('Rozšíření 1'), { target: { value: 'Řeky' } });
    expect(names()).toEqual([[1, 'Řeky'], [2, 'Opat']]);

    fireEvent.click(screen.getByRole('button', { name: 'Odebrat rozšíření 2' }));
    expect(names()).toEqual([[1, 'Řeky']]);

    fireEvent.click(screen.getByRole('button', { name: 'Přidat rozšíření' }));
    fireEvent.change(screen.getByLabelText('Rozšíření 2'), { target: { value: 'Nové' } });
    expect(names()).toEqual([[1, 'Řeky'], [null, 'Nové']]);
  });

  it('Enter in a filled input adds a focused row instead of submitting, and ignores an empty one', () => {
    render(<Harness initial={toEditItems([{ id: 1, name: 'Řeka' }])} />);
    fireEvent.keyDown(screen.getByLabelText('Rozšíření 1'), { key: 'Enter' });
    expect(screen.getByLabelText('Rozšíření 2')).toHaveFocus();
    fireEvent.keyDown(screen.getByLabelText('Rozšíření 2'), { key: 'Enter' }); // prázdný řádek další nepřidá
    expect(screen.queryByLabelText('Rozšíření 3')).not.toBeInTheDocument();
  });
});
