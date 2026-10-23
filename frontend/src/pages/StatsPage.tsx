import { keepPreviousData, useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { useApp } from '../AppContext';
import { api } from '../api';
import type { Party } from '../api/types';
import { BarChart } from '../components/BarChart';
import { ErrorMessage } from '../components/ErrorMessage';
import { PageTitle } from '../components/PageTitle';
import { StatsFilter } from '../components/StatsFilter';
import { type FilterItem, parseStatsSearch, statsSearch, toStatsQuery } from '../lib/statsFilter';

/** Statistiky party z adresy (`/parties/:id/stats`); filtr je v parametrech adresy, takže jde sdílet odkazem. */
export function StatsPage() {
  const { id } = useParams();
  const { parties } = useApp();
  const party = parties.find((p) => p.id === Number(id));
  if (!party) {
    return (
      <section className="page pad empty">
        <p>Tuhle partu nevidíš, nebo neexistuje.</p>
        <Link className="btn" to="/parties">Spravovat party</Link>
      </section>
    );
  }
  return <PartyStats party={party} />;
}

function PartyStats({ party }: { party: Party }) {
  const queryClient = useQueryClient();
  const [params, setParams] = useSearchParams();
  const [query, setQuery] = useState('');
  const [searchOpen, setSearchOpen] = useState(false);

  // V adrese jsou jen id her; názvy štítků se dohledají (a při výběru hry se rovnou uloží do cache).
  const gameIds = [...new Set([...params.getAll('games'), ...params.getAll('excludeGames')].map(Number).filter((n) => Number.isInteger(n) && n > 0))];
  const names = useQueries({
    queries: gameIds.map((gameId) => ({
      queryKey: ['statsGameName', party.id, gameId],
      queryFn: async () => (await api.game(party.id, gameId)).name,
      staleTime: Infinity,
      retry: false,
    })),
  });
  const nameById = new Map(gameIds.map((gameId, i) => [gameId, names[i]?.data]));
  const items = parseStatsSearch(params, (gameId) => nameById.get(gameId) ?? `Hra #${gameId}`);
  const setItems = (next: FilterItem[]) => {
    for (const item of next) if (item.kind === 'game') queryClient.setQueryData(['statsGameName', party.id, item.id], item.name);
    setParams(statsSearch(next), { replace: true });
  };

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
      {data && !nothingPlayed && <StatsFilter partyId={party.id} items={items} onChange={setItems} query={query} onQueryChange={setQuery} open={searchOpen} onOpenChange={setSearchOpen} />}
      <div className="pad">
        <ErrorMessage error={error} />
        {isPending && <p className="muted">Načítám…</p>}
        {nothingPlayed && <p className="muted">Ještě jste nic neodehráli, je čas to napravit.</p>}
        {data && !nothingPlayed && query.trim() === '' && !searchOpen && (
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
