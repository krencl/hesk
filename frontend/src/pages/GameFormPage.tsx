import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api } from '../api';
import type { GameInput } from '../api/types';
import { Actions } from '../components/Actions';
import { ErrorMessage } from '../components/ErrorMessage';
import { PageTitle } from '../components/PageTitle';
import { SafeHtml } from '../components/SafeHtml';
import { renderCheatSheet } from '../lib/cheatSheet';
import { SCORE_TYPE_LABELS, SCORE_TYPES } from '../lib/scoring';

const EMPTY = { name: '', scoreType: 'most_points', imageUrl: '', description: '', cheatSheet: '', addons: '' } as const;

export function GameFormPage() {
  const { id } = useParams();
  const gameId = id ? Number(id) : null;
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const existing = useQuery({ queryKey: ['game', gameId], queryFn: () => api.game(gameId!), enabled: gameId !== null });
  const [form, setForm] = useState<{ name: string; scoreType: GameInput['scoreType']; imageUrl: string; description: string; cheatSheet: string; addons: string }>({ ...EMPTY });

  useEffect(() => {
    const g = existing.data;
    if (g) setForm({ name: g.name, scoreType: g.scoreType, imageUrl: g.imageUrl ?? '', description: g.description ?? '', cheatSheet: g.cheatSheet ?? '', addons: g.addons.map((a) => a.name).join('\n') });
  }, [existing.data]);

  const toInput = (): GameInput => ({
    name: form.name.trim(),
    scoreType: form.scoreType,
    imageUrl: form.imageUrl.trim() || null,
    description: form.description.trim() || null,
    cheatSheet: form.cheatSheet.trim() || null,
    addons: form.addons.split('\n').map((a) => a.trim()).filter(Boolean),
  });

  const done = async (target: string) => {
    await queryClient.invalidateQueries({ queryKey: ['games', party.id] });
    await queryClient.invalidateQueries({ queryKey: ['game', gameId] });
    navigate(target);
  };
  const save = useMutation({
    mutationFn: () => (gameId ? api.updateGame(gameId, toInput()) : api.createGame(party.id, toInput())),
    onSuccess: () => done('/manage/games'),
  });
  const remove = useMutation({ mutationFn: () => api.deleteGame(gameId!), onSuccess: () => done('/manage/games') });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate();
  };
  const set = (changes: Partial<typeof form>) => setForm((f) => ({ ...f, ...changes }));

  if (gameId !== null && existing.isPending) return <p className="muted pad">Načítám…</p>;

  return (
    <section className="page">
      <PageTitle>{gameId ? 'Upravit hru' : 'Nová hra'}</PageTitle>
      <form className="pad form" onSubmit={submit}>
        <label>Název<input value={form.name} onChange={(e) => set({ name: e.target.value })} maxLength={150} required /></label>
        <label>
          Bodování
          <select value={form.scoreType} onChange={(e) => set({ scoreType: e.target.value as GameInput['scoreType'] })}>
            {SCORE_TYPES.map((t) => <option key={t} value={t}>{SCORE_TYPE_LABELS[t]}</option>)}
          </select>
        </label>
        <label>URL obrázku<input type="url" value={form.imageUrl} onChange={(e) => set({ imageUrl: e.target.value })} /></label>
        <label>Popis<textarea rows={3} value={form.description} onChange={(e) => set({ description: e.target.value })} /></label>
        <label>Přídavky (jeden na řádek)<textarea rows={3} value={form.addons} onChange={(e) => set({ addons: e.target.value })} /></label>
        <label>Taháček (Markdown nebo HTML)<textarea rows={6} value={form.cheatSheet} onChange={(e) => set({ cheatSheet: e.target.value })} /></label>
        {form.cheatSheet.trim() && (
          <details>
            <summary>Náhled taháčku</summary>
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
