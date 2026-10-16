import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
import { GameSearch } from '../components/GameSearch';
import { LoadMore } from '../components/LoadMore';
import { PageTitle } from '../components/PageTitle';
import { ListSkeleton } from '../components/Skeleton';
import { DEFAULT_MANAGE_GAMES_SETTINGS, loadGameListSettings, MANAGE_GAMES_KEY, saveGameListSettings } from '../lib/listSettings';
import { SCORE_TYPE_LABELS } from '../lib/scoring';
import { useGameList } from '../lib/useGameList';

/** Správa her: hledání a lazy-loading seznam her (výchozí řazení podle názvu), odtud se hry zakládají a upravují. */
export function GamesPage() {
  const party = useActiveParty();
  const [settings, setSettings] = useState(() => loadGameListSettings(MANAGE_GAMES_KEY, DEFAULT_MANAGE_GAMES_SETTINGS));
  const patch = (changes: Partial<typeof settings>) => setSettings((s) => ({ ...s, ...changes }));
  useEffect(() => saveGameListSettings(settings, MANAGE_GAMES_KEY), [settings]);
  const { games, items, query } = useGameList(party.id, settings);

  return (
    <section className="page">
      <PageTitle>Správa her</PageTitle>
      <div className="pad">
        <Actions><Link className="btn" to="/games/new">Nová hra</Link></Actions>
      </div>
      <div className="list-top">
        <GameSearch partyId={party.id} settings={settings} onChange={patch} />
      </div>

      {games.error && <div className="pad"><ErrorMessage error={games.error} /></div>}
      {games.isPending && <ListSkeleton />}
      {games.data && items.length === 0 && (
        <p className="empty">{query ? <>Hledání „<b className="primary">{query}</b>“ nic nenašlo.</> : 'Zatím tu nejsou žádné hry.'}</p>
      )}

      <ul className={`list${games.isPlaceholderData ? ' stale' : ''}`}>
        {items.map((g) => (
          <li key={g.id}>
            <Link to={`/games/${g.id}/edit`} className="list-item">
              <GameImage url={g.imageUrl} />
              <div className="list-item-body">
                <strong className="h5">{g.name}</strong>
                <span className="secondary">{SCORE_TYPE_LABELS[g.scoreType]}</span>
              </div>
            </Link>
          </li>
        ))}
      </ul>

      <LoadMore hasMore={games.hasNextPage} loading={games.isFetchingNextPage} failed={games.isFetchNextPageError} onLoad={() => games.fetchNextPage()} />
    </section>
  );
}
