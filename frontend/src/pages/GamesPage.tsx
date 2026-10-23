import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameListItem } from '../components/GameListItem';
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
      <div className="list-top">
        <GameSearch partyId={party.id} settings={settings} onChange={patch} />
      </div>

      {games.error && <div className="pad"><ErrorMessage error={games.error} /></div>}
      {games.isPending && <ListSkeleton />}
      {games.data && items.length === 0 && (
        <p className="empty"><span>{query ? <>Hledání „<b className="primary">{query}</b>“ nic nenašlo.</> : 'Ještě jste nic nehráli, je čas to napravit!'}</span></p>
      )}

      <ul className={`list${games.isPlaceholderData ? ' stale' : ''}`}>
        {items.map((g) => (
          <GameListItem key={g.id} to={`/games/${g.id}/edit`} imageUrl={g.imageUrl} name={g.name}>
            <span className="secondary">{SCORE_TYPE_LABELS[g.scoreType]}</span>
          </GameListItem>
        ))}
      </ul>

      <LoadMore hasMore={games.hasNextPage} loading={games.isFetchingNextPage} failed={games.isFetchNextPageError} onLoad={() => games.fetchNextPage()} />

      <Actions><Link className="btn" to="/games/new">Přidat hru</Link></Actions>
    </section>
  );
}
