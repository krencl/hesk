import { describe, expect, it } from 'vitest';
import type { Play } from '../api/types';
import { formatScore, groupBySession, winnerSummary } from './scoring';

const play = (id: number, sessionId: number, results: Play['results'] = []): Play => ({
  id, sessionId, results,
  game: { id: 1, name: 'G', imageUrl: null, scoreType: 'most_points' },
  startedAt: '2026-01-01T10:00:00Z', endedAt: '2026-01-01T11:00:00Z', place: null, notes: null, addons: [], tags: [],
});

describe('winnerSummary', () => {
  it('lists winners with the top score', () => {
    const p = play(1, 1, [
      { playerId: 1, name: 'Anna', score: 90, rank: 1, winner: true },
      { playerId: 2, name: 'Bob', score: 70, rank: 2, winner: false },
    ]);
    expect(winnerSummary(p)).toBe('Anna (90)');
  });

  it('joins tied winners and omits missing scores', () => {
    const p = play(1, 1, [
      { playerId: 1, name: 'Anna', score: null, rank: 1, winner: true },
      { playerId: 2, name: 'Bob', score: null, rank: 1, winner: true },
    ]);
    expect(winnerSummary(p)).toBe('Anna, Bob');
  });

  it('shows a dash without winners', () => {
    expect(winnerSummary(play(1, 1))).toBe('—');
  });
});

describe('formatScore', () => {
  it('keeps integers and trims decimals', () => {
    expect(formatScore(12)).toBe('12');
    expect(formatScore(12.345)).toBe('12,35');
  });
});

describe('groupBySession', () => {
  it('groups consecutive plays of one session and keeps order', () => {
    const groups = groupBySession([play(5, 2), play(4, 2), play(3, 1)]);
    expect(groups.map((g) => [g.sessionId, g.plays.map((p) => p.id)])).toEqual([[2, [5, 4]], [1, [3]]]);
  });
});
