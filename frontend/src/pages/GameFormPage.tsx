import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { api, bggImageProxyUrl } from '../api';
import type { BggPreparedGame, GameInput } from '../api/types';
import { Actions } from '../components/Actions';
import { BggGameSearch } from '../components/BggGameSearch';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
import { ImageCropPopup } from '../components/ImageCropPopup';
import { NamedListEditor } from '../components/NamedListEditor';
import { PageTitle } from '../components/PageTitle';
import { SafeHtml } from '../components/SafeHtml';
import { renderCheatSheet } from '../lib/cheatSheet';
import { type CropArea, cropToBlob } from '../lib/cropImage';
import { type EditItem, fromEditItems, toEditItems } from '../lib/namedItems';
import { SCORE_TYPE_LABELS, SCORE_TYPES } from '../lib/scoring';

interface FormState {
  name: string;
  shortName: string;
  scoreType: GameInput['scoreType'];
  imageUrl: string;
  description: string;
  cheatSheet: string;
  addons: EditItem[];
  characters: EditItem[];
  players: EditItem[];
  bggId: number | null;
}

const EMPTY: FormState = { name: '', shortName: '', scoreType: 'most_points', imageUrl: '', description: '', cheatSheet: '', addons: [], characters: [], players: [], bggId: null };

/** Formulář hry (nová hra / úprava); vstupuje se do něj ze správy her. */
export function GameFormPage() {
  const { id } = useParams();
  const gameId = id ? Number(id) : null;
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const existing = useQuery({ queryKey: ['game', gameId], queryFn: () => api.game(gameId!), enabled: gameId !== null });
  const [form, setForm] = useState<FormState>(EMPTY);
  // Vybraný soubor čeká v editoru na oříznutí (object URL; po zavření se uvolní).
  const [cropSrc, setCropSrc] = useState<string | null>(null);
  // Nová hra začíná hledáním; formulář se ukáže po výběru hry z BGG nebo po „Založit vlastní hru“.
  const [started, setStarted] = useState(gameId !== null);
  // Originál obrázku na BGG CDN: jen náhled, lokálně se uloží až výřez z ořezového editoru.
  const [bggImage, setBggImage] = useState<string | null>(null);
  const fileInput = useRef<HTMLInputElement>(null);

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
        players: toEditItems(g.players),
        bggId: g.bggId,
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
      const input: GameInput = {
        name: form.name.trim(),
        shortName: form.shortName.trim() || null,
        scoreType: form.scoreType,
        imageUrl: form.imageUrl.trim() || null,
        description: form.description.trim() || null,
        cheatSheet: form.cheatSheet.trim() || null,
        addons: fromEditItems(form.addons),
        characters: fromEditItems(form.characters),
        players: fromEditItems(form.players),
        bggId: form.bggId,
      };
      return gameId ? api.updateGame(gameId, input) : api.createGame(party.id, input);
    },
    onSuccess: () => done('/manage/games'),
  });
  const upload = useMutation({
    mutationFn: async (area: CropArea) => api.uploadGameImage(party.id, await cropToBlob(cropSrc!, area)),
    onSuccess: ({ url }) => {
      set({ imageUrl: url });
      setBggImage(null);
      closeCrop();
    },
  });
  const closeCrop = () => {
    if (cropSrc?.startsWith('blob:')) URL.revokeObjectURL(cropSrc);
    setCropSrc(null);
    upload.reset();
  };
  const pickFile = (file: File | undefined) => {
    if (fileInput.current) fileInput.current.value = ''; // stejný soubor jde vybrat znovu
    if (file) setCropSrc(URL.createObjectURL(file));
  };
  const pickBgg = (game: BggPreparedGame) => {
    setForm({ ...EMPTY, name: game.name, bggId: game.bggId });
    setBggImage(game.bggImageUrl);
    setStarted(true);
    // uživatel obrázek potvrdí a ořízne hned (zrušením zůstane náhled a tlačítko níž)
    if (game.bggImageUrl) setCropSrc(bggImageProxyUrl(game.bggImageUrl));
  };
  const startCustom = (name: string) => {
    setForm({ ...EMPTY, name });
    setStarted(true);
  };
  const remove = useMutation({ mutationFn: () => api.deleteGame(gameId!), onSuccess: () => done('/manage/games') });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate();
  };
  const set = (changes: Partial<FormState>) => setForm((f) => ({ ...f, ...changes }));

  if (gameId !== null && existing.isPending) return <p className="muted pad">Načítám…</p>;

  if (!started) {
    return (
      <section className="page">
        <PageTitle>Nová hra</PageTitle>
        <BggGameSearch partyId={party.id} onPick={pickBgg} onCustom={startCustom} />
      </section>
    );
  }
  const preview = form.imageUrl.trim() || bggImage;

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
        <div className="field">
          <span className="field-label">Obrázek</span>
          <div className="cover-field">
            <GameImage url={preview || null} />
            <input ref={fileInput} type="file" accept="image/*" hidden aria-label="Soubor s obrázkem" onChange={(e) => pickFile(e.target.files?.[0])} />
            {bggImage && !form.imageUrl.trim() && <button type="button" className="btn small" onClick={() => setCropSrc(bggImageProxyUrl(bggImage))}>Oříznout obrázek z BGG</button>}
            <button type="button" className="btn small outline" onClick={() => fileInput.current?.click()}>{form.imageUrl.trim() ? 'Změnit obrázek' : bggImage ? 'Nahrát vlastní obrázek' : 'Nahrát obrázek'}</button>
            {preview && <button type="button" className="btn small outline-secondary" onClick={() => { set({ imageUrl: '' }); setBggImage(null); }}>Odebrat obrázek</button>}
          </div>
          {bggImage && !form.imageUrl.trim() && <p className="muted hint">Obrázek z BoardGameGeek se uloží až po oříznutí.</p>}
        </div>
        {form.bggId && <p className="muted hint">Hra na <a href={`https://boardgamegeek.com/boardgame/${form.bggId}`} target="_blank" rel="noreferrer">BoardGameGeek</a>.</p>}
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

        <NamedListEditor
          label="Hráči hry"
          noun="hráč hry"
          nounAccusative="hráče hry"
          addLabel="Přidat hráče hry"
          emptyText="Hra nemá vlastní hráče."
          maxLength={100}
          hint="Proti nim hrají lidští hráči (kooperativní hry); v zápisu jsou vybraní automaticky."
          items={form.players}
          onChange={(players) => set({ players })}
        />

        <label>Cheatsheet (Markdown nebo HTML)<textarea rows={6} value={form.cheatSheet} onChange={(e) => set({ cheatSheet: e.target.value })} /></label>
        {form.cheatSheet.trim() && (
          <details>
            <summary>Náhled cheatsheetu</summary>
            <SafeHtml html={renderCheatSheet(form.cheatSheet)} />
          </details>
        )}
        <ErrorMessage error={save.error ?? remove.error} />
        {cropSrc && (
          <ImageCropPopup
            src={cropSrc}
            busy={upload.isPending}
            error={upload.error ? (upload.error.message) : null}
            onConfirm={(area) => upload.mutate(area)}
            onCancel={closeCrop}
          />
        )}
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
