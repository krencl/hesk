import { describe, expect, it } from 'vitest';
import type { Play } from '../api/types';
import { formatScore, winners } from './scoring';

const results: Play['results'] = [
  { playerId: 1, name: 'Anna', score: 90, rank: 1, winner: true, starting: false, character: null },
  { playerId: 2, name: 'Bob', score: 90, rank: 1, winner: true, starting: false, character: null },
  { playerId: 3, name: 'Cyril', score: 70, rank: 3, winner: false, starting: false, character: null },
];

describe('winners', () => {
  it('returns all tied winners in order', () => {
    expect(winners({ results }).map((r) => r.name)).toEqual(['Anna', 'Bob']);
  });

  it('is empty when nobody won', () => {
    expect(winners({ results: [results[2]] })).toEqual([]);
  });
});

describe('formatScore', () => {
  it('keeps integers and trims decimals', () => {
    expect(formatScore(12)).toBe('12');
    expect(formatScore(12.345)).toBe('12,35');
  });
});
