import type { ReactNode } from 'react';
import type { GameWithStats } from '../api/types';
import type { useGameList } from '../lib/useGameList';
import { ErrorMessage } from './ErrorMessage';
import { LoadMore } from './LoadMore';
import { ListSkeleton } from './Skeleton';

interface Props {
  list: ReturnType<typeof useGameList>;
  /** Jeden řádek seznamu (`GameListItem`). */
  renderItem: (game: GameWithStats) => ReactNode;
  /** Věta, když nic není a nehledá se. */
  emptyText: string;
  /** Tlačítko pod větou o prázdném seznamu. */
  emptyAction?: ReactNode;
}

/** Stav načítání, chyba, prázdný seznam, řádky a dotahování dalších stránek; sdílí ho seznam her i správa her. */
export function GameListView({ list: { games, items, query }, renderItem, emptyText, emptyAction }: Props) {
  return (
    <>
      {games.error && <div className="pad"><ErrorMessage error={games.error} /></div>}
      {games.isPending && <ListSkeleton />}

      {games.data && items.length === 0 && (
        <p className="empty">
          <span>{query ? <>Hledání „<b className="primary">{query}</b>“ nic nenašlo.</> : emptyText}</span>
          {emptyAction}
        </p>
      )}

      <ul className={`list${games.isPlaceholderData ? ' stale' : ''}`}>{items.map(renderItem)}</ul>

      <LoadMore hasMore={games.hasNextPage} loading={games.isFetchingNextPage} failed={games.isFetchNextPageError} onLoad={() => games.fetchNextPage()} />
    </>
  );
}
