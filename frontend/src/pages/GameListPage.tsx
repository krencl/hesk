import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import type { GameWithStats } from '../api/types';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
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
          {query ? <>Hledání „<b className="primary">{query}</b>“ nic nenašlo.</> : sessionNumber ? 'V tomhle sezení se nehrála žádná hra.' : 'Zatím tu nejsou žádné hry.'}
          {!query && !sessionNumber && <Link className="btn" to="/games/new">Přidat hru</Link>}
        </p>
      )}

      <ul className={`list${games.isPlaceholderData ? ' stale' : ''}`}>
        {items.map((game) => (
          <li key={game.id}>
            <Link to={`/games/${game.id}`} className="list-item">
              <GameImage url={game.imageUrl} />
              <div className="list-item-body">
                <strong className="h5">{game.name}</strong>
                <LastPlay game={game} />
              </div>
            </Link>
          </li>
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
