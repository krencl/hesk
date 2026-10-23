import { describe, expect, it } from 'vitest';
import { addItem, type FilterItem, parseStatsSearch, removeItem, statsSearch, toggleItem, toStatsQuery } from './statsFilter';

const azul: FilterItem = { kind: 'game', id: 2, name: 'Azul', mode: 'include' };
const rychlovka: FilterItem = { kind: 'tag', name: 'Rychlovka', mode: 'include' };

describe('stats filter', () => {
  it('adds an item once, toggles + / − and removes it', () => {
    const added = addItem(addItem([], azul), { ...azul, mode: 'exclude' });
    expect(added).toEqual([azul]);
    expect(toggleItem(added, azul)).toEqual([{ ...azul, mode: 'exclude' }]);
    expect(toggleItem(toggleItem(added, azul), azul)).toEqual([azul]);
    expect(removeItem(added, azul)).toEqual([]);
  });

  it('keeps a game and a tag of the same name apart', () => {
    const items = addItem([{ kind: 'game', id: 1, name: 'Strategie', mode: 'include' }], { kind: 'tag', name: 'Strategie', mode: 'include' });
    expect(items).toHaveLength(2);
  });

  it('builds a sorted stats query', () => {
    const items: FilterItem[] = [{ kind: 'game', id: 9, name: 'B', mode: 'exclude' }, azul, { ...rychlovka, name: 'Párty', mode: 'exclude' }, rychlovka, { kind: 'game', id: 1, name: 'A', mode: 'include' }];
    expect(toStatsQuery(items)).toEqual({ tags: ['Rychlovka'], excludeTags: ['Párty'], games: [1, 2], excludeGames: [9] });
  });

  it('keeps the filter in the address: repeated params, game ids only, stable order of the items', () => {
    const items: FilterItem[] = [rychlovka, { kind: 'tag', name: 'Párty', mode: 'exclude' }, { kind: 'game', id: 9, name: 'B', mode: 'exclude' }, azul];
    const params = statsSearch(items);
    expect(params.toString()).toBe('tags=Rychlovka&excludeTags=P%C3%A1rty&games=2&excludeGames=9');

    const parsed = parseStatsSearch(params, (id) => `Hra ${id}`);
    expect(parsed).toEqual([{ kind: 'game', id: 2, name: 'Hra 2', mode: 'include' }, { kind: 'game', id: 9, name: 'Hra 9', mode: 'exclude' }, { kind: 'tag', name: 'Párty', mode: 'exclude' }, rychlovka]);
    expect(statsSearch(toggleItem(parsed, parsed[0])).toString()).toContain('excludeGames=2&excludeGames=9');
  });

  it('ignores broken params and does not let one item be both + and −', () => {
    const params = new URLSearchParams('games=x&games=-3&games=5&excludeGames=5&tags=%20&tags=Strategie&excludeTags=Strategie');
    expect(parseStatsSearch(params, () => 'H')).toEqual([{ kind: 'game', id: 5, name: 'H', mode: 'include' }, { kind: 'tag', name: 'Strategie', mode: 'include' }]);
  });
});
