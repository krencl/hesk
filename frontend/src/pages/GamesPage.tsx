import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
import { SCORE_TYPE_LABELS } from '../lib/scoring';

export function GamesPage() {
  const party = useActiveParty();
  const { data: games, isPending, error } = useQuery({ queryKey: ['games', party.id, 'manage'], queryFn: () => api.games(party.id, { sort: 'name' }) });

  return (
    <section className="flush">
      <div className="pad">
        <h2>Správa her</h2>
        <Link className="btn block" to="/games/new">+ Nová hra</Link>
        <ErrorMessage error={error} />
        {isPending && <p className="muted">Načítám…</p>}
      </div>
      <ul className="list">
        {games?.map((g) => (
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
    </section>
  );
}
