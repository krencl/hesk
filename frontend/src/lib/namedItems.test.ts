import { describe, expect, it } from 'vitest';
import { fromEditItems, toEditItems } from './namedItems';

describe('named items', () => {
  it('keeps ids of saved items and gives every row its own key', () => {
    const items = toEditItems([{ id: 5, name: 'Řeka' }, { id: 6, name: 'Opat' }]);
    expect(items.map((i) => i.id)).toEqual([5, 6]);
    expect(new Set(items.map((i) => i.key)).size).toBe(2);
  });

  it('prepares items for the API: trims, drops blanks, omits id of new rows', () => {
    expect(fromEditItems([
      { key: 1, id: 5, name: ' Řeka přejmenovaná ' },
      { key: 2, name: '   ' },
      { key: 3, name: 'Nové' },
    ])).toEqual([{ id: 5, name: 'Řeka přejmenovaná' }, { name: 'Nové' }]);
  });
});
