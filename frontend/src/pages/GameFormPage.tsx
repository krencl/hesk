import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import type { GameInput } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { NamedListEditor } from '../components/NamedListEditor';
import { PageTitle } from '../components/PageTitle';
import { SafeHtml } from '../components/SafeHtml';
import { renderCheatSheet } from '../lib/cheatSheet';
import { type EditItem, fromEditItems, toEditItems } from '../lib/namedItems';
import { SCORE_TYPE_LABELS, SCORE_TYPES } from '../lib/scoring';

/** Volba hráče hry: žádný, existující hráč (id), nebo nový host (vznikne při uložení). */
type PlayerChoice = '' | 'new' | `${number}`;

interface FormState {
  name: string;
  shortName: string;
  scoreType: GameInput['scoreType'];
  imageUrl: string;
  description: string;
  cheatSheet: string;
  addons: EditItem[];
  characters: EditItem[];
  player: PlayerChoice;
  newPlayerName: string;
}

const EMPTY: FormState = { name: '', shortName: '', scoreType: 'most_points', imageUrl: '', description: '', cheatSheet: '', addons: [], characters: [], player: '', newPlayerName: '' };

/** Formulář hry (nová hra / úprava); vstupuje se do něj ze správy her. */
export function GameFormPage() {
  const { id } = useParams();
  const gameId = id ? Number(id) : null;
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const existing = useQuery({ queryKey: ['game', gameId], queryFn: () => api.game(gameId!), enabled: gameId !== null });
  const players = useQuery({ queryKey: ['players', party.id], queryFn: () => api.players(party.id) });
  const [form, setForm] = useState<FormState>(EMPTY);

  useEffect(() => {
    const g = existing.data;
    if (g) {
      setForm({
        name: g.name,
        shortName: g.shortName ?? '',
        scoreType: g.scoreType,
        imageUrl: g.imageUrl ?? '',
        description: g.description ?? '',
        cheatSheet: g.cheatSheet ?? '',
        addons: toEditItems(g.addons),
        characters: toEditItems(g.characters),
        player: g.player ? `${g.player.id}` : '',
        newPlayerName: '',
      });
    }
  }, [existing.data]);

  const done = async (target: string) => {
    await queryClient.invalidateQueries({ queryKey: ['games', party.id] });
    await queryClient.invalidateQueries({ queryKey: ['game', gameId] });
    navigate(target);
  };
  const save = useMutation({
    mutationFn: async () => {
      let playerId = form.player === '' || form.player === 'new' ? null : Number(form.player);
      if (form.player === 'new' && form.newPlayerName.trim()) {
        playerId = (await api.createGuest(party.id, form.newPlayerName.trim())).id;
        await queryClient.invalidateQueries({ queryKey: ['players', party.id] });
      }
      const input: GameInput = {
        name: form.name.trim(),
        shortName: form.shortName.trim() || null,
        scoreType: form.scoreType,
        imageUrl: form.imageUrl.trim() || null,
        description: form.description.trim() || null,
        cheatSheet: form.cheatSheet.trim() || null,
        addons: fromEditItems(form.addons),
        characters: fromEditItems(form.characters),
        playerId,
      };
      return gameId ? api.updateGame(gameId, input) : api.createGame(party.id, input);
    },
    onSuccess: () => done('/manage/games'),
  });
  const remove = useMutation({ mutationFn: () => api.deleteGame(gameId!), onSuccess: () => done('/manage/games') });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate();
  };
  const set = (changes: Partial<FormState>) => setForm((f) => ({ ...f, ...changes }));

  if (gameId !== null && existing.isPending) return <p className="muted pad">Načítám…</p>;

  // Hráčem hry bývá host (protihráč kooperativní hry); vybraný hráč se nabídne vždy.
  const playerOptions = (players.data ?? []).filter((p) => !p.hasAccount || `${p.id}` === form.player);

  return (
    <section className="page">
      <PageTitle>{gameId ? 'Upravit hru' : 'Nová hra'}</PageTitle>
      <form className="pad form" onSubmit={submit}>
        <label>Název<input value={form.name} onChange={(e) => set({ name: e.target.value })} maxLength={150} required /></label>
        <label>
          Zkrácený název
          <input value={form.shortName} onChange={(e) => set({ shortName: e.target.value })} maxLength={50} placeholder="např. Jungle (do statistik)" />
        </label>
        <label>
          Bodování
          <select value={form.scoreType} onChange={(e) => set({ scoreType: e.target.value as GameInput['scoreType'] })}>
            {SCORE_TYPES.map((t) => <option key={t} value={t}>{SCORE_TYPE_LABELS[t]}</option>)}
          </select>
        </label>
        <label>URL obrázku<input inputMode="url" value={form.imageUrl} placeholder="https://… nebo /static/covers/…" onChange={(e) => set({ imageUrl: e.target.value })} /></label>
        <label>Popis<textarea rows={3} value={form.description} onChange={(e) => set({ description: e.target.value })} /></label>

        <NamedListEditor
          label="Rozšíření"
          noun="rozšíření"
          addLabel="Přidat rozšíření"
          emptyText="Hra nemá rozšíření."
          items={form.addons}
          onChange={(addons) => set({ addons })}
        />
        <NamedListEditor
          label="Postavy"
          noun="postava"
          nounAccusative="postavu"
          addLabel="Přidat postavu"
          emptyText="Hra nemá postavy."
          items={form.characters}
          onChange={(characters) => set({ characters })}
        />

        <div className="field">
          <label htmlFor="game-player">Hráč hry</label>
          <select id="game-player" value={form.player} onChange={(e) => set({ player: e.target.value as PlayerChoice })}>
            <option value="">— hra nemá vlastního hráče —</option>
            {playerOptions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            <option value="new">Nový hráč…</option>
          </select>
          {form.player === 'new' && (
            <input value={form.newPlayerName} maxLength={100} placeholder="Jméno hráče hry (např. Boss)" aria-label="Jméno nového hráče hry" onChange={(e) => set({ newPlayerName: e.target.value })} />
          )}
          <span className="muted hint">Proti němu hrají lidští hráči (kooperativní hry); v zápisu je vybraný automaticky.</span>
        </div>

        <label>Cheatsheet (Markdown nebo HTML)<textarea rows={6} value={form.cheatSheet} onChange={(e) => set({ cheatSheet: e.target.value })} /></label>
        {form.cheatSheet.trim() && (
          <details>
            <summary>Náhled cheatsheetu</summary>
            <SafeHtml html={renderCheatSheet(form.cheatSheet)} />
          </details>
        )}
        <ErrorMessage error={save.error ?? remove.error} />
        <Actions>
          <button className="btn" type="submit" disabled={save.isPending}>Uložit</button>
          <Link className="btn outline-secondary" to="/manage/games">Zpět</Link>
        </Actions>
        {gameId && (
          <p className="subtle-action">
            <button type="button" className="link subtle" onClick={() => window.confirm('Smazat hru?') && remove.mutate()}>Smazat hru</button>
          </p>
        )}
      </form>
    </section>
  );
}
