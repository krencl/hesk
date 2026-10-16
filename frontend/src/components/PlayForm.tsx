import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, type ReactNode, useState } from 'react';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import type { Game, PlayInput } from '../api/types';
import { type PlayDraft, toPlayInput, validateDraft } from '../lib/playForm';
import { ErrorMessage } from './ErrorMessage';
import { GameHeader } from './GameHeader';
import { ListSkeleton } from './Skeleton';

interface Props {
  game: Game;
  /** Výchozí hodnoty (čtou se jen při prvním vykreslení). */
  initial: PlayDraft;
  heading: string;
  submitLabel: string;
  saving: boolean;
  /** Chyba z ukládání (zobrazí se pod formulářem). */
  error: unknown;
  cancelTo: string;
  onSubmit: (input: PlayInput) => void;
  /** Doplňkové prvky pod tlačítky (např. smazání zápisu). */
  children?: ReactNode;
}

/** Formulář zápisu hry – sdílený mezi založením a úpravou zápisu. */
export function PlayForm({ game, initial, heading, submitLabel, saving, error, cancelTo, onSubmit, children }: Props) {
  const party = useActiveParty();
  const queryClient = useQueryClient();
  const players = useQuery({ queryKey: ['players', party.id], queryFn: () => api.players(party.id) });
  const [draft, setDraft] = useState<PlayDraft>(initial);
  const [formError, setFormError] = useState<string | null>(null);
  const [guestName, setGuestName] = useState('');

  const patch = (changes: Partial<PlayDraft>) => setDraft((d) => ({ ...d, ...changes }));
  const togglePlayer = (playerId: number) =>
    setDraft((d) => ({
      ...d,
      players: d.players.some((p) => p.playerId === playerId)
        ? d.players.filter((p) => p.playerId !== playerId)
        : [...d.players, { playerId, score: '', rank: '', winner: false }],
    }));
  const patchPlayer = (playerId: number, changes: Partial<PlayDraft['players'][number]>) =>
    patch({ players: draft.players.map((p) => (p.playerId === playerId ? { ...p, ...changes } : p)) });

  const addGuest = useMutation({
    mutationFn: () => api.createGuest(party.id, guestName.trim()),
    onSuccess: async (p) => {
      setGuestName('');
      await queryClient.invalidateQueries({ queryKey: ['players', party.id] });
      togglePlayer(p.id);
    },
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const problem = validateDraft(draft, game);
    setFormError(problem);
    if (!problem) onSubmit(toPlayInput(draft, game));
  };

  if (players.isPending) return <ListSkeleton rows={3} withImage={false} />;

  return (
    <form className="form" onSubmit={submit}>
      <GameHeader name={game.name} imageUrl={game.imageUrl} />
      <h2>{heading}</h2>

      {game.addons.length > 0 && (
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
        {players.data?.map((m) => {
          const player = draft.players.find((p) => p.playerId === m.id);
          return (
            <div key={m.id} className={`player-row${player ? ' selected' : ''}`}>
              <label className="check">
                <input type="checkbox" checked={!!player} onChange={() => togglePlayer(m.id)} />
                {m.name}
              </label>
              {player && (game.scoreType === 'most_points' || game.scoreType === 'fewest_points') && (
                <input inputMode="decimal" aria-label={`Skóre ${m.name}`} placeholder="skóre" value={player.score} onChange={(e) => patchPlayer(m.id, { score: e.target.value })} />
              )}
              {player && game.scoreType === 'rank_order' && (
                <input type="number" min={1} aria-label={`Pořadí ${m.name}`} placeholder="pořadí" value={player.rank} onChange={(e) => patchPlayer(m.id, { rank: e.target.value })} />
              )}
              {player && game.scoreType === 'winner_only' && (
                <label className="check">
                  <input type="checkbox" checked={player.winner} onChange={(e) => patchPlayer(m.id, { winner: e.target.checked })} /> vítěz
                </label>
              )}
            </div>
          );
        })}
        <div className="player-row">
          <input value={guestName} maxLength={100} placeholder="Nový hráč (host)" aria-label="Jméno nového hráče" onChange={(e) => setGuestName(e.target.value)} />
          <button type="button" className="btn secondary" disabled={!guestName.trim() || addGuest.isPending} onClick={() => addGuest.mutate()}>Přidat</button>
        </div>
        <ErrorMessage error={addGuest.error} />
      </fieldset>

      <div className="row stack">
        <label>Začátek<input type="datetime-local" value={draft.startedAt} onChange={(e) => patch({ startedAt: e.target.value })} required /></label>
        <label>Konec<input type="datetime-local" value={draft.endedAt} onChange={(e) => patch({ endedAt: e.target.value })} required /></label>
      </div>
      <label>Místo<input value={draft.place} maxLength={100} onChange={(e) => patch({ place: e.target.value })} /></label>
      <label>Tagy (oddělené čárkou)<input value={draft.tags} onChange={(e) => patch({ tags: e.target.value })} /></label>
      <label>Poznámka<textarea value={draft.notes} rows={3} onChange={(e) => patch({ notes: e.target.value })} /></label>

      {formError && <div className="error" role="alert">{formError}</div>}
      <ErrorMessage error={error} />
      <div className="nav-actions">
        <button className="btn" type="submit" disabled={saving}>{submitLabel}</button>
        <Link className="btn outline-secondary" to={cancelTo}>Zrušit</Link>
      </div>
      {children}
    </form>
  );
}
