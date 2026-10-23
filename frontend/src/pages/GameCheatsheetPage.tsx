import { Link, useParams } from 'react-router-dom';
import { Actions } from '../components/Actions';
import { GameHeader } from '../components/GameHeader';
import { LoadingOrError } from '../components/PageState';
import { SafeHtml } from '../components/SafeHtml';
import { renderCheatSheet } from '../lib/cheatSheet';
import { useGame } from '../lib/useGame';

export function GameCheatsheetPage() {
  const id = Number(useParams().id);
  const game = useGame(id);

  if (!game.data) return <LoadingOrError error={game.error} rows={1} />;

  return (
    <section className="page game-detail">
      <GameHeader name={game.data.name} imageUrl={game.data.imageUrl} />
      <div className="cheatsheet">
        {game.data.cheatSheet ? <SafeHtml html={renderCheatSheet(game.data.cheatSheet)} /> : <p className="muted">Tahle hra nemá cheatsheet.</p>}
      </div>
      <Actions><Link className="btn outline-secondary" to={`/games/${id}`}>Zpět</Link></Actions>
    </section>
  );
}
