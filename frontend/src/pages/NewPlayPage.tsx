import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import type { PlayInput } from '../api/types';
import { EmptyState, isNotFound } from '../components/PageState';
import { PlayForm } from '../components/PlayForm';
import { ListSkeleton } from '../components/Skeleton';
import { fromLocalInput } from '../lib/datetime';
import { defaultPlayerIds, defaultTimes, emptyDraft } from '../lib/playForm';
import { refreshAfterPlayChange } from '../lib/refresh';
import { useGame } from '../lib/useGame';
import { canWrite } from '../lib/roles';
import { qk } from '../api/queryKeys';

/** Nový zápis dané hry (hra se nevybírá, formulář se otevírá z jejího detailu). */
export function NewPlayPage() {
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const gameId = Number(useParams().id);
  const game = useGame(gameId);

  // Výchozí hodnoty závisejí na čase zápisu: sezení (a jeho číslo) a hráči posledního zápisu stejného sezení.
  const [now] = useState(() => new Date());
  const times = defaultTimes(now);
  const startedIso = fromLocalInput(times.startedAt);
  const endedIso = fromLocalInput(times.endedAt);
  const players = useQuery({ queryKey: qk.players(party.id), queryFn: () => api.players(party.id) });
  const defaults = useQuery({
    queryKey: qk.playDefaults(party.id, startedIso, endedIso),
    queryFn: () => api.playDefaults(party.id, startedIso, endedIso),
    staleTime: 60_000,
    gcTime: 0,
    enabled: canWrite(party.role),
  });

  const save = useMutation({
    mutationFn: (input: PlayInput) => api.createPlay(party.id, input),
    onSuccess: async (saved) => {
      await refreshAfterPlayChange(queryClient, party.id, saved.game.id, { leaving: true });
      navigate(`/games/${saved.game.id}`);
    },
  });

  if (isNotFound(game.error)) return <EmptyState message="Tahle hra neexistuje nebo k ní nemáš přístup." backTo="/games" backLabel="Zpět na hry" />;
  if (!canWrite(party.role)) return <EmptyState message="Zapisovat hry může jen zapisovač nebo správce." backTo={`/games/${gameId}`} backLabel="Zpět" />;
  if (!game.data || !players.data || defaults.isPending) return <ListSkeleton rows={3} withImage={false} />;

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
