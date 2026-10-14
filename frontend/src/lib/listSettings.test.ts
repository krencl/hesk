import { afterEach, describe, expect, it } from 'vitest';
import { DEFAULT_GAME_LIST_SETTINGS, loadGameListSettings, saveGameListSettings } from './listSettings';

afterEach(() => sessionStorage.clear());

describe('game list settings', () => {
  it('returns defaults when nothing is stored', () => {
    expect(loadGameListSettings()).toEqual(DEFAULT_GAME_LIST_SETTINGS);
  });

  it('round-trips saved settings', () => {
    const settings = { query: 'dix', filterVisible: true, sort: 'name' as const, sessionNumber: 12 };
    saveGameListSettings(settings);
    expect(loadGameListSettings()).toEqual(settings);
  });

  it('sanitizes corrupted values', () => {
    sessionStorage.setItem('hesk.gameList', JSON.stringify({ query: 5, filterVisible: 'yes', sort: 'evil', sessionNumber: '3' }));
    expect(loadGameListSettings()).toEqual(DEFAULT_GAME_LIST_SETTINGS);
    sessionStorage.setItem('hesk.gameList', '{not json');
    expect(loadGameListSettings()).toEqual(DEFAULT_GAME_LIST_SETTINGS);
  });
});
