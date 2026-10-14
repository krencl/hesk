import { keepPreviousData, useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import type { GameSort, GameWithStats } from '../api/types';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
import { Icon } from '../components/Icon';
import { ListSkeleton } from '../components/Skeleton';
import { formatDateTimeShort } from '../lib/datetime';
import { loadGameListSettings, saveGameListSettings } from '../lib/listSettings';
import { useDebounced } from '../lib/useDebounced';

const PAGE_SIZE = 20;

export function GameListPage() {
  const party = useActiveParty();
  const [settings, setSettings] = useState(loadGameListSettings);
  const patch = (changes: Partial<typeof settings>) => setSettings((s) => ({ ...s, ...changes }));
  useEffect(() => saveGameListSettings(settings), [settings]);

  const query = useDebounced(settings.query.trim());
  const sessionNumber = settings.filterVisible ? settings.sessionNumber : null;
  const sessions = useQuery({ queryKey: ['sessions', party.id], queryFn: () => api.sessions(party.id), enabled: settings.filterVisible });

  const games = useInfiniteQuery({
    queryKey: ['games', party.id, 'list', { query, sort: settings.sort, sessionNumber }],
    initialPageParam: 0,
    queryFn: async ({ pageParam }) => {
      // Žádáme o jednu položku navíc, abychom věděli, zda existuje další stránka.
      const rows = await api.games(party.id, { q: query, sort: settings.sort, sessionNumber, limit: PAGE_SIZE + 1, offset: pageParam });
      return { items: rows.slice(0, PAGE_SIZE), hasMore: rows.length > PAGE_SIZE };
    },
    getNextPageParam: (last, all) => (last.hasMore ? all.length * PAGE_SIZE : undefined),
    placeholderData: keepPreviousData,
  });
  const items = games.data?.pages.flatMap((p) => p.items) ?? [];

  const toggleFilter = () => patch({ filterVisible: !settings.filterVisible, sessionNumber: null });

  return (
    <section className="flush game-list">
      <div className="search">
        <div className="search-row">
          <button type="button" className="search-icon" aria-label={settings.query ? 'Smazat hledání' : 'Hledání'} onClick={() => patch({ query: '' })}>
            <Icon name={settings.query ? 'close' : 'search'} />
          </button>
          <input
            type="search"
            value={settings.query}
            onChange={(e) => patch({ query: e.target.value })}
            aria-label="Hledat hru"
            autoComplete="off"
            enterKeyHint="search"
          />
          <button type="button" className={`search-filter-icon${settings.filterVisible ? ' active' : ''}`} aria-label="Filtr" aria-pressed={settings.filterVisible} onClick={toggleFilter}>
            <Icon name={settings.filterVisible ? 'filter_alt_off' : 'filter_alt'} />
          </button>
        </div>
        {settings.filterVisible && (
          <div className="search-filter">
            <label className="filter-label" htmlFor="sort">Řazení</label>
            <select id="sort" value={settings.sort} onChange={(e) => patch({ sort: e.target.value as GameSort })}>
              <option value="activity">Aktivita</option>
              <option value="name">Název</option>
            </select>
            <label className="filter-label" htmlFor="session">Sezení</label>
            <select id="session" value={settings.sessionNumber ?? ''} onChange={(e) => patch({ sessionNumber: e.target.value ? Number(e.target.value) : null })}>
              <option value="">Všechny</option>
              {sessions.data?.map((s) => <option key={s.id} value={s.number}>{s.number}</option>)}
            </select>
          </div>
        )}
      </div>

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

      {games.hasNextPage && (
        <button type="button" className="btn light block more" disabled={games.isFetchingNextPage} onClick={() => games.fetchNextPage()}>
          Načíst další
        </button>
      )}
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
