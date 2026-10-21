import { describe, expect, it } from 'vitest';
import type { Game, Play } from '../api/types';
import { toLocalInput } from './datetime';
import { bestScoreIds, defaultPlayerIds, defaultTimes, draftFromPlay, emptyDraft, isWinner, type PlayDraft, toPlayInput, validateDraft } from './playForm';

const game = (scoreType: Game['scoreType']): Game => ({
  id: 7, name: 'G', shortName: null, scoreType, imageUrl: null, description: null, cheatSheet: null, addons: [{ id: 1, name: 'X' }], characters: [{ id: 3, name: 'Gigazaur' }], players: [],
});

const draft = (over: Partial<PlayDraft> = {}): PlayDraft => ({
  gameId: 7,
  startedAt: '2026-03-01T18:00',
  endedAt: '2026-03-01T19:00',
  players: [{ playerId: 1, score: '10,5', rank: '', winner: false, starting: false, characterId: null }],
  addonIds: [1, 99],
  tags: ['víkend', 'liga'],
  notes: '  ',
  sessionNumber: '95',
  sessionNumberTouched: false,
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
    const noScore = draft({ players: [{ playerId: 1, score: '', rank: '', winner: false, starting: false, characterId: null }] });
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
    expect(input.tags).toEqual(['víkend', 'liga']);
    expect(input.notes).toBeNull();
    expect(input.startedAt).toMatch(/Z$/);
  });

  it('maps rank and winner games', () => {
    const d = draft({ players: [{ playerId: 1, score: '', rank: '2', winner: true, starting: false, characterId: null }] });
    expect(toPlayInput(d, game('rank_order')).players).toEqual([{ playerId: 1, rank: 2, starting: false }]);
    expect(toPlayInput(d, game('winner_only')).players).toEqual([{ playerId: 1, winner: true, starting: false }]);
  });
});

