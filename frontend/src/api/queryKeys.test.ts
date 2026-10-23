import { QueryClient } from '@tanstack/react-query';
import { describe, expect, it } from 'vitest';
import { qk } from './queryKeys';

describe('query keys', () => {
  it('lets the prefix keys invalidate the more specific ones', async () => {
    const qc = new QueryClient();
    const keys = [qk.gameList(3, { query: '' }), qk.gamePlays(3, 7), qk.statsFilterGames(3, 'x'), qk.game(7, 3), qk.party(3), qk.playDefaults(3, 'a', 'b'), qk.stats(3, { games: [1] })];
    for (const key of keys) qc.setQueryData(key, 'x');
    const stale = (key: readonly unknown[]) => qc.getQueryState(key)?.isInvalidated;

    await qc.invalidateQueries({ queryKey: qk.games(3) });
    expect([keys[0], keys[1], keys[2]].every(stale)).toBe(true);
    expect([keys[3], keys[4], keys[5], keys[6]].some(stale)).toBe(false);

    await qc.invalidateQueries({ queryKey: qk.game(7) });
    await qc.invalidateQueries({ queryKey: qk.party() });
    await qc.invalidateQueries({ queryKey: qk.playDefaults(3) });
    await qc.invalidateQueries({ queryKey: qk.stats(3) });
    expect(keys.every(stale)).toBe(true);
  });

  it('does not touch other parties', async () => {
    const qc = new QueryClient();
    qc.setQueryData(qk.gamePlays(4, 7), 'x');
    await qc.invalidateQueries({ queryKey: qk.games(3) });
    expect(qc.getQueryState(qk.gamePlays(4, 7))?.isInvalidated).toBe(false);
  });
});
