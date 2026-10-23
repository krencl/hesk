import { describe, expect, it } from 'vitest';
import type { Game } from '../api/types';
import { GAME } from '../test/helpers';
import { emptyFormState, toFormState, toGameInput } from './gameForm';

const game = {
  ...GAME,
  shortName: 'Carc',
  description: 'Popis',
  cheatSheet: '# Pravidla',
  imageUrl: '/uploads/covers/a.webp',
  bggId: 822,
  addons: [{ id: 11, name: 'Řeka' }],
  characters: [{ id: 21, name: 'Rytíř' }],
  players: [{ id: 2, name: 'Boss' }],
  tags: ['Rychlovka'],
} as unknown as Game;

describe('gameForm', () => {
  it('turns a stored game into form state, keeping ids of the list items', () => {
    const form = toFormState(game);
    expect(form).toMatchObject({ name: 'Carcassonne', shortName: 'Carc', scoreType: 'most_points', imageUrl: '/uploads/covers/a.webp', description: 'Popis', cheatSheet: '# Pravidla', tags: ['Rychlovka'], bggId: 822 });
    expect(form.addons).toMatchObject([{ id: 11, name: 'Řeka' }]);
    expect(form.characters).toMatchObject([{ id: 21, name: 'Rytíř' }]);
    expect(form.players).toMatchObject([{ id: 2, name: 'Boss' }]);
  });

  it('replaces missing optional values by empty strings', () => {
    const form = toFormState({ ...game, shortName: null, imageUrl: null, description: null, cheatSheet: null, bggId: null } as Game);
    expect(form).toMatchObject({ shortName: '', imageUrl: '', description: '', cheatSheet: '', bggId: null });
  });

  it('round-trips to the request body', () => {
    expect(toGameInput(toFormState(game))).toEqual({
      name: 'Carcassonne', shortName: 'Carc', scoreType: 'most_points', imageUrl: '/uploads/covers/a.webp', description: 'Popis', cheatSheet: '# Pravidla',
      addons: [{ id: 11, name: 'Řeka' }], characters: [{ id: 21, name: 'Rytíř' }], players: [{ id: 2, name: 'Boss' }], tags: ['Rychlovka'], bggId: 822,
    });
  });

  it('trims texts, sends null for blank optional ones and drops blank list rows', () => {
    const form = emptyFormState({ name: '  Nová  ', shortName: '  ', imageUrl: ' ', description: '\n', cheatSheet: ' ' });
    form.addons = [{ key: 1, name: ' ' }, { key: 2, name: ' Nové ' }];
    expect(toGameInput(form)).toEqual({
      name: 'Nová', shortName: null, scoreType: 'most_points', imageUrl: null, description: null, cheatSheet: null,
      addons: [{ name: 'Nové' }], characters: [], players: [], tags: [], bggId: null,
    });
  });

  it('starts empty with the default scoring', () => {
    expect(emptyFormState({ name: 'X', bggId: 5 })).toMatchObject({ name: 'X', bggId: 5, scoreType: 'most_points', tags: [] });
  });
});
