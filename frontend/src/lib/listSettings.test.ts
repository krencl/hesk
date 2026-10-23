import { describe, expect, it } from 'vitest';
import { DEFAULT_GAME_LIST_SETTINGS, DEFAULT_MANAGE_GAMES_SETTINGS, loadGameListSettings, MANAGE_GAMES_KEY, saveGameListSettings } from './listSettings';

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

  it('keeps the settings of the game management separate and sorted by name by default', () => {
    expect(loadGameListSettings(MANAGE_GAMES_KEY, DEFAULT_MANAGE_GAMES_SETTINGS).sort).toBe('name');
    saveGameListSettings({ ...DEFAULT_MANAGE_GAMES_SETTINGS, query: 'abc' }, MANAGE_GAMES_KEY);
    expect(loadGameListSettings(MANAGE_GAMES_KEY, DEFAULT_MANAGE_GAMES_SETTINGS).query).toBe('abc');
    expect(loadGameListSettings()).toEqual(DEFAULT_GAME_LIST_SETTINGS);
  });
});
