import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { BarChart } from './BarChart';

describe('BarChart', () => {
  it('scales bars relative to the maximum', () => {
    render(<BarChart items={[{ key: 1, label: 'Anna', value: 4 }, { key: 2, label: 'Bob', value: 2 }]} />);
    expect(screen.getByRole('img', { name: 'Anna: 4' })).toHaveStyle({ width: '100%' });
    expect(screen.getByRole('img', { name: 'Bob: 2' })).toHaveStyle({ width: '50%' });
  });

  it('marks the first three bars as the top and the rest with a lighter shade', () => {
    render(<BarChart items={[1, 2, 3, 4, 5].map((n) => ({ key: n, label: `P${n}`, value: 10 - n }))} />);
    const rest = [1, 2, 3, 4, 5].map((n) => screen.getByRole('img', { name: `P${n}: ${10 - n}` }).classList.contains('rest'));
    expect(rest).toEqual([false, false, false, true, true]);
  });

  it('shows a placeholder without data', () => {
    render(<BarChart items={[]} />);
    expect(screen.getByText('Zatím žádná data.')).toBeInTheDocument();
  });

  it('does not show entries with the value 0', () => {
    render(<BarChart items={[{ key: 1, label: 'Anna', value: 3 }, { key: 2, label: 'Foxo', value: 0 }]} />);
    expect(screen.getByRole('img', { name: 'Anna: 3' })).toBeInTheDocument();
    expect(screen.queryByText('Foxo')).not.toBeInTheDocument();
  });

  it('shows the placeholder when every value is 0', () => {
    render(<BarChart items={[{ key: 1, label: 'Anna', value: 0 }]} />);
    expect(screen.getByText('Zatím žádná data.')).toBeInTheDocument();
  });
});
