import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
import { SCORE_TYPE_LABELS } from '../lib/scoring';

export function GamesPage() {
  const party = useActiveParty();
  const { data: games, isPending, error } = useQuery({ queryKey: ['games', party.id], queryFn: () => api.games(party.id) });

  return (
    <section>
      <h2>Hry</h2>
      <Link className="btn block" to="/games/new">+ Nová hra</Link>
      <ErrorMessage error={error} />
      {isPending && <p className="muted">Načítám…</p>}
      <ul className="list">
        {games?.map((g) => (
          <li key={g.id}>
            <Link to={`/games/${g.id}`} className="play">
              <GameImage url={g.imageUrl} name={g.name} />
              <div className="play-body">
                <strong>{g.name}</strong>
                <small className="muted">{SCORE_TYPE_LABELS[g.scoreType]}</small>
              </div>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
