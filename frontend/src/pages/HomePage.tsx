import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
import { formatDate, formatDateTime } from '../lib/datetime';
import { groupBySession, winnerSummary } from '../lib/scoring';

export function HomePage() {
  const party = useActiveParty();
  const queryClient = useQueryClient();
  const { data: plays, isPending, error } = useQuery({ queryKey: ['plays', party.id], queryFn: () => api.plays(party.id) });
  const remove = useMutation({
    mutationFn: (id: number) => api.deletePlay(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['plays', party.id] }),
  });

  return (
    <section>
      <Link className="btn block" to="/plays/new">+ Nový zápis</Link>
      <ErrorMessage error={error ?? remove.error} />
      {isPending && <p className="muted">Načítám…</p>}
      {plays?.length === 0 && <p className="empty">Zatím žádné zápisy. Přidej první!</p>}
      {plays &&
        groupBySession(plays).map((group) => (
          <div key={group.sessionId} className="session">
            <h3>{formatDate(group.plays[group.plays.length - 1].startedAt)}</h3>
            <ul className="list">
              {group.plays.map((play) => (
                <li key={play.id} className="play">
                  <GameImage url={play.game.imageUrl} name={play.game.name} />
                  <div className="play-body">
                    <strong>{play.game.name}</strong>
                    <span>🏆 {winnerSummary(play)}</span>
                    <small className="muted">{formatDateTime(play.startedAt)}{play.place ? ` · ${play.place}` : ''}</small>
                    {play.tags.length > 0 && <small className="muted">{play.tags.map((t) => `#${t}`).join(' ')}</small>}
                  </div>
                  <button
                    type="button"
                    className="icon-btn"
                    aria-label={`Smazat zápis ${play.game.name}`}
                    onClick={() => window.confirm('Opravdu smazat zápis?') && remove.mutate(play.id)}
                  >
                    ✕
                  </button>
                </li>
              ))}
            </ul>
          </div>
        ))}
    </section>
  );
}
