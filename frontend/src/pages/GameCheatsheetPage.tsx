import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameHeader } from '../components/GameHeader';
import { SafeHtml } from '../components/SafeHtml';
import { renderCheatSheet } from '../lib/cheatSheet';
import { ListSkeleton } from '../components/Skeleton';

export function GameCheatsheetPage() {
  const id = Number(useParams().id);
  const game = useQuery({ queryKey: ['game', id], queryFn: () => api.game(id), retry: false });

  if (!game.data) return game.error ? <section className="page pad"><ErrorMessage error={game.error} /></section> : <ListSkeleton rows={1} />;

  return (
    <section className="page game-detail">
      <GameHeader name={game.data.name} imageUrl={game.data.imageUrl} />
      <div className="cheatsheet">
        {game.data.cheatSheet ? <SafeHtml html={renderCheatSheet(game.data.cheatSheet)} /> : <p className="muted">Tahle hra nemá cheatsheet.</p>}
      </div>
      <div className="pad">
        <Actions><Link className="btn outline-secondary" to={`/games/${id}`}>Zpět</Link></Actions>
      </div>
    </section>
  );
}
