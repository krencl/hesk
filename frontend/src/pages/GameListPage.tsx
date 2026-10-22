import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import type { GameWithStats } from '../api/types';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameListItem } from '../components/GameListItem';
import { GameSearch } from '../components/GameSearch';
import { LoadMore } from '../components/LoadMore';
import { ListSkeleton } from '../components/Skeleton';
import { formatDateTimeShort } from '../lib/datetime';
import { loadGameListSettings, saveGameListSettings } from '../lib/listSettings';
import { useGameList } from '../lib/useGameList';

export function GameListPage() {
  const party = useActiveParty();
  const [settings, setSettings] = useState(() => loadGameListSettings());
  const patch = (changes: Partial<typeof settings>) => setSettings((s) => ({ ...s, ...changes }));
  useEffect(() => saveGameListSettings(settings), [settings]);
  const { games, items, query, sessionNumber } = useGameList(party.id, settings);

  return (
    <section className="page game-list">
      <GameSearch partyId={party.id} settings={settings} onChange={patch} withSession />

      {games.error && <div className="pad"><ErrorMessage error={games.error} /></div>}
      {games.isPending && <ListSkeleton />}

      {games.data && items.length === 0 && (
        <p className="empty">
          {query ? <>Hledání „<b className="primary">{query}</b>“ nic nenašlo.</> : sessionNumber ? 'V tomhle sezení se nehrála žádná hra.' : 'Ještě jste nic nehráli, je čas to napravit!'}
          {!query && !sessionNumber && <Link className="btn" to="/games/new">Přidat hru</Link>}
        </p>
      )}

      <ul className={`list${games.isPlaceholderData ? ' stale' : ''}`}>
        {items.map((game) => (
          <GameListItem key={game.id} to={`/games/${game.id}`} imageUrl={game.imageUrl} name={game.name}>
            <LastPlay game={game} />
          </GameListItem>
        ))}
      </ul>

      <LoadMore hasMore={games.hasNextPage} loading={games.isFetchingNextPage} failed={games.isFetchNextPageError} onLoad={() => games.fetchNextPage()} />
    </section>
  );
}

function LastPlay({ game }: { game: GameWithStats }) {
  if (!game.lastPlay) return <span className="secondary">Zatím nehráno</span>;
  return (
    <span className="secondary last-play">
      {formatDateTimeShort(game.lastPlay.startedAt)} #{game.lastPlay.sessionNumber}
      <br />
      {game.lastPlay.winners.join(', ')}
    </span>
  );
}
