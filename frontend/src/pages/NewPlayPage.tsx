import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ApiError } from '../api/client';
import { PlayForm } from '../components/PlayForm';
import { ListSkeleton } from '../components/Skeleton';
import { emptyDraft } from '../lib/playForm';
import { refreshAfterPlayChange } from '../lib/refresh';

/** Nový zápis dané hry (hra se nevybírá, formulář se otevírá z jejího detailu). */
export function NewPlayPage() {
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const gameId = Number(useParams().id);
  const game = useQuery({ queryKey: ['game', gameId], queryFn: () => api.game(gameId), retry: false });

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
  if (!game.data) return <ListSkeleton rows={3} withImage={false} />;

  return (
    <PlayForm
      game={game.data}
      initial={emptyDraft(game.data)}
      heading="Nový zápis"
      submitLabel="Uložit zápis"
      saving={save.isPending}
      error={save.error}
      cancelTo={`/games/${gameId}`}
      onSubmit={(input) => save.mutate(input)}
    />
  );
}
