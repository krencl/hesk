import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import { ErrorMessage } from '../components/ErrorMessage';
import { toLocalInput } from '../lib/datetime';
import { type PlayDraft, toPlayInput, validateDraft } from '../lib/playForm';

export function NewPlayPage() {
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const games = useQuery({ queryKey: ['games', party.id], queryFn: () => api.games(party.id) });
  const detail = useQuery({ queryKey: ['party', party.id], queryFn: () => api.party(party.id) });

  const [draft, setDraft] = useState<PlayDraft>(() => {
    const end = new Date();
    return {
      gameId: null,
      startedAt: toLocalInput(new Date(end.getTime() - 60 * 60 * 1000)),
      endedAt: toLocalInput(end),
      players: [],
      addonIds: [],
      place: '',
      tags: '',
      notes: '',
    };
  });
  const [formError, setFormError] = useState<string | null>(null);
  const game = games.data?.find((g) => g.id === draft.gameId);

  const save = useMutation({
    mutationFn: () => api.createPlay(party.id, toPlayInput(draft, game!)),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['plays', party.id] });
      await queryClient.invalidateQueries({ queryKey: ['stats', party.id] });
      navigate('/');
    },
  });

  const patch = (changes: Partial<PlayDraft>) => setDraft((d) => ({ ...d, ...changes }));
  const togglePlayer = (userId: number) =>
    patch({
      players: draft.players.some((p) => p.userId === userId)
        ? draft.players.filter((p) => p.userId !== userId)
        : [...draft.players, { userId, score: '', rank: '', winner: false }],
    });
  const patchPlayer = (userId: number, changes: Partial<PlayDraft['players'][number]>) =>
    patch({ players: draft.players.map((p) => (p.userId === userId ? { ...p, ...changes } : p)) });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const problem = validateDraft(draft, game);
    setFormError(problem);
    if (!problem) save.mutate();
  };

  if (games.isPending || detail.isPending) return <p className="muted">Načítám…</p>;
  if (games.data?.length === 0) return <p className="empty">Nejdřív přidej nějakou hru v menu → Hry.</p>;

  return (
    <form className="form" onSubmit={submit}>
      <h2>Nový zápis</h2>

      <label>
        Hra
        <select value={draft.gameId ?? ''} onChange={(e) => patch({ gameId: e.target.value ? Number(e.target.value) : null, addonIds: [] })} required>
          <option value="">— vyber —</option>
          {games.data?.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
        </select>
      </label>

      {game && game.addons.length > 0 && (
        <fieldset>
          <legend>Přídavky</legend>
          {game.addons.map((a) => (
            <label key={a.id} className="check">
              <input type="checkbox" checked={draft.addonIds.includes(a.id)} onChange={(e) => patch({ addonIds: e.target.checked ? [...draft.addonIds, a.id] : draft.addonIds.filter((id) => id !== a.id) })} />
              {a.name}
            </label>
          ))}
        </fieldset>
      )}

      <fieldset>
        <legend>Hráči</legend>
        {detail.data?.members.map((m) => {
          const player = draft.players.find((p) => p.userId === m.userId);
          return (
            <div key={m.userId} className="player-row">
              <label className="check">
                <input type="checkbox" checked={!!player} onChange={() => togglePlayer(m.userId)} />
                {m.nickname}
              </label>
              {player && game && (game.scoreType === 'most_points' || game.scoreType === 'fewest_points') && (
                <input inputMode="decimal" aria-label={`Skóre ${m.nickname}`} placeholder="skóre" value={player.score} onChange={(e) => patchPlayer(m.userId, { score: e.target.value })} />
              )}
              {player && game?.scoreType === 'rank_order' && (
                <input type="number" min={1} aria-label={`Pořadí ${m.nickname}`} placeholder="pořadí" value={player.rank} onChange={(e) => patchPlayer(m.userId, { rank: e.target.value })} />
              )}
              {player && game?.scoreType === 'winner_only' && (
                <label className="check">
                  <input type="checkbox" checked={player.winner} onChange={(e) => patchPlayer(m.userId, { winner: e.target.checked })} /> vítěz
                </label>
              )}
            </div>
          );
        })}
      </fieldset>

      <div className="row">
        <label>Začátek<input type="datetime-local" value={draft.startedAt} onChange={(e) => patch({ startedAt: e.target.value })} required /></label>
        <label>Konec<input type="datetime-local" value={draft.endedAt} onChange={(e) => patch({ endedAt: e.target.value })} required /></label>
      </div>
      <label>Místo<input value={draft.place} maxLength={100} onChange={(e) => patch({ place: e.target.value })} /></label>
      <label>Tagy (oddělené čárkou)<input value={draft.tags} onChange={(e) => patch({ tags: e.target.value })} /></label>
      <label>Poznámka<textarea value={draft.notes} rows={3} onChange={(e) => patch({ notes: e.target.value })} /></label>

      {formError && <div className="error" role="alert">{formError}</div>}
      <ErrorMessage error={save.error} />
      <button className="btn" type="submit" disabled={save.isPending}>Uložit zápis</button>
    </form>
  );
}
