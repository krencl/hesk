import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import type { Game, PlayInput } from '../api/types';
import { fromLocalInput } from '../lib/datetime';
import { applySessionLastPlay, endAfterStart, isScored, parseSessionNumber, isWinner, newPlayerDraft, type PlayDraft, toPlayInput, validateDraft } from '../lib/playForm';
import { Actions } from './Actions';
import { ErrorMessage } from './ErrorMessage';
import { GameHeader } from './GameHeader';
import { PlayerRow } from './PlayerRow';
import { ListSkeleton } from './Skeleton';
import { TagInput } from './TagInput';
import { useToasts } from './Toasts';
import { qk } from '../api/queryKeys';

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
  const players = useQuery({ queryKey: qk.players(party.id), queryFn: () => api.players(party.id) });
  const tags = useQuery({ queryKey: qk.tags(party.id), queryFn: () => api.tags(party.id) });
  const [draft, setDraft] = useState<PlayDraft>(initial);

  // Číslo sezení závisí na čase zápisu (do 24 h od posledního zápisu zůstává, jinak o jedno víc); stejný dotaz už načetla stránka.
  const startedIso = validIso(draft.startedAt);
  const endedIso = validIso(draft.endedAt);
  const suggestion = useQuery({
    queryKey: qk.playDefaults(party.id, startedIso, endedIso),
    queryFn: () => api.playDefaults(party.id, startedIso!, endedIso!),
    enabled: suggestSessionNumber && !!startedIso && !!endedIso,
    staleTime: 60_000,
    gcTime: 0,
    placeholderData: keepPreviousData, // při změně času nebliká původní číslo, než přijde nový návrh
  });
  const suggested = suggestion.data?.sessionNumber;
  // Dokud číslo sezení uživatel nezměnil, ukazuje se návrh podle času zápisu (do draftu se nekopíruje; posílá se jen ručně zadané).
  const shownSession = draft.sessionNumberTouched ? draft.sessionNumber : suggested !== undefined ? String(suggested) : draft.sessionNumber;
  const { showError } = useToasts();

  // Ručně zadané číslo existujícího sezení (nový zápis, dopisování zpětně): čas a hráči podle jeho posledního zápisu.
  // Čeká se na dopsání čísla, aby se nenačítala mezičísla (např. „1“ cestou k „12“).
  const typedSession = suggestSessionNumber && draft.sessionNumberTouched ? parseSessionNumber(draft.sessionNumber) : null;
  useEffect(() => {
    if (typedSession === null) return;
    let cancelled = false;
    const timer = setTimeout(() => {
      queryClient
        .fetchQuery({ queryKey: qk.sessionLastPlay(party.id, typedSession), queryFn: () => api.sessionLastPlay(party.id, typedSession), staleTime: 0 })
        .then((last) => {
          if (!cancelled && last) setDraft((d) => applySessionLastPlay(d, game, last));
        })
        .catch(() => undefined); // bez předvyplnění se dá zapisovat dál
    }, 500);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [typedSession, party.id, queryClient, game]);
  const [showGuests, setShowGuests] = useState(false);
  const [guestName, setGuestName] = useState('');

  const scored = isScored(game.scoreType);
  const patch = (changes: Partial<PlayDraft>) => setDraft((d) => ({ ...d, ...changes }));
  const changeStart = (startedAt: string) => setDraft((d) => ({ ...d, startedAt, endedAt: endAfterStart(startedAt, d.endedAt) }));
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
      await queryClient.invalidateQueries({ queryKey: qk.players(party.id) });
      togglePlayer(p.id);
    },
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const problem = validateDraft(draft, game);
    if (problem) showError(problem);
    else onSubmit(toPlayInput(draft, game));
  };

  if (players.isPending) return <ListSkeleton rows={3} withImage={false} />;

  // Hlavní hráči (členové s účtem) jsou vidět vždy, hosté až po „Přidat hosty“ (vybraní hosté ale vždy).
  // Host, který je ve správě hostů skrytý, se nenabízí; pokud už v zápisu hraje (úprava zápisu), zůstává vidět.
  const isSelected = (id: number) => draft.players.some((p) => p.playerId === id);
  // Vlastní hráči hry (kooperativní hry) jsou vidět vždy a první; hráči jiných her se mezi hosty nenabízejí.
  const isGamePlayer = (id: number) => game.players.some((p) => p.id === id);
  const rank = (m: { id: number; hasAccount: boolean }) => (isGamePlayer(m.id) ? 0 : m.hasAccount ? 1 : 2);
  const visible = (players.data ?? [])
    .filter((m) => isGamePlayer(m.id) || m.hasAccount || isSelected(m.id) || (showGuests && m.visible && !m.gamePlayer))
    .sort((a, b) => rank(a) - rank(b));

  return (
    <section className="page">
      <GameHeader name={game.name} imageUrl={game.imageUrl} />
      <form className="pad form" onSubmit={submit}>
        <h2>{heading}</h2>

        <label>Číslo sezení<input type="number" inputMode="numeric" min={1} step={1} value={shownSession} onChange={(e) => patch({ sessionNumber: e.target.value, sessionNumberTouched: true })} /></label>

        <div className="row">
          <label>Začátek<input type="datetime-local" value={draft.startedAt} onChange={(e) => changeStart(e.target.value)} required /></label>
          <label>Konec<input type="datetime-local" value={draft.endedAt} onChange={(e) => patch({ endedAt: e.target.value })} required /></label>
        </div>

        <div className="field">
          <span className="field-label">Hráči</span>
          {visible.map((m) => (
            <PlayerRow
              key={m.id}
              member={m}
              game={game}
              player={draft.players.find((p) => p.playerId === m.id)}
              isGamePlayer={isGamePlayer(m.id)}
              scored={scored}
              winner={isWinner(draft, game, m.id)}
              onToggle={() => togglePlayer(m.id)}
              onPatch={(changes) => patchPlayer(m.id, changes)}
              onToggleWinner={() => toggleWinner(m.id)}
              onToggleStarting={() => toggleStarting(m.id)}
            />
          ))}

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
          <label htmlFor="play-tags" className="field-label">Tagy</label>
          <TagInput id="play-tags" tags={draft.tags} options={tags.data ?? []} onChange={(t) => patch({ tags: t })} />
        </div>
        <label>Poznámka<textarea value={draft.notes} rows={3} onChange={(e) => patch({ notes: e.target.value })} /></label>

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
