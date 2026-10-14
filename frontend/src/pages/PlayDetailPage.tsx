import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ApiError } from '../api/client';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
import { Icon } from '../components/Icon';
import { formatDate, formatDuration, formatTime } from '../lib/datetime';
import { refreshAfterPlayChange } from '../lib/refresh';
import { formatScore, SCORE_TYPE_LABELS } from '../lib/scoring';

export function PlayDetailPage() {
  const party = useActiveParty();
  const id = Number(useParams().id);
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [confirmDelete, setConfirmDelete] = useState(false);
  const { data: play, isPending, error } = useQuery({ queryKey: ['play', id], queryFn: () => api.play(id), retry: false });

  const gameId = play?.game.id;
  const remove = useMutation({
    mutationFn: () => api.deletePlay(id),
    onSuccess: async () => {
      queryClient.removeQueries({ queryKey: ['play', id] });
      await refreshAfterPlayChange(queryClient, party.id, gameId!, { leaving: true });
      navigate(`/games/${gameId}`, { replace: true });
    },
  });

  if (error instanceof ApiError && (error.status === 404 || error.status === 403)) {
    return (
      <section className="detail">
        <p className="empty">Tenhle zápis neexistuje nebo k němu nemáš přístup.</p>
        <div className="nav-actions"><Link className="btn secondary" to="/games">Zpět</Link></div>
      </section>
    );
  }
  if (isPending) return <p className="muted pad">Načítám…</p>;
  if (!play) return <section className="detail"><ErrorMessage error={error} /></section>;

  const duration = formatDuration(play.startedAt, play.endedAt);
  // Převedené záznamy ze staré aplikace mají začátek = konec, proto ukazujeme jen jeden čas.
  const when = !duration
    ? `${formatDate(play.startedAt)} ${formatTime(play.startedAt)}`
    : `${formatDate(play.startedAt)} ${formatTime(play.startedAt)} – ${formatDate(play.startedAt) === formatDate(play.endedAt) ? '' : `${formatDate(play.endedAt)} `}${formatTime(play.endedAt)} (${duration})`;
  const showScore = play.game.scoreType === 'most_points' || play.game.scoreType === 'fewest_points';
  const details: [string, string][] = [
    ['Kdy', when],
    ['Místo', play.place ?? ''],
    ['Přídavky', play.addons.map((a) => a.name).join(', ')],
    ['Tagy', play.tags.map((t) => `#${t}`).join(' ')],
    ['Poznámka', play.notes ?? ''],
  ];

  return (
    <section className="detail">
      <div className="detail-game">
        <GameImage url={play.game.imageUrl} small />
        <div>
          <Link className="h5 plain" to={`/games/${play.game.id}`}>{play.game.name}</Link>
          <small className="muted">{SCORE_TYPE_LABELS[play.game.scoreType]}</small>
        </div>
      </div>

      <div className="detail-info">
        {details.filter(([, value]) => value).map(([label, value]) => (
          <div key={label}><span className="primary">{label}: </span>{value}</div>
        ))}
      </div>

      <ul className="results" aria-label="Výsledky">
        {play.results.map((r) => (
          <li key={r.playerId} className={r.winner ? 'winner' : ''}>
            <span className="result-icon">{r.winner && <Icon name="workspace_premium" />}</span>
            <span className="result-name">{r.name}{r.winner && <span className="sr-only"> (vítěz)</span>}</span>
            {showScore && <span className="result-score">{r.score === null ? '' : formatScore(r.score)}</span>}
            {play.game.scoreType === 'rank_order' && <span className="result-score muted">{r.rank}.</span>}
          </li>
        ))}
      </ul>

      <ErrorMessage error={remove.error} />
      <div className="nav-actions">
        <button type="button" className="btn outline-secondary" disabled={remove.isPending} onClick={() => (confirmDelete ? remove.mutate() : setConfirmDelete(true))}>
          {confirmDelete ? 'Potvrď smazání' : 'Smazat zápis'}
        </button>
        <Link className="btn secondary" to={`/games/${play.game.id}`}>Zpět</Link>
      </div>
    </section>
  );
}
