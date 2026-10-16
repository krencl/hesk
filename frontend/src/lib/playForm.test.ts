import { describe, expect, it } from 'vitest';
import type { Game, Play } from '../api/types';
import { toLocalInput } from './datetime';
import { draftFromPlay, emptyDraft, type PlayDraft, toPlayInput, validateDraft } from './playForm';

const game = (scoreType: Game['scoreType']): Game => ({
  id: 7, name: 'G', scoreType, imageUrl: null, description: null, cheatSheet: null, addons: [{ id: 1, name: 'X' }],
});

const draft = (over: Partial<PlayDraft> = {}): PlayDraft => ({
  gameId: 7,
  startedAt: '2026-03-01T18:00',
  endedAt: '2026-03-01T19:00',
  players: [{ playerId: 1, score: '10,5', rank: '', winner: false }],
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
    const noScore = draft({ players: [{ playerId: 1, score: '', rank: '', winner: false }] });
    expect(validateDraft(noScore, game('fewest_points'))).toMatch(/skóre/);
    expect(validateDraft(noScore, game('rank_order'))).toMatch(/pořadí/);
    expect(validateDraft(noScore, game('winner_only'))).toMatch(/vítěze/);
  });
});

describe('toPlayInput', () => {
  it('maps score games, normalises decimals and cleans optional fields', () => {
    const input = toPlayInput(draft(), game('most_points'));
    expect(input.players).toEqual([{ playerId: 1, score: 10.5 }]);
    expect(input.addonIds).toEqual([1]);
    expect(input.place).toBe('Doma');
    expect(input.tags).toEqual(['víkend', 'liga']);
    expect(input.notes).toBeNull();
    expect(input.startedAt).toMatch(/Z$/);
  });

  it('maps rank and winner games', () => {
    const d = draft({ players: [{ playerId: 1, score: '', rank: '2', winner: true }] });
    expect(toPlayInput(d, game('rank_order')).players).toEqual([{ playerId: 1, rank: 2 }]);
    expect(toPlayInput(d, game('winner_only')).players).toEqual([{ playerId: 1, winner: true }]);
  });
});

describe('draftFromPlay', () => {
  const play: Play = {
    id: 1, sessionId: 2, sessionNumber: 3, game: { id: 7, name: 'G', imageUrl: null, scoreType: 'most_points' },
    startedAt: '2026-03-01T18:00:00+00:00', endedAt: '2026-03-01T19:30:00+00:00', place: null, notes: null,
    addons: [{ id: 1, name: 'X' }], tags: ['víkend', 'liga'],
    results: [{ playerId: 4, name: 'Anna', score: 10.5, rank: 1, winner: true }, { playerId: 5, name: 'Bob', score: null, rank: 2, winner: false }],
  };

  it('fills the form from a play', () => {
    expect(draftFromPlay(play)).toEqual({
      gameId: 7,
      startedAt: toLocalInput(new Date(play.startedAt)),
      endedAt: toLocalInput(new Date(play.endedAt)),
      players: [{ playerId: 4, score: '10.5', rank: '1', winner: true }, { playerId: 5, score: '', rank: '2', winner: false }],
      addonIds: [1],
      place: '',
      tags: 'víkend, liga',
      notes: '',
    });
  });

  it('round-trips through toPlayInput', () => {
    const input = toPlayInput(draftFromPlay(play), game('most_points'));
    expect(input.tags).toEqual(['víkend', 'liga']);
    expect(input.addonIds).toEqual([1]);
    expect(input.startedAt).toBe('2026-03-01T18:00:00.000Z');
    expect(input.endedAt).toBe('2026-03-01T19:30:00.000Z');
    expect(input.players[0]).toEqual({ playerId: 4, score: 10.5 });
  });
});

describe('emptyDraft', () => {
  it('starts an hour before now and ends now', () => {
    const now = new Date(2026, 2, 1, 20, 0);
    expect(emptyDraft(7, now)).toMatchObject({ gameId: 7, startedAt: '2026-03-01T19:00', endedAt: '2026-03-01T20:00', players: [], addonIds: [] });
  });
});
