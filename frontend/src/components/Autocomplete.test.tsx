import { fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { Autocomplete } from './Autocomplete';
import { TagInput } from './TagInput';

function Field({ options }: { options: string[] }) {
  const [value, setValue] = useState('');
  return <><label htmlFor="p">Pole</label><Autocomplete id="p" value={value} onChange={setValue} options={options} /></>;
}

function Tags({ options, initial = [] }: { options: string[]; initial?: string[] }) {
  const [tags, setTags] = useState(initial);
  return <><label htmlFor="t">Tagy</label><TagInput id="t" tags={tags} onChange={setTags} options={options} /></>;
}

const names = () => screen.queryAllByRole('option').map((o) => o.textContent);

describe('Autocomplete', () => {
  it('shows all known values on focus and narrows them while typing (ignoring case and diacritics)', () => {
    render(<Field options={['Doma', 'Klub', 'Kavárna Čapek']} />);
    expect(names()).toEqual([]);
    fireEvent.focus(screen.getByLabelText('Pole'));
    expect(names()).toEqual(['Doma', 'Klub', 'Kavárna Čapek']);
    fireEvent.change(screen.getByLabelText('Pole'), { target: { value: 'capek' } });
    expect(names()).toEqual(['Kavárna Čapek']);
  });

  it('picks a suggestion by click and keeps a brand new typed value as it is', () => {
    render(<Field options={['Doma', 'Klub']} />);
    const input = screen.getByLabelText('Pole');
    fireEvent.focus(input);
    fireEvent.click(screen.getByRole('option', { name: 'Klub' }));
    expect(input).toHaveValue('Klub');
    expect(names()).toEqual([]);

    fireEvent.change(input, { target: { value: 'Na chatě' } });
    expect(names()).toEqual([]);
    fireEvent.blur(input);
    expect(input).toHaveValue('Na chatě');
  });

  it('supports the keyboard: arrows, Enter, Escape', () => {
    render(<Field options={['Doma', 'Klub']} />);
    const input = screen.getByLabelText('Pole');
    fireEvent.focus(input);
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    expect(screen.getByRole('option', { name: 'Klub' })).toHaveAttribute('aria-selected', 'true');
    fireEvent.keyDown(input, { key: 'ArrowUp' });
    fireEvent.keyDown(input, { key: 'Enter' });
    expect(input).toHaveValue('Doma');

    fireEvent.change(input, { target: { value: 'D' } });
    expect(names()).toEqual(['Doma']);
    fireEvent.keyDown(input, { key: 'Escape' });
    expect(names()).toEqual([]);
  });
});

describe('TagInput', () => {
  it('adds a typed new tag with Enter or a comma and a suggestion by click, without duplicates', () => {
    render(<Tags options={['liga', 'víkend']} />);
    const input = screen.getByLabelText('Tagy');
    fireEvent.change(input, { target: { value: 'nový' } });
    fireEvent.keyDown(input, { key: 'Enter' });
    expect(screen.getByRole('button', { name: 'Odebrat tag nový' })).toBeInTheDocument();
    expect(input).toHaveValue('');

    fireEvent.change(input, { target: { value: 'rodinná, ' } });
    expect(screen.getByRole('button', { name: 'Odebrat tag rodinná' })).toBeInTheDocument();

    fireEvent.focus(input);
    fireEvent.click(screen.getByRole('option', { name: 'liga' }));
    expect(screen.getByRole('button', { name: 'Odebrat tag liga' })).toBeInTheDocument();
    expect(names()).toEqual([]); // přidané už se nenabízí (a nabídka se po výběru zavřela)

    fireEvent.change(input, { target: { value: 'LIGA' } });
    fireEvent.keyDown(input, { key: 'Enter' });
    expect(screen.getAllByRole('button', { name: /Odebrat tag/ })).toHaveLength(3);
  });

  it('adds the pending text when the input loses focus, so it is not lost on save', () => {
    render(<Tags options={[]} />);
    const input = screen.getByLabelText('Tagy');
    fireEvent.change(input, { target: { value: 'turnaj' } });
    fireEvent.blur(input);
    expect(screen.getByRole('button', { name: 'Odebrat tag turnaj' })).toBeInTheDocument();
  });

  it('reuses the spelling of an existing tag and removes tags', () => {
    render(<Tags options={['Liga']} initial={['víkend']} />);
    const input = screen.getByLabelText('Tagy');
    fireEvent.change(input, { target: { value: 'liga' } });
    fireEvent.keyDown(input, { key: 'Enter' });
    expect(screen.getByRole('button', { name: 'Odebrat tag Liga' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Odebrat tag víkend' }));
    expect(screen.queryByRole('button', { name: 'Odebrat tag víkend' })).not.toBeInTheDocument();
  });
});
