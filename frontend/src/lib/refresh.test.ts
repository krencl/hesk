import { QueryClient } from '@tanstack/react-query';
import { describe, expect, it } from 'vitest';
import { refreshAfterPlayChange } from './refresh';

function seeded() {
  const qc = new QueryClient();
  for (const key of [['games', 3, 'list', {}], ['games', 3, 'plays', 7], ['game', 7], ['stats', 3], ['stats', 4]]) qc.setQueryData(key, 'x');
  return qc;
}

describe('refreshAfterPlayChange', () => {
  it('invalidates everything derived from plays of the party, but not other parties', async () => {
    const qc = seeded();
    await refreshAfterPlayChange(qc, 3, 7);
    const stale = (key: unknown[]) => qc.getQueryState(key)?.isInvalidated;
    expect([['games', 3, 'list', {}], ['games', 3, 'plays', 7], ['game', 7], ['stats', 3]].every(stale)).toBe(true);
    expect(stale(['stats', 4])).toBe(false);
  });

  it('drops the cached round list of the game when leaving the page', async () => {
    const qc = seeded();
    await refreshAfterPlayChange(qc, 3, 7, { leaving: true });
    expect(qc.getQueryData(['games', 3, 'plays', 7])).toBeUndefined();
    expect(qc.getQueryData(['game', 7])).toBe('x');
  });
});
