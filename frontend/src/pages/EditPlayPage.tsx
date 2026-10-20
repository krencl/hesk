import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ApiError } from '../api/client';
import type { PlayInput } from '../api/types';
import { ErrorMessage } from '../components/ErrorMessage';
import { PlayForm } from '../components/PlayForm';
import { ListSkeleton } from '../components/Skeleton';
import { draftFromPlay } from '../lib/playForm';
import { refreshAfterPlayChange } from '../lib/refresh';

/** Úprava zápisu; smazání zápisu je až pod formulářem. */
export function EditPlayPage() {
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const id = Number(useParams().id);
  const [confirmDelete, setConfirmDelete] = useState(false);
  const play = useQuery({ queryKey: ['play', id], queryFn: () => api.play(id), retry: false, staleTime: 0, gcTime: 0 });
  const gameId = play.data?.game.id;
  const game = useQuery({ queryKey: ['game', gameId], queryFn: () => api.game(gameId!), enabled: gameId !== undefined, retry: false });

  const finish = async (leavingGameId: number) => {
    queryClient.removeQueries({ queryKey: ['play', id] });
    await refreshAfterPlayChange(queryClient, party.id, leavingGameId, { leaving: true });
    navigate(`/games/${leavingGameId}`, { replace: true });
  };
  const save = useMutation({ mutationFn: (input: PlayInput) => api.updatePlay(id, input), onSuccess: (saved) => finish(saved.game.id) });
  const remove = useMutation({ mutationFn: () => api.deletePlay(id), onSuccess: () => finish(gameId!) });

  if (play.error instanceof ApiError && (play.error.status === 404 || play.error.status === 403)) {
    return <p className="page pad empty">Tenhle zápis neexistuje nebo k němu nemáš přístup.<Link className="btn" to="/games">Zpět na hry</Link></p>;
  }
  if (!play.data || !game.data) return play.error || game.error ? <section className="page pad"><ErrorMessage inline error={play.error ?? game.error} /></section> : <ListSkeleton rows={3} withImage={false} />;

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
