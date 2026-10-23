import { useQuery } from '@tanstack/react-query';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { BarChart } from '../components/BarChart';
import { ErrorMessage } from '../components/ErrorMessage';
import { PageTitle } from '../components/PageTitle';

export function StatsPage() {
  const party = useActiveParty();
  const { data, isPending, error } = useQuery({ queryKey: ['stats', party.id], queryFn: () => api.stats(party.id) });

  return (
    <section className="page">
      <PageTitle>Statistiky – {party.name}</PageTitle>
      <div className="pad">
        <ErrorMessage error={error} />
        {isPending && <p className="muted">Načítám…</p>}
        {data && data.topGames.length === 0 && <p className="muted">Ještě jste nic neodehráli, je čas to napravit.</p>}
        {data && data.topGames.length > 0 && (
          <>
            <h3>Výhry hráčů</h3>
            <BarChart items={data.wins.map((w) => ({ key: w.playerId, label: w.name, value: w.wins }))} />
            <h3>Nejhranější hry</h3>
            <BarChart items={data.topGames.map((g) => ({ key: g.gameId, label: g.shortName ?? g.name, value: g.plays }))} />
          </>
        )}
      </div>
    </section>
  );
}
