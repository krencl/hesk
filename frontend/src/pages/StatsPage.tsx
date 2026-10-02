import { useQuery } from '@tanstack/react-query';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { BarChart } from '../components/BarChart';
import { ErrorMessage } from '../components/ErrorMessage';

export function StatsPage() {
  const party = useActiveParty();
  const { data, isPending, error } = useQuery({ queryKey: ['stats', party.id], queryFn: () => api.stats(party.id) });

  return (
    <section>
      <h2>Statistiky – {party.name}</h2>
      <ErrorMessage error={error} />
      {isPending && <p className="muted">Načítám…</p>}
      {data && (
        <>
          <h3>Výhry hráčů</h3>
          <BarChart items={data.wins.map((w) => ({ key: w.userId, label: w.nickname ?? '?', value: w.wins }))} />
          <h3>Nejhranější hry</h3>
          <BarChart items={data.topGames.map((g) => ({ key: g.gameId, label: g.name, value: g.plays }))} />
        </>
      )}
    </section>
  );
}