describe('draftFromPlay', () => {
  const play: Play = {
    id: 1, sessionId: 2, sessionNumber: 3, game: { id: 7, name: 'G', imageUrl: null, scoreType: 'most_points' },
    startedAt: '2026-03-01T18:00:00+00:00', endedAt: '2026-03-01T19:30:00+00:00', notes: null,
    addons: [{ id: 1, name: 'X' }], tags: ['víkend', 'liga'],
    results: [{ playerId: 4, name: 'Anna', score: 10.5, rank: 1, winner: true, starting: true, character: null }, { playerId: 5, name: 'Bob', score: null, rank: 2, winner: false, starting: false, character: null }],
  };

  it('fills the form from a play', () => {
    expect(draftFromPlay(play)).toEqual({
      gameId: 7,
      startedAt: toLocalInput(new Date(play.startedAt)),
      endedAt: toLocalInput(new Date(play.endedAt)),
      players: [{ playerId: 4, score: '10.5', rank: '1', winner: true, starting: true, characterId: null }, { playerId: 5, score: '', rank: '2', winner: false, starting: false, characterId: null }],
      addonIds: [1],
      tags: ['víkend', 'liga'],
      notes: '',
      sessionNumber: '3',
      sessionNumberTouched: false,
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

describe('session number', () => {
  it('is not sent unless the user changed it (the backend derives it from the time)', () => {
    expect(toPlayInput(draft(), game('most_points'))).not.toHaveProperty('sessionNumber');
    expect(toPlayInput(draft({ sessionNumberTouched: true, sessionNumber: ' 96 ' }), game('most_points')).sessionNumber).toBe(96);
  });

  it('rejects an invalid manual number', () => {
    for (const bad of ['', '0', '-1', '1.5', 'abc']) {
      expect(validateDraft(draft({ sessionNumberTouched: true, sessionNumber: bad }), game('most_points'))).toMatch(/Číslo sezení/);
    }
    expect(validateDraft(draft({ sessionNumberTouched: false, sessionNumber: '' }), game('most_points'))).toBeNull();
  });
});

describe('defaultPlayerIds', () => {
  const players = [{ id: 1, hasAccount: true }, { id: 2, hasAccount: false }, { id: 3, hasAccount: true }];

  it('takes the players of the last play of the same session', () => {
    expect(defaultPlayerIds({ playerIds: [2, 3] }, players)).toEqual([2, 3]);
  });

  it('falls back to the party members with an account when there is no session yet', () => {
    expect(defaultPlayerIds({ playerIds: null }, players)).toEqual([1, 3]);
    expect(defaultPlayerIds(undefined, players)).toEqual([1, 3]);
  });
});

describe('emptyDraft', () => {
  it('prefills players and the session number without duplicating the own player of the game', () => {
    const d = emptyDraft({ id: 7, players: [{ id: 3, name: 'Boss' }] }, new Date(2026, 2, 1, 20, 0), { playerIds: [1, 3], sessionNumber: 95 });
    expect(d.players.map((p) => p.playerId)).toEqual([3, 1]);
    expect(d).toMatchObject({ sessionNumber: '95', sessionNumberTouched: false, tags: [] });
  });

  it('computes default times', () => {
    expect(defaultTimes(new Date(2026, 2, 1, 20, 0))).toEqual({ startedAt: '2026-03-01T20:00', endedAt: '2026-03-01T20:00' });
  });

  it('starts and ends now', () => {
    const now = new Date(2026, 2, 1, 20, 0);
    expect(emptyDraft({ id: 7, players: [] }, now)).toMatchObject({ gameId: 7, startedAt: '2026-03-01T20:00', endedAt: '2026-03-01T20:00', players: [], addonIds: [] });
  });
});

describe('winners of scored games', () => {
  const two = (a: string, b: string, over: Partial<PlayDraft> = {}) => draft({
    players: [{ playerId: 1, score: a, rank: '', winner: false, starting: false, characterId: null }, { playerId: 2, score: b, rank: '', winner: false, starting: false, characterId: null }],
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
    const manual = two('10', '10', { winnersTouched: true, players: [{ playerId: 1, score: '10', rank: '', winner: false, starting: false, characterId: null }, { playerId: 2, score: '10', rank: '', winner: true, starting: false, characterId: null }] });
    expect(isWinner(manual, game('most_points'), 1)).toBe(false);
    expect(isWinner(manual, game('most_points'), 2)).toBe(true);
  });

  it('sends winner flags only for a manual choice, and the starting player always', () => {
    const auto = toPlayInput(two('10', '10'), game('most_points'));
    expect(auto.players).toEqual([{ playerId: 1, score: 10, starting: false }, { playerId: 2, score: 10, starting: false }]);
    const manual = toPlayInput(two('10', '10', { winnersTouched: true, players: [{ playerId: 1, score: '10', rank: '', winner: true, starting: true, characterId: null }, { playerId: 2, score: '10', rank: '', winner: false, starting: false, characterId: null }] }), game('most_points'));
    expect(manual.players).toEqual([{ playerId: 1, score: 10, starting: true, winner: true }, { playerId: 2, score: 10, starting: false, winner: false }]);
  });

  it('requires a winner after manual selection and rejects two starting players', () => {
    const none = two('10', '10', { winnersTouched: true });
    expect(validateDraft(none, game('most_points'))).toMatch(/vítěze/);
    const two_starting = two('1', '2', { players: [{ playerId: 1, score: '1', rank: '', winner: false, starting: true, characterId: null }, { playerId: 2, score: '2', rank: '', winner: false, starting: true, characterId: null }] });
    expect(validateDraft(two_starting, game('most_points'))).toMatch(/Začínající/);
    expect(validateDraft(two('1', 'abc'), game('most_points'))).toMatch(/číslo/);
  });

  it('detects a manual winner when editing a play (tie decided by hand)', () => {
    const base: Play = {
      id: 1, sessionId: 2, sessionNumber: 3, game: { id: 7, name: 'G', imageUrl: null, scoreType: 'most_points' },
      startedAt: '2026-03-01T18:00:00+00:00', endedAt: '2026-03-01T19:00:00+00:00', notes: null, addons: [], tags: [],
      results: [
        { playerId: 1, name: 'A', score: 50, rank: 1, winner: true, starting: false, character: null },
        { playerId: 2, name: 'B', score: 50, rank: 1, winner: false, starting: false, character: null },
      ],
    };
    expect(draftFromPlay(base).winnersTouched).toBe(true);
    const natural = { ...base, results: [base.results[0], { ...base.results[1], winner: true }] };
    expect(draftFromPlay(natural).winnersTouched).toBe(false);
    const winnerOnly = { ...base, game: { ...base.game, scoreType: 'winner_only' as const } };
    expect(draftFromPlay(winnerOnly).winnersTouched).toBe(false);
  });
});

describe('characters and the game player', () => {
  it('keeps a chosen character only when it belongs to the game', () => {
    const d = draft({ players: [
      { playerId: 1, score: '1', rank: '', winner: false, starting: false, characterId: 3 },
      { playerId: 2, score: '2', rank: '', winner: false, starting: false, characterId: 999 },
      { playerId: 3, score: '3', rank: '', winner: false, starting: false, characterId: null },
    ] });
    expect(toPlayInput(d, game('most_points')).players).toEqual([
      { playerId: 1, score: 1, starting: false, characterId: 3 },
      { playerId: 2, score: 2, starting: false },
      { playerId: 3, score: 3, starting: false },
    ]);
  });

  it('starts a new play with the own player of the game selected', () => {
    const now = new Date(2026, 2, 1, 20, 0);
    expect(emptyDraft({ id: 7, players: [{ id: 9, name: 'Boss' }] }, now).players).toEqual([{ playerId: 9, score: '', rank: '', winner: false, starting: false, characterId: null }]);
    expect(emptyDraft({ id: 7, players: [] }, now).players).toEqual([]);
    expect(emptyDraft({ id: 7, players: [{ id: 9, name: 'Boss' }, { id: 4, name: 'Minion' }] }, now, { playerIds: [1, 4] }).players.map((p) => p.playerId)).toEqual([9, 4, 1]);
  });

  it('fills the character when editing a play', () => {
    const play: Play = {
      id: 1, sessionId: 2, sessionNumber: 3, game: { id: 7, name: 'G', imageUrl: null, scoreType: 'winner_only' },
      startedAt: '2026-03-01T18:00:00+00:00', endedAt: '2026-03-01T19:00:00+00:00', notes: null, addons: [], tags: [],
      results: [{ playerId: 1, name: 'A', score: null, rank: 1, winner: true, starting: false, character: { id: 3, name: 'Gigazaur' } }],
    };
    expect(draftFromPlay(play).players[0].characterId).toBe(3);
  });
});
