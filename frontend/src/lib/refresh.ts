import type { QueryClient } from '@tanstack/react-query';

/**
 * Po vytvoření/smazání zápisu zneplatní vše, co z něj vychází (seznamy her, detail hry, chronologie, statistiky).
 * Při odchodu ze stránky (`leaving`) se navíc zahodí seznam kol dané hry, aby se po návratu na detail hry
 * nejdřív neukázala stará data bez nového zápisu.
 */
export async function refreshAfterPlayChange(queryClient: QueryClient, partyId: number, gameId: number, { leaving = false } = {}): Promise<void> {
  if (leaving) queryClient.removeQueries({ queryKey: ['games', partyId, 'plays', gameId] });
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: ['games', partyId] }),
    queryClient.invalidateQueries({ queryKey: ['game', gameId] }),
    queryClient.invalidateQueries({ queryKey: ['plays', partyId] }),
    queryClient.invalidateQueries({ queryKey: ['stats', partyId] }),
  ]);
}
