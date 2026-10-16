import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ApiError } from '../api/client';
import type { Play } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameHeader } from '../components/GameHeader';
import { Icon } from '../components/Icon';
import { ListSkeleton } from '../components/Skeleton';
import { formatDateShort, formatDateTimeShort } from '../lib/datetime';
import { formatScore, winners } from '../lib/scoring';

const PAGE_SIZE = 10;

export function GameDetailPage() {
  const party = useActiveParty();
  const id = Number(useParams().id);
  // undefined = výchozí (první zápis rozbalený jako ve starém prototypu), null = vše sbaleno
  const [openId, setOpenId] = useState<number | null | undefined>(undefined);

  const game = useQuery({ queryKey: ['game', id], queryFn: () => api.game(id), retry: false });
  const rounds = useInfiniteQuery({
    queryKey: ['games', party.id, 'plays', id],
    initialPageParam: 0,
    queryFn: async ({ pageParam }) => {
      const rows = await api.plays(party.id, PAGE_SIZE + 1, pageParam, id);
      return { items: rows.slice(0, PAGE_SIZE), hasMore: rows.length > PAGE_SIZE };
    },
    getNextPageParam: (last, all) => (last.hasMore ? all.length * PAGE_SIZE : undefined),
  });
  const plays = rounds.data?.pages.flatMap((p) => p.items) ?? [];
  const selectedId = openId === undefined ? plays[0]?.id : openId;

  if (game.error instanceof ApiError && (game.error.status === 404 || game.error.status === 403)) {
    return (
      <section className="page pad">
        <p className="empty">Tahle hra neexistuje nebo k ní nemáš přístup.</p>
        <Actions><Link className="btn outline-secondary" to="/games">Zpět</Link></Actions>
      </section>
    );
  }
  if (!game.data) return game.error ? <section className="page pad"><ErrorMessage error={game.error} /></section> : <ListSkeleton rows={1} />;

  const remaining = Math.max(0, game.data.playCount - plays.length);

  return (
    <section className="page game-detail">
      <GameHeader name={game.data.name} imageUrl={game.data.imageUrl} />

      {rounds.isPending && <ListSkeleton rows={4} withImage={false} />}
      <ErrorMessage error={rounds.error} />
      {rounds.data && plays.length === 0 && <p className="center">Dosud žádný zápis. Je čas to napravit!</p>}

      <div className="rounds">
        {plays.map((play) => (
          <Round
            key={play.id}
            play={play}
            open={play.id === selectedId}
            onToggle={() => setOpenId(play.id === selectedId ? null : play.id)}
          />
        ))}
      </div>

      {rounds.hasNextPage && (
        <button type="button" className="btn light block more" disabled={rounds.isFetchingNextPage} onClick={() => rounds.fetchNextPage()}>
          Načíst další záznamy {remaining > 0 && <span className="secondary">(zbývá {remaining})</span>}
        </button>
      )}

      <div className="pad">
        <Actions>
          <Link className="btn" to={`/games/${id}/new-play`}>Nový zápis</Link>
          {game.data.cheatSheet && <Link className="btn outline" to={`/games/${id}/cheatsheet`}>Taháček</Link>}
          <Link className="btn outline-secondary" to="/games">Zpět</Link>
        </Actions>
      </div>
    </section>
  );
}

function Round({ play, open, onToggle }: { play: Play; open: boolean; onToggle: () => void }) {
  const scored = play.game.scoreType === 'most_points' || play.game.scoreType === 'fewest_points';
  const won = winners(play);
  const winnerScore = scored && won[0]?.score != null ? formatScore(won[0].score) : '';

  const details: [string, string][] = [
    ['Verze hry', play.addons.map((a) => a.name).join(', ')],
    ['Číslo sezení', String(play.sessionNumber)],
    ['Místo', play.place ?? ''],
    ['Tagy', play.tags.join(', ')],
    ['Poznámka', play.notes ?? ''],
  ];

  return (
    <div className={`round${open ? ' selected' : ''}`}>
      <button type="button" className="round-head" aria-expanded={open} onClick={onToggle}>
        <span className="round-when secondary">{formatDateTimeShort(play.startedAt)} #{play.sessionNumber}</span>
        <strong className="round-winners">{won.map((r) => r.name).join(', ')}</strong>
        <strong className="round-score">{winnerScore}</strong>
      </button>

      {open && (
        <div className="round-body">
          <div className="detail-info">
            {details.filter(([, value]) => value).map(([label, value]) => (
              <div key={label}><span className="primary">{label}: </span>{value}</div>
            ))}
          </div>
          <ul className="results" aria-label={`Výsledky ${formatDateShort(play.startedAt)}`}>
            {play.results.map((r) => (
              <li key={r.playerId} className={r.winner ? 'winner' : ''}>
                <span className="result-icons">
                  <span>{r.winner && <Icon name="workspace_premium" />}</span>
                  <span>{r.starting && <Icon name="looks_one" />}</span>
                </span>
                <span className="result-name">{r.name}{r.winner && <span className="sr-only"> (vítěz)</span>}{r.starting && <span className="sr-only"> (začínal)</span>}</span>
                {scored && <span className="result-score">{r.score === null ? '' : formatScore(r.score)}</span>}
                {play.game.scoreType === 'rank_order' && <span className="result-score muted">{r.rank}.</span>}
              </li>
            ))}
          </ul>
          <Actions>
            <Link className="btn outline-secondary" to={`/plays/${play.id}/edit`}>Upravit</Link>
          </Actions>
        </div>
      )}
    </div>
  );
}
