import { keepPreviousData, useInfiniteQuery } from '@tanstack/react-query';
import { api } from '../api';
import type { GameWithStats } from '../api/types';
import type { GameListSettings } from './listSettings';
import { useDebounced } from './useDebounced';

export const GAME_PAGE_SIZE = 20;

/** Stránkovaný (lazy-loading) seznam her party podle hledání a filtru; sdílí ho seznam her i správa her. */
export function useGameList(partyId: number, settings: GameListSettings) {
  const query = useDebounced(settings.query.trim());
  const sessionNumber = settings.filterVisible ? settings.sessionNumber : null;

  const games = useInfiniteQuery({
    queryKey: ['games', partyId, 'list', { query, sort: settings.sort, sessionNumber }],
    initialPageParam: 0,
    queryFn: async ({ pageParam }) => {
      // Žádáme o jednu položku navíc, abychom věděli, zda existuje další stránka.
      const rows = await api.games(partyId, { q: query, sort: settings.sort, sessionNumber, limit: GAME_PAGE_SIZE + 1, offset: pageParam });
      return { items: rows.slice(0, GAME_PAGE_SIZE), hasMore: rows.length > GAME_PAGE_SIZE };
    },
    getNextPageParam: (last, all) => (last.hasMore ? all.length * GAME_PAGE_SIZE : undefined),
    placeholderData: keepPreviousData,
  });
  const items: GameWithStats[] = games.data?.pages.flatMap((p) => p.items) ?? [];

  return { games, items, query, sessionNumber };
}
