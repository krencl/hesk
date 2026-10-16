import { describe, expect, it } from 'vitest';
import type { Game, Play } from '../api/types';
import { toLocalInput } from './datetime';
import { bestScoreIds, draftFromPlay, emptyDraft, isWinner, type PlayDraft, toPlayInput, validateDraft } from './playForm';

const game = (scoreType: Game['scoreType']): Game => ({
  id: 7, name: 'G', scoreType, imageUrl: null, description: null, cheatSheet: null, addons: [{ id: 1, name: 'X' }],
});

const draft = (over: Partial<PlayDraft> = {}): PlayDraft => ({
  gameId: 7,
  startedAt: '2026-03-01T18:00',
  endedAt: '2026-03-01T19:00',
  players: [{ playerId: 1, score: '10,5', rank: '', winner: false, starting: false }],
  addonIds: [1, 99],
  place: ' Doma ',
  tags: 'víkend, , liga',
  notes: '  ',
  winnersTouched: false,
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
    const noScore = draft({ players: [{ playerId: 1, score: '', rank: '', winner: false, starting: false }] });
    expect(validateDraft(noScore, game('fewest_points'))).toMatch(/skóre/);
    expect(validateDraft(noScore, game('rank_order'))).toMatch(/pořadí/);
    expect(validateDraft(noScore, game('winner_only'))).toMatch(/vítěze/);
  });
});

describe('toPlayInput', () => {
  it('maps score games, normalises decimals and cleans optional fields', () => {
    const input = toPlayInput(draft(), game('most_points'));
    expect(input.players).toEqual([{ playerId: 1, score: 10.5, starting: false }]);
    expect(input.addonIds).toEqual([1]);
    expect(input.place).toBe('Doma');
    expect(input.tags).toEqual(['víkend', 'liga']);
    expect(input.notes).toBeNull();
    expect(input.startedAt).toMatch(/Z$/);
  });

  it('maps rank and winner games', () => {
    const d = draft({ players: [{ playerId: 1, score: '', rank: '2', winner: true, starting: false }] });
    expect(toPlayInput(d, game('rank_order')).players).toEqual([{ playerId: 1, rank: 2, starting: false }]);
    expect(toPlayInput(d, game('winner_only')).players).toEqual([{ playerId: 1, winner: true, starting: false }]);
  });
});

describe('draftFromPlay', () => {
  const play: Play = {
    id: 1, sessionId: 2, sessionNumber: 3, game: { id: 7, name: 'G', imageUrl: null, scoreType: 'most_points' },
    startedAt: '2026-03-01T18:00:00+00:00', endedAt: '2026-03-01T19:30:00+00:00', place: null, notes: null,
    addons: [{ id: 1, name: 'X' }], tags: ['víkend', 'liga'],
    results: [{ playerId: 4, name: 'Anna', score: 10.5, rank: 1, winner: true, starting: true }, { playerId: 5, name: 'Bob', score: null, rank: 2, winner: false, starting: false }],
  };

  it('fills the form from a play', () => {
    expect(draftFromPlay(play)).toEqual({
      gameId: 7,
      startedAt: toLocalInput(new Date(play.startedAt)),
      endedAt: toLocalInput(new Date(play.endedAt)),
      players: [{ playerId: 4, score: '10.5', rank: '1', winner: true, starting: true }, { playerId: 5, score: '', rank: '2', winner: false, starting: false }],
      addonIds: [1],
      place: '',
      tags: 'víkend, liga',
      notes: '',
      winnersTouched: false,
    });
  });

  it('round-trips through toPlayInput', () => {
    const input = toPlayInput(draftFromPlay(play), game('most_points'));
    expect(input.tags).toEqual(['víkend', 'liga']);
    expect(input.addonIds).toEqual([1]);
    expect(input.startedAt).toBe('2026-03-01T18:00:00.000Z');
    expect(input.endedAt).toBe('2026-03-01T19:30:00.000Z');
    expect(input.players[0]).toEqual({ playerId: 4, score: 10.5, starting: true });
  });
});

