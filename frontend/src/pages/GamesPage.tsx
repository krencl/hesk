import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { Actions } from '../components/Actions';
import { GameListItem } from '../components/GameListItem';
import { GameSearch } from '../components/GameSearch';
import { GameListView } from '../components/GameListView';
import { PageTitle } from '../components/PageTitle';
import { DEFAULT_MANAGE_GAMES_SETTINGS, MANAGE_GAMES_KEY } from '../lib/listSettings';
import { SCORE_TYPE_LABELS } from '../lib/scoring';
import { useGameList } from '../lib/useGameList';
import { useGameListSettings } from '../lib/useGameListSettings';

/** Správa her: hledání a lazy-loading seznam her (výchozí řazení podle názvu), odtud se hry zakládají a upravují. */
export function GamesPage() {
  const party = useActiveParty();
  const [settings, patch] = useGameListSettings(MANAGE_GAMES_KEY, DEFAULT_MANAGE_GAMES_SETTINGS);
  const list = useGameList(party.id, settings);

  return (
    <section className="page">
      <PageTitle>Správa her</PageTitle>
      <div className="list-top">
        <GameSearch partyId={party.id} settings={settings} onChange={patch} />
      </div>
      <GameListView
        list={list}
        emptyText="Ještě jste nic nehráli, je čas to napravit!"
        renderItem={(g) => (
          <GameListItem key={g.id} to={`/games/${g.id}/edit`} imageUrl={g.imageUrl} name={g.name}>
            <span className="secondary">{SCORE_TYPE_LABELS[g.scoreType]}</span>
          </GameListItem>
        )}
      />

      <Actions><Link className="btn" to="/games/new">Přidat hru</Link></Actions>
    </section>
  );
}
