import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ApiError } from '../api/client';
import { Actions } from '../components/Actions';
import { PlayForm } from '../components/PlayForm';
import { ListSkeleton } from '../components/Skeleton';
import { fromLocalInput } from '../lib/datetime';
import { defaultPlayerIds, defaultTimes, emptyDraft } from '../lib/playForm';
import { refreshAfterPlayChange } from '../lib/refresh';
import { canWrite } from '../lib/roles';

/** Nový zápis dané hry (hra se nevybírá, formulář se otevírá z jejího detailu). */
export function NewPlayPage() {
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const gameId = Number(useParams().id);
  const game = useQuery({ queryKey: ['game', gameId], queryFn: () => api.game(gameId), retry: false });

  // Výchozí hodnoty závisejí na čase zápisu: sezení (a jeho číslo) a hráči posledního zápisu stejného sezení.
  const [now] = useState(() => new Date());
  const times = defaultTimes(now);
  const startedIso = fromLocalInput(times.startedAt);
  const endedIso = fromLocalInput(times.endedAt);
  const players = useQuery({ queryKey: ['players', party.id], queryFn: () => api.players(party.id) });
  const defaults = useQuery({
    queryKey: ['playDefaults', party.id, startedIso, endedIso],
    queryFn: () => api.playDefaults(party.id, startedIso, endedIso),
    staleTime: 60_000,
    gcTime: 0,
    enabled: canWrite(party.role),
  });

  const save = useMutation({
    mutationFn: (input: Parameters<typeof api.createPlay>[1]) => api.createPlay(party.id, input),
    onSuccess: async (saved) => {
      await refreshAfterPlayChange(queryClient, party.id, saved.game.id, { leaving: true });
      navigate(`/games/${saved.game.id}`);
    },
  });

  if (game.error instanceof ApiError && (game.error.status === 404 || game.error.status === 403)) {
    return <p className="page pad empty">Tahle hra neexistuje nebo k ní nemáš přístup.<Link className="btn" to="/games">Zpět na hry</Link></p>;
  }
  if (!canWrite(party.role)) {
    return (
      <section className="page pad">
        <p className="empty">Zapisovat hry může jen zapisovač nebo admin.</p>
        <Actions><Link className="btn outline-secondary" to={`/games/${gameId}`}>Zpět</Link></Actions>
      </section>
    );
  }
  if (!game.data || !players.data || (defaults.isPending && !defaults.isError)) return <ListSkeleton rows={3} withImage={false} />;

  return (
    <PlayForm
      game={game.data}
      initial={emptyDraft(game.data, now, { playerIds: defaultPlayerIds(defaults.data, players.data), sessionNumber: defaults.data?.sessionNumber, tags: defaults.data?.tags })}
      suggestSessionNumber
      heading="Nový zápis"
      submitLabel="Uložit zápis"
      saving={save.isPending}
      error={save.error}
      cancelTo={`/games/${gameId}`}
      onSubmit={(input) => save.mutate(input)}
    />
  );
}
