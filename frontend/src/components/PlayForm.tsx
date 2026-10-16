import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import type { Game, PlayInput } from '../api/types';
import { fromLocalInput } from '../lib/datetime';
import { isScored, isWinner, newPlayerDraft, type PlayDraft, toPlayInput, validateDraft } from '../lib/playForm';
import { Actions } from './Actions';
import { Autocomplete } from './Autocomplete';
import { ErrorMessage } from './ErrorMessage';
import { GameHeader } from './GameHeader';
import { Icon } from './Icon';
import { ListSkeleton } from './Skeleton';
import { TagInput } from './TagInput';

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
  /** U nového zápisu se číslo sezení předvyplňuje podle času zápisu, dokud ho uživatel sám nezmění. */
  suggestSessionNumber?: boolean;
  onSubmit: (input: PlayInput) => void;
  /** Doplňkové prvky pod tlačítky (např. smazání zápisu). */
  children?: ReactNode;
}

/** Hodnota z <input type="datetime-local"> jako ISO, nebo `null`, když není (ještě) platná. */
function validIso(local: string): string | null {
  return local && !Number.isNaN(new Date(local).getTime()) ? fromLocalInput(local) : null;
}

/** Formulář zápisu hry – sdílený mezi založením a úpravou zápisu. */
export function PlayForm({ game, initial, heading, submitLabel, saving, error, cancelTo, suggestSessionNumber = false, onSubmit, children }: Props) {
  const party = useActiveParty();
  const queryClient = useQueryClient();
  const players = useQuery({ queryKey: ['players', party.id], queryFn: () => api.players(party.id) });
  const places = useQuery({ queryKey: ['places', party.id], queryFn: () => api.places(party.id) });
  const tags = useQuery({ queryKey: ['tags', party.id], queryFn: () => api.tags(party.id) });
  const [draft, setDraft] = useState<PlayDraft>(initial);

  // Číslo sezení závisí na čase zápisu (do 24 h od posledního zápisu zůstává, jinak o jedno víc); stejný dotaz už načetla stránka.
  const startedIso = validIso(draft.startedAt);
  const endedIso = validIso(draft.endedAt);
  const suggestion = useQuery({
    queryKey: ['playDefaults', party.id, startedIso, endedIso],
    queryFn: () => api.playDefaults(party.id, startedIso!, endedIso!),
    enabled: suggestSessionNumber && !!startedIso && !!endedIso,
    staleTime: 60_000,
    gcTime: 0,
  });
  const suggested = suggestion.data?.sessionNumber;
  useEffect(() => {
    if (suggested !== undefined) setDraft((d) => (d.sessionNumberTouched || d.sessionNumber === String(suggested) ? d : { ...d, sessionNumber: String(suggested) }));
  }, [suggested]);
  const [formError, setFormError] = useState<string | null>(null);
  const [showGuests, setShowGuests] = useState(false);
  const [guestName, setGuestName] = useState('');

  const scored = isScored(game.scoreType);
  const patch = (changes: Partial<PlayDraft>) => setDraft((d) => ({ ...d, ...changes }));
  const togglePlayer = (playerId: number) =>
    setDraft((d) => ({
      ...d,
      players: d.players.some((p) => p.playerId === playerId)
        ? d.players.filter((p) => p.playerId !== playerId)
        : [...d.players, newPlayerDraft(playerId)],
    }));
  const patchPlayer = (playerId: number, changes: Partial<PlayDraft['players'][number]>) =>
    patch({ players: draft.players.map((p) => (p.playerId === playerId ? { ...p, ...changes } : p)) });

  // U her se skóre se nejdřív ukazuje vítěz podle skóre; první ruční zásah převezme volbu do ruky uživatele.
  const toggleWinner = (playerId: number) => {
    if (!scored) return patchPlayer(playerId, { winner: !draft.players.find((p) => p.playerId === playerId)?.winner });
    patch({
      winnersTouched: true,
      players: draft.players.map((p) => ({ ...p, winner: p.playerId === playerId ? !isWinner(draft, game, p.playerId) : isWinner(draft, game, p.playerId) })),
    });
  };
  const toggleStarting = (playerId: number) =>
    patch({ players: draft.players.map((p) => ({ ...p, starting: p.playerId === playerId ? !p.starting : false })) });

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

  // Hlavní hráči (členové s účtem) jsou vidět vždy, hosté až po „Přidat hosty“ (vybraní hosté ale vždy).
  const isSelected = (id: number) => draft.players.some((p) => p.playerId === id);
  // Vlastní hráč hry (kooperativní hry) je vidět vždy a první.
  const gamePlayerId = game.player?.id;
  const rank = (m: { id: number; hasAccount: boolean }) => (m.id === gamePlayerId ? 0 : m.hasAccount ? 1 : 2);
  const visible = (players.data ?? [])
    .filter((m) => m.id === gamePlayerId || m.hasAccount || isSelected(m.id) || showGuests)
    .sort((a, b) => rank(a) - rank(b));

  return (
    <section className="page">
      <GameHeader name={game.name} imageUrl={game.imageUrl} />
      <form className="pad form" onSubmit={submit}>
        <h2>{heading}</h2>

        {game.addons.length > 0 && (
          <div className="field">
            <span className="field-label">Rozšíření</span>
            {game.addons.map((a) => (
              <label key={a.id} className="check">
                <input type="checkbox" checked={draft.addonIds.includes(a.id)} onChange={(e) => patch({ addonIds: e.target.checked ? [...draft.addonIds, a.id] : draft.addonIds.filter((id) => id !== a.id) })} />
                {a.name}
              </label>
            ))}
          </div>
        )}

        <div className="field">
          <span className="field-label">Hráči</span>
          {visible.map((m) => {
            const player = draft.players.find((p) => p.playerId === m.id);
            return (
              <div key={m.id} className={`player${player ? ' selected' : ''}`}>
                <label className="check">
                  <input type="checkbox" checked={!!player} onChange={() => togglePlayer(m.id)} />
                  {m.name}
                  {m.id === gamePlayerId && <span className="muted hint"> hráč hry</span>}
                </label>
                {player && scored && (
                  <input inputMode="decimal" aria-label={`Skóre ${m.name}`} placeholder="skóre" value={player.score} onChange={(e) => patchPlayer(m.id, { score: e.target.value })} />
                )}
                {player && game.scoreType === 'rank_order' && (
                  <input type="number" min={1} aria-label={`Pořadí ${m.name}`} placeholder="pořadí" value={player.rank} onChange={(e) => patchPlayer(m.id, { rank: e.target.value })} />
                )}
                {player && game.characters.length > 0 && (
                  <select className="character" aria-label={`Postava ${m.name}`} value={player.characterId ?? ''} onChange={(e) => patchPlayer(m.id, { characterId: e.target.value ? Number(e.target.value) : null })}>
                    <option value="">— postava —</option>
                    {game.characters.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                )}
                {player && (
                  <div className="chips">
                    {game.scoreType !== 'rank_order' && (
                      <button type="button" className="chip" aria-pressed={isWinner(draft, game, m.id)} aria-label={`Vítěz ${m.name}`} onClick={() => toggleWinner(m.id)}>
                        <Icon name="workspace_premium" /> Vítěz
                      </button>
                    )}
                    <button type="button" className="chip" aria-pressed={player.starting} aria-label={`Začíná ${m.name}`} onClick={() => toggleStarting(m.id)}>
                      <Icon name="looks_one" /> Začíná
                    </button>
                  </div>
                )}
              </div>
            );
          })}

          {showGuests && (
            <div className="guest-add">
              <input value={guestName} maxLength={100} placeholder="Nový host" aria-label="Jméno nového hosta" onChange={(e) => setGuestName(e.target.value)} />
              <button type="button" className="btn outline" disabled={!guestName.trim() || addGuest.isPending} onClick={() => addGuest.mutate()}>Přidat</button>
            </div>
          )}
          <ErrorMessage error={addGuest.error} />
          <div className="field-actions">
            <button type="button" className="btn small outline-secondary" aria-expanded={showGuests} onClick={() => setShowGuests(!showGuests)}>
              {showGuests ? 'Skrýt hosty' : 'Přidat hosty'}
            </button>
            {scored && draft.winnersTouched && (
              <button type="button" className="link subtle" onClick={() => patch({ winnersTouched: false })}>Vítěze určit podle skóre</button>
            )}
          </div>
        </div>

        <div className="row stack">
          <label>Začátek<input type="datetime-local" value={draft.startedAt} onChange={(e) => patch({ startedAt: e.target.value })} required /></label>
          <label>Konec<input type="datetime-local" value={draft.endedAt} onChange={(e) => patch({ endedAt: e.target.value })} required /></label>
        </div>
        <label>Číslo sezení<input type="number" inputMode="numeric" min={1} step={1} value={draft.sessionNumber} onChange={(e) => patch({ sessionNumber: e.target.value, sessionNumberTouched: true })} /></label>
        <div className="field">
          <label htmlFor="play-place" className="field-label">Místo</label>
          <Autocomplete id="play-place" value={draft.place} maxLength={100} options={places.data ?? []} onChange={(place) => patch({ place })} />
        </div>
        <div className="field">
          <label htmlFor="play-tags" className="field-label">Tagy</label>
          <TagInput id="play-tags" tags={draft.tags} options={tags.data ?? []} onChange={(t) => patch({ tags: t })} />
        </div>
        <label>Poznámka<textarea value={draft.notes} rows={3} onChange={(e) => patch({ notes: e.target.value })} /></label>

        {formError && <div className="error" role="alert">{formError}</div>}
        <ErrorMessage error={error} />
        <Actions>
          <button className="btn" type="submit" disabled={saving}>{submitLabel}</button>
          <Link className="btn outline-secondary" to={cancelTo}>Zrušit</Link>
        </Actions>
        {children}
      </form>
    </section>
  );
}
