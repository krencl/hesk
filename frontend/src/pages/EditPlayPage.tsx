import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import type { PlayInput } from '../api/types';
import { ErrorMessage } from '../components/ErrorMessage';
import { PlayForm } from '../components/PlayForm';
import { EmptyState, LoadingOrError, isNotFound } from '../components/PageState';
import { draftFromPlay } from '../lib/playForm';
import { refreshAfterPlayChange } from '../lib/refresh';
import { useGame } from '../lib/useGame';
import { canWrite } from '../lib/roles';
import { qk } from '../api/queryKeys';

/** Úprava zápisu; smazání zápisu je až pod formulářem. */
export function EditPlayPage() {
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const id = Number(useParams().id);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const play = useQuery({ queryKey: qk.play(id), queryFn: () => api.play(id), staleTime: 0, gcTime: 0 });
  const gameId = play.data?.game.id;
  const game = useGame(gameId);

  const finish = async (leavingGameId: number) => {
    queryClient.removeQueries({ queryKey: qk.play(id) });
    await refreshAfterPlayChange(queryClient, party.id, leavingGameId, { leaving: true });
    navigate(`/games/${leavingGameId}`, { replace: true });
  };
  const save = useMutation({ mutationFn: (input: PlayInput) => api.updatePlay(id, input), onSuccess: (saved) => finish(saved.game.id) });
  const remove = useMutation({ mutationFn: () => api.deletePlay(id), onSuccess: () => finish(gameId!) });

  if (isNotFound(play.error)) return <EmptyState message="Tenhle zápis neexistuje nebo k němu nemáš přístup." backTo="/games" backLabel="Zpět na hry" />;
  if (!canWrite(party.role)) return <EmptyState message="Upravovat zápisy může jen zapisovač nebo správce." backTo="/games" backLabel="Zpět na hry" />;
  if (!play.data || !game.data) return <LoadingOrError error={play.error ?? game.error} rows={3} withImage={false} />;

  return (
    <PlayForm
      game={game.data}
      initial={draftFromPlay(play.data)}
      heading="Upravit zápis"
      submitLabel="Uložit změny"
      saving={save.isPending || remove.isPending}
      error={save.error}
      cancelTo={`/games/${play.data.game.id}`}
      onSubmit={(input) => save.mutate(input)}
    >
      <ErrorMessage error={remove.error} />
      <p className="subtle-action">
        <button type="button" className={`link subtle${confirmDelete ? ' confirm' : ''}`} disabled={remove.isPending} onClick={() => (confirmDelete ? remove.mutate() : setConfirmDelete(true))}>
          {confirmDelete ? 'Potvrď smazání' : 'Smazat zápis'}
        </button>
      </p>
    </PlayForm>
  );
}
