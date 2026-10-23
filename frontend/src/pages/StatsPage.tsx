import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { BarChart } from '../components/BarChart';
import { ErrorMessage } from '../components/ErrorMessage';
import { PageTitle } from '../components/PageTitle';
import { TagFilter, type TagFilterState } from '../components/TagFilter';

const namesWith = (filter: TagFilterState, state: 'include' | 'exclude') => Object.keys(filter).filter((t) => filter[t] === state).sort();

export function StatsPage() {
  const party = useActiveParty();
  const [filter, setFilter] = useState<TagFilterState>({});
  const tags = namesWith(filter, 'include');
  const excludeTags = namesWith(filter, 'exclude');
  const filtered = tags.length > 0 || excludeTags.length > 0;
  const { data, isPending, error } = useQuery({
    queryKey: ['stats', party.id, tags, excludeTags],
    queryFn: () => api.stats(party.id, { tags, excludeTags }),
    placeholderData: (previous) => previous,
  });
  const gameTags = useQuery({ queryKey: ['partyGameTags', party.id], queryFn: () => api.partyGameTags(party.id) });
  const nothingPlayed = data && data.topGames.length === 0 && !filtered;

  return (
    <section className="page">
      <PageTitle label="Statistiky">{party.name}</PageTitle>
      <div className="pad">
        <ErrorMessage error={error} />
        {isPending && <p className="muted">Načítám…</p>}
        {nothingPlayed && <p className="muted">Ještě jste nic neodehráli, je čas to napravit.</p>}
        {data && !nothingPlayed && (
          <>
            {gameTags.data && gameTags.data.length > 0 && <TagFilter tags={gameTags.data} value={filter} onChange={setFilter} />}
            {data.topGames.length === 0 ? (
              <p className="muted">Žádná odehraná hra neodpovídá filtru.</p>
            ) : (
              <>
                <h3>Výhry hráčů</h3>
                <BarChart items={data.wins.map((w) => ({ key: w.playerId, label: w.name, value: w.wins }))} />
                <h3>Nejhranější hry</h3>
                <BarChart items={data.topGames.map((g) => ({ key: g.gameId, label: g.shortName ?? g.name, value: g.plays }))} />
              </>
            )}
          </>
        )}
      </div>
    </section>
  );
}
