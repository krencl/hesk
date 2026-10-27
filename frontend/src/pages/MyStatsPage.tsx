import { useQuery } from '@tanstack/react-query';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { qk } from '../api/queryKeys';
import { BarChart } from '../components/BarChart';
import { ErrorMessage } from '../components/ErrorMessage';
import { PageTitle } from '../components/PageTitle';
import { winsLabel } from '../lib/plural';

/** Moje statistiky: hry s nejvíc výhrami a výhry podle tagů her přihlášeného hráče v aktivní partě. */
export function MyStatsPage() {
  const party = useActiveParty();
  const { data, isPending, error } = useQuery({ queryKey: qk.myStats(party.id), queryFn: () => api.myStats(party.id) });

  return (
    <section className="page">
      <PageTitle label="Moje statistiky">{party.name}</PageTitle>
      <div className="pad">
        <ErrorMessage error={error} />
        {isPending && <p className="muted">Načítám…</p>}
        {data && data.wins === 0 && <p className="muted">Zatím tu nemáš žádnou výhru, je čas to napravit.</p>}
        {data && data.wins > 0 && (
          <>
            <p className="muted my-stats-summary">{data.player?.name}: {winsLabel(data.wins)} z {data.plays} {data.plays === 1 ? 'hry' : 'her'}</p>
            <h3>Nejvíc výher</h3>
            <BarChart items={data.topGames.map((g) => ({ key: g.gameId, label: g.shortName ?? g.name, value: g.wins, total: g.plays, showTotal: true, to: `/games/${g.gameId}` }))} />
            <h3>Výhry podle tagu hry</h3>
            <BarChart items={data.gameTags.map((t) => ({ key: t.name, label: t.name, value: t.wins }))} />
          </>
        )}
      </div>
    </section>
  );
}
