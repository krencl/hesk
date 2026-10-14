import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
import { Icon } from '../components/Icon';
import { formatDate, formatDateTime } from '../lib/datetime';
import { groupBySession, winnerSummary } from '../lib/scoring';

export function HomePage() {
  const party = useActiveParty();
  const { data: plays, isPending, error } = useQuery({ queryKey: ['plays', party.id], queryFn: () => api.plays(party.id) });

  return (
    <section className="flush">
      <div className="pad">
        <h2>Zápisy</h2>
        <Link className="btn block" to="/plays/new">+ Nový zápis</Link>
        <ErrorMessage error={error} />
        {isPending && <p className="muted">Načítám…</p>}
        {plays?.length === 0 && <p className="empty">Zatím žádné zápisy. Přidej první!</p>}
      </div>
      {plays &&
        groupBySession(plays).map((group) => (
          <div key={group.sessionId} className="session">
            <h3>{formatDate(group.plays[group.plays.length - 1].startedAt)}</h3>
            <ul className="list">
              {group.plays.map((play) => (
                <li key={play.id}>
                  <Link to={`/plays/${play.id}`} className="list-item">
                    <GameImage url={play.game.imageUrl} />
                    <div className="list-item-body">
                      <strong className="h5">{play.game.name}</strong>
                      <span className="secondary">
                        {formatDateTime(play.startedAt)}{play.place ? ` · ${play.place}` : ''}
                      </span>
                      <span className="secondary"><Icon name="workspace_premium" /> {winnerSummary(play)}</span>
                      {play.tags.length > 0 && <span className="secondary small">{play.tags.map((t) => `#${t}`).join(' ')}</span>}
                    </div>
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
    </section>
  );
}
