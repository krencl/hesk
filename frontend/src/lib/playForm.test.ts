import { describe, expect, it } from 'vitest';
import type { Game } from '../api/types';
import { type PlayDraft, toPlayInput, validateDraft } from './playForm';

const game = (scoreType: Game['scoreType']): Game => ({
  id: 7, name: 'G', scoreType, imageUrl: null, description: null, cheatSheet: null, addons: [{ id: 1, name: 'X' }],
});

const draft = (over: Partial<PlayDraft> = {}): PlayDraft => ({
  gameId: 7,
  startedAt: '2026-03-01T18:00',
  endedAt: '2026-03-01T19:00',
  players: [{ userId: 1, score: '10,5', rank: '', winner: false }],
  addonIds: [1, 99],
  place: ' Doma ',
  tags: 'víkend, , liga',
  notes: '  ',
  ...over,
});

describe('validateDraft', () => {
  it('accepts a complete score draft', () => {
    expect(validateDraft(draft(), game('most_points'))).toBeNull();
  });

  it('requires a game and players', () => {
    expect(validateDraft(draft(), undefined)).toMatch(/hru/);
    expect(validateDraft(draft({ players: [] }), game('most_points'))).toMatch(/hráče/);
  });

  it('rejects end before start', () => {
    expect(validateDraft(draft({ endedAt: '2026-03-01T17:00' }), game('most_points'))).toMatch(/Konec/);
  });

  it('requires scores / ranks / a winner depending on score type', () => {
    const noScore = draft({ players: [{ userId: 1, score: '', rank: '', winner: false }] });
    expect(validateDraft(noScore, game('fewest_points'))).toMatch(/skóre/);
    expect(validateDraft(noScore, game('rank_order'))).toMatch(/pořadí/);
    expect(validateDraft(noScore, game('winner_only'))).toMatch(/vítěze/);
  });
});

describe('toPlayInput', () => {
  it('maps score games, normalises decimals and cleans optional fields', () => {
    const input = toPlayInput(draft(), game('most_points'));
    expect(input.players).toEqual([{ userId: 1, score: 10.5 }]);
    expect(input.addonIds).toEqual([1]);
    expect(input.place).toBe('Doma');
    expect(input.tags).toEqual(['víkend', 'liga']);
    expect(input.notes).toBeNull();
    expect(input.startedAt).toMatch(/Z$/);
  });

  it('maps rank and winner games', () => {
    const d = draft({ players: [{ userId: 1, score: '', rank: '2', winner: true }] });
    expect(toPlayInput(d, game('rank_order')).players).toEqual([{ userId: 1, rank: 2 }]);
    expect(toPlayInput(d, game('winner_only')).players).toEqual([{ userId: 1, winner: true }]);
  });
});
