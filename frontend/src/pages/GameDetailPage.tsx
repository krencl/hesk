import { useInfiniteQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import type { Play } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { LoadMore } from '../components/LoadMore';
import { GameHeader } from '../components/GameHeader';
import { GameTopWinners } from '../components/GameTopWinners';
import { Icon } from '../components/Icon';
import { EmptyState, LoadingOrError, isNotFound } from '../components/PageState';
import { ListSkeleton } from '../components/Skeleton';
import { formatDateShort, formatDateTimeShort } from '../lib/datetime';
import { canWrite } from '../lib/roles';
import { useGame } from '../lib/useGame';
import { isScored } from '../lib/playForm';
import { formatScore, winners } from '../lib/scoring';
import { qk } from '../api/queryKeys';

const PAGE_SIZE = 10;

export function GameDetailPage() {
  const party = useActiveParty();
  const id = Number(useParams().id);
  // undefined = výchozí (první zápis rozbalený jako ve starém prototypu), null = vše sbaleno
  const [openId, setOpenId] = useState<number | null | undefined>(undefined);

  const game = useGame(id);
  const rounds = useInfiniteQuery({
    queryKey: qk.gamePlays(party.id, id),
    initialPageParam: 0,
    queryFn: async ({ pageParam }) => {
      const rows = await api.plays(party.id, PAGE_SIZE + 1, pageParam, id);
      return { items: rows.slice(0, PAGE_SIZE), hasMore: rows.length > PAGE_SIZE };
    },
    getNextPageParam: (last, all) => (last.hasMore ? all.length * PAGE_SIZE : undefined),
  });
  const plays = rounds.data?.pages.flatMap((p) => p.items) ?? [];
  const selectedId = openId === undefined ? plays[0]?.id : openId;

  if (isNotFound(game.error)) return <EmptyState message="Tahle hra neexistuje nebo k ní nemáš přístup." backTo="/" backLabel="Zpět" />;
  if (!game.data) return <LoadingOrError error={game.error} rows={1} />;

  const remaining = Math.max(0, game.data.playCount - plays.length);

  return (
    <section className="page game-detail">
      <GameHeader name={game.data.name} imageUrl={game.data.imageUrl} />
      {game.data.playCount > 0 && <GameTopWinners partyId={party.id} gameId={id} gameName={game.data.name} />}

      {rounds.isPending && <ListSkeleton rows={4} withImage={false} />}
      <ErrorMessage error={rounds.error} />
      {rounds.data && plays.length === 0 && <p className="center">Dosud žádný zápis. Je čas to napravit!</p>}

      <div className="rounds">
        {plays.map((play) => (
          <Round
            key={play.id}
            play={play}
            canEdit={canWrite(party.role)}
            open={play.id === selectedId}
            onToggle={() => setOpenId(play.id === selectedId ? null : play.id)}
          />
        ))}
      </div>

      <LoadMore
        hasMore={rounds.hasNextPage}
        loading={rounds.isFetchingNextPage}
        failed={rounds.isFetchNextPageError}
        onLoad={() => rounds.fetchNextPage()}
        label={`Načíst další záznamy${remaining > 0 ? ` (zbývá ${remaining})` : ''}`}
      />

      <Actions>
        {canWrite(party.role) && <Link className="btn" to={`/games/${id}/new-play`}>Nový zápis</Link>}
        {game.data.cheatSheet && <Link className="btn outline" to={`/games/${id}/cheatsheet`}>Cheatsheet</Link>}
        <Link className="btn outline-secondary" to="/">Zpět</Link>
      </Actions>
    </section>
  );
}

function Round({ play, canEdit, open, onToggle }: { play: Play; canEdit: boolean; open: boolean; onToggle: () => void }) {
  const scored = isScored(play.game.scoreType);
  const won = winners(play);
  const winnerScore = scored && won[0]?.score != null ? formatScore(won[0].score) : '';

  const details: [string, string][] = [
    ['Rozšíření', play.addons.map((a) => a.name).join(', ')],
    ['Číslo sezení', String(play.sessionNumber)],
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
                <span className={`result-icons${r.winner && r.starting ? ' both' : ''}`}>
                  {r.starting && <Icon name="looks_one" className="icon-starting" />}
                  {r.winner && <Icon name="workspace_premium" className="icon-winner" />}
                </span>
                <span className="result-name">
                  {r.name}
                  {r.character && <span className="result-character">{r.character.name}</span>}
                  {r.winner && <span className="sr-only"> (vítěz)</span>}
                  {r.starting && <span className="sr-only"> (začínal)</span>}
                </span>
                {scored && <span className="result-score">{r.score === null ? '' : formatScore(r.score)}</span>}
                {play.game.scoreType === 'rank_order' && <span className="result-score muted">{r.rank}.</span>}
              </li>
            ))}
          </ul>
          {canEdit && (
            <Actions inline>
              <Link className="btn small outline-secondary" to={`/plays/${play.id}/edit`}>Upravit</Link>
            </Actions>
          )}
        </div>
      )}
    </div>
  );
}
