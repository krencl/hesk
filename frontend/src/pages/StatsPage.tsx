import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { BarChart } from '../components/BarChart';
import { ErrorMessage } from '../components/ErrorMessage';
import { PageTitle } from '../components/PageTitle';
import { StatsFilter } from '../components/StatsFilter';
import { type FilterItem, toStatsQuery } from '../lib/statsFilter';

export function StatsPage() {
  const party = useActiveParty();
  const [items, setItems] = useState<FilterItem[]>([]);
  const [query, setQuery] = useState('');
  const filter = toStatsQuery(items);
  const { data, isPending, error } = useQuery({
    queryKey: ['stats', party.id, filter],
    queryFn: () => api.stats(party.id, filter),
    placeholderData: keepPreviousData,
  });
  const nothingPlayed = data && data.topGames.length === 0 && items.length === 0;

  return (
    <section className="page">
      <PageTitle label="Statistiky">{party.name}</PageTitle>
      {data && !nothingPlayed && <StatsFilter partyId={party.id} items={items} onChange={setItems} query={query} onQueryChange={setQuery} />}
      <div className="pad">
        <ErrorMessage error={error} />
        {isPending && <p className="muted">Načítám…</p>}
        {nothingPlayed && <p className="muted">Ještě jste nic neodehráli, je čas to napravit.</p>}
        {data && !nothingPlayed && query.trim() === '' && (
          data.topGames.length === 0 ? (
            <p className="muted">Žádná odehraná hra neodpovídá filtru.</p>
          ) : (
            <>
              <h3>Výhry hráčů</h3>
              <BarChart items={data.wins.map((w) => ({ key: w.playerId, label: w.name, value: w.wins }))} />
              <h3>Nejhranější hry</h3>
              <BarChart items={data.topGames.map((g) => ({ key: g.gameId, label: g.shortName ?? g.name, value: g.plays }))} />
            </>
          )
        )}
      </div>
    </section>
  );
}
