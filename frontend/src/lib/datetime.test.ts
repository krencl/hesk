import { describe, expect, it } from 'vitest';
import { formatDuration } from './datetime';

describe('formatDuration', () => {
  it('formats hours and minutes', () => {
    expect(formatDuration('2026-03-01T18:00:00Z', '2026-03-01T19:30:00Z')).toBe('1 h 30 min');
    expect(formatDuration('2026-03-01T18:00:00Z', '2026-03-01T20:00:00Z')).toBe('2 h');
    expect(formatDuration('2026-03-01T18:00:00Z', '2026-03-01T18:45:00Z')).toBe('45 min');
  });

  it('returns empty string for non-positive or invalid ranges', () => {
    expect(formatDuration('2026-03-01T18:00:00Z', '2026-03-01T18:00:00Z')).toBe('');
    expect(formatDuration('2026-03-01T19:00:00Z', '2026-03-01T18:00:00Z')).toBe('');
    expect(formatDuration('x', 'y')).toBe('');
  });
});
