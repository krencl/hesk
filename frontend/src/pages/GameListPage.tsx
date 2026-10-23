import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import type { GameWithStats } from '../api/types';
import { GameListItem } from '../components/GameListItem';
import { GameSearch } from '../components/GameSearch';
import { GameListView } from '../components/GameListView';
import { formatDateTimeShort } from '../lib/datetime';
import { canWrite } from '../lib/roles';
import { useGameList } from '../lib/useGameList';
import { useGameListSettings } from '../lib/useGameListSettings';

export function GameListPage() {
  const party = useActiveParty();
  const [settings, patch] = useGameListSettings();
  const list = useGameList(party.id, settings);
  const { sessionNumber } = list;

  return (
    <section className="page game-list">
      <GameSearch partyId={party.id} settings={settings} onChange={patch} withSession />
      <GameListView
        list={list}
        emptyText={sessionNumber ? 'V tomhle sezení se nehrála žádná hra.' : 'Ještě jste nic nehráli, je čas to napravit!'}
        emptyAction={!sessionNumber && canWrite(party.role) ? <Link className="btn" to="/games/new">Přidat hru</Link> : undefined}
        renderItem={(game) => (
          <GameListItem key={game.id} to={`/games/${game.id}`} imageUrl={game.imageUrl} name={game.name}>
            <LastPlay game={game} />
          </GameListItem>
        )}
      />
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
