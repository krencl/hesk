import type { QueryClient } from '@tanstack/react-query';
import { qk } from '../api/queryKeys';

/**
 * Po vytvoření/smazání zápisu zneplatní vše, co z něj vychází (seznamy her, detail hry, chronologie, statistiky).
 * Při odchodu ze stránky (`leaving`) se navíc zahodí seznam kol dané hry, aby se po návratu na detail hry
 * nejdřív neukázala stará data bez nového zápisu.
 */
export async function refreshAfterPlayChange(queryClient: QueryClient, partyId: number, gameId: number, { leaving = false } = {}): Promise<void> {
  if (leaving) queryClient.removeQueries({ queryKey: qk.gamePlays(partyId, gameId) });
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: qk.games(partyId) }),
    queryClient.invalidateQueries({ queryKey: qk.game(gameId) }),
    queryClient.invalidateQueries({ queryKey: qk.stats(partyId) }),
    // nové tagy z uloženého zápisu a posun čísla sezení pro další zápis
    queryClient.invalidateQueries({ queryKey: qk.tags(partyId) }),
    queryClient.invalidateQueries({ queryKey: qk.playDefaults(partyId) }),
  ]);
}