describe('emptyDraft', () => {
  it('starts an hour before now and ends now', () => {
    const now = new Date(2026, 2, 1, 20, 0);
    expect(emptyDraft(7, now)).toMatchObject({ gameId: 7, startedAt: '2026-03-01T19:00', endedAt: '2026-03-01T20:00', players: [], addonIds: [] });
  });
});

describe('winners of scored games', () => {
  const two = (a: string, b: string, over: Partial<PlayDraft> = {}) => draft({
    players: [{ playerId: 1, score: a, rank: '', winner: false, starting: false }, { playerId: 2, score: b, rank: '', winner: false, starting: false }],
    ...over,
  });

  it('finds the best score, with all tied players as winners', () => {
    expect([...bestScoreIds([{ playerId: 1, score: 5 }, { playerId: 2, score: 9 }, { playerId: 3, score: 9 }, { playerId: 4, score: null }], 'most_points')]).toEqual([2, 3]);
    expect([...bestScoreIds([{ playerId: 1, score: 5 }, { playerId: 2, score: 9 }], 'fewest_points')]).toEqual([1]);
    expect(bestScoreIds([{ playerId: 1, score: null }], 'most_points').size).toBe(0);
  });

  it('shows the automatic winners until the user picks them manually', () => {
    const tied = two('10', '10,0');
    expect(isWinner(tied, game('most_points'), 1)).toBe(true);
    expect(isWinner(tied, game('most_points'), 2)).toBe(true);
    const manual = two('10', '10', { winnersTouched: true, players: [{ playerId: 1, score: '10', rank: '', winner: false, starting: false }, { playerId: 2, score: '10', rank: '', winner: true, starting: false }] });
    expect(isWinner(manual, game('most_points'), 1)).toBe(false);
    expect(isWinner(manual, game('most_points'), 2)).toBe(true);
  });

  it('sends winner flags only for a manual choice, and the starting player always', () => {
    const auto = toPlayInput(two('10', '10'), game('most_points'));
    expect(auto.players).toEqual([{ playerId: 1, score: 10, starting: false }, { playerId: 2, score: 10, starting: false }]);
    const manual = toPlayInput(two('10', '10', { winnersTouched: true, players: [{ playerId: 1, score: '10', rank: '', winner: true, starting: true }, { playerId: 2, score: '10', rank: '', winner: false, starting: false }] }), game('most_points'));
    expect(manual.players).toEqual([{ playerId: 1, score: 10, starting: true, winner: true }, { playerId: 2, score: 10, starting: false, winner: false }]);
  });

  it('requires a winner after manual selection and rejects two starting players', () => {
    const none = two('10', '10', { winnersTouched: true });
    expect(validateDraft(none, game('most_points'))).toMatch(/vítěze/);
    const two_starting = two('1', '2', { players: [{ playerId: 1, score: '1', rank: '', winner: false, starting: true }, { playerId: 2, score: '2', rank: '', winner: false, starting: true }] });
    expect(validateDraft(two_starting, game('most_points'))).toMatch(/Začínající/);
    expect(validateDraft(two('1', 'abc'), game('most_points'))).toMatch(/číslo/);
  });

  it('detects a manual winner when editing a play (tie decided by hand)', () => {
    const base: Play = {
      id: 1, sessionId: 2, sessionNumber: 3, game: { id: 7, name: 'G', imageUrl: null, scoreType: 'most_points' },
      startedAt: '2026-03-01T18:00:00+00:00', endedAt: '2026-03-01T19:00:00+00:00', place: null, notes: null, addons: [], tags: [],
      results: [
        { playerId: 1, name: 'A', score: 50, rank: 1, winner: true, starting: false },
        { playerId: 2, name: 'B', score: 50, rank: 1, winner: false, starting: false },
      ],
    };
    expect(draftFromPlay(base).winnersTouched).toBe(true);
    const natural = { ...base, results: [base.results[0], { ...base.results[1], winner: true }] };
    expect(draftFromPlay(natural).winnersTouched).toBe(false);
    const winnerOnly = { ...base, game: { ...base.game, scoreType: 'winner_only' as const } };
    expect(draftFromPlay(winnerOnly).winnersTouched).toBe(false);
  });
});
