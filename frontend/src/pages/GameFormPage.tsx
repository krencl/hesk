import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { type FormEvent, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useActiveParty, useApp } from '../AppContext';
import { api, bggImageProxyUrl } from '../api';
import { qk } from '../api/queryKeys';
import type { BggPreparedGame, Game, GameInput } from '../api/types';
import { Actions } from '../components/Actions';
import { BggGameSearch } from '../components/BggGameSearch';
import { ConfirmPopup } from '../components/ConfirmPopup';
import { ErrorMessage } from '../components/ErrorMessage';
import { GameImage } from '../components/GameImage';
import { ImageCropPopup } from '../components/ImageCropPopup';
import { NamedListEditor } from '../components/NamedListEditor';
import { EmptyState, isNotFound, LoadingOrError } from '../components/PageState';
import { PageTitle } from '../components/PageTitle';
import { SafeHtml } from '../components/SafeHtml';
import { TagInput } from '../components/TagInput';
import { renderCheatSheet } from '../lib/cheatSheet';
import { type CropArea, cropToBlob } from '../lib/cropImage';
import { emptyFormState, type FormState, toFormState, toGameInput } from '../lib/gameForm';
import { canManageGames } from '../lib/roles';
import { SCORE_TYPE_LABELS, SCORE_TYPES } from '../lib/scoring';
import { useGame } from '../lib/useGame';

/**
 * Formulář hry (přidání / úprava); vstupuje se do něj ze správy her. Hra je sdílená mezi partami (ADR 0023): sdílené údaje
 * smí měnit jen správce her, ostatní upravují jen vlastní hráče hry své party.
 */
export function GameFormPage() {
  const { id } = useParams();
  return id ? <EditGame gameId={Number(id)} /> : <NewGame />;
}

/** Úprava: formulář se ukáže až s načtenou hrou (při chybě by prázdný formulář po uložení smazal rozšíření, postavy, tagy i hráče hry). */
function EditGame({ gameId }: { gameId: number }) {
  const game = useGame(gameId);
  if (isNotFound(game.error)) return <EmptyState message="Tahle hra neexistuje nebo k ní nemáš přístup." backTo="/manage/games" backLabel="Zpět" />;
  if (!game.data) return <LoadingOrError error={game.error} rows={3} withImage={false} />;
  // `key`: při přechodu na jinou hru se formulář musí založit znovu, jeho stav se kopíruje z dat jen jednou
  return <GameForm key={game.data.id} gameId={gameId} initial={toFormState(game.data)} />;
}

/** Nová hra začíná hledáním; formulář se ukáže po výběru hry z BGG nebo po „Založit vlastní hru“. */
function NewGame() {
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [start, setStart] = useState<{ form: FormState; bggImage: string | null } | null>(null);

  /** Hra z katalogu je v partě: rovnou se otevře její detail, nic se nezakládá. */
  const linked = async (game: Game) => {
    await queryClient.invalidateQueries({ queryKey: qk.games(party.id) });
    navigate(`/games/${game.id}`);
  };

  if (start) return <GameForm gameId={null} initial={start.form} initialBggImage={start.bggImage} />;
  return (
    <section className="page">
      <PageTitle>Přidat hru</PageTitle>
      <BggGameSearch
        partyId={party.id}
        onPick={(game: BggPreparedGame) => setStart({ form: emptyFormState({ name: game.name, bggId: game.bggId }), bggImage: game.bggImageUrl })}
        onLinked={linked}
        onCustom={(name) => setStart({ form: emptyFormState({ name }), bggImage: null })}
      />
    </section>
  );
}

interface FormProps {
  /** `null` = nová hra. */
  gameId: number | null;
  initial: FormState;
  /** Originál obrázku na BGG CDN: jen náhled, lokálně se uloží až výřez z ořezového editoru. */
  initialBggImage?: string | null;
}

type Confirming = 'unlink' | 'delete';

function GameForm({ gameId, initial, initialBggImage = null }: FormProps) {
  const party = useActiveParty();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { me } = useApp();
  // Jen správce her upravuje sdílené údaje; ostatním jsou pole zamčená (hráči hry patří partě a jdou měnit vždy).
  const readOnly = gameId !== null && !canManageGames(me);
  const [form, setForm] = useState<FormState>(() => initial);
  // Vybraný soubor čeká v editoru na oříznutí (object URL; po zavření se uvolní). Hra z BGG otevře ořez hned
  // (zrušením zůstane náhled a tlačítko níž).
  const [cropSrc, setCropSrc] = useState<string | null>(() => (initialBggImage ? bggImageProxyUrl(initialBggImage) : null));
  const [bggImage, setBggImage] = useState<string | null>(initialBggImage);
  const [confirming, setConfirming] = useState<Confirming | null>(null);
  const fileInput = useRef<HTMLInputElement>(null);
  const gameTags = useQuery({ queryKey: qk.gameTags(), queryFn: api.gameTags });
  const cheatSheetHtml = useMemo(() => (form.cheatSheet.trim() ? renderCheatSheet(form.cheatSheet) : ''), [form.cheatSheet]);

  /** Po uložení zneplatní, co se hrou změnilo; hra, která z party zmizela, se z cache zahodí (nemá smysl ji načítat znovu). */
  const done = async (target: string, { gone = false } = {}) => {
    if (gone && gameId !== null) queryClient.removeQueries({ queryKey: qk.game(gameId) });
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: qk.games(party.id) }),
      ...(gone || gameId === null ? [] : [queryClient.invalidateQueries({ queryKey: qk.game(gameId) })]),
      queryClient.invalidateQueries({ queryKey: qk.gameTags() }),
      queryClient.invalidateQueries({ queryKey: qk.partyGameTags() }),
      queryClient.invalidateQueries({ queryKey: qk.stats(party.id) }),
      queryClient.invalidateQueries({ queryKey: qk.players(party.id) }), // hráči hry se zakládají jako hosté
    ]);
    navigate(target);
  };
  const save = useMutation({
    mutationFn: async () => {
      const input: GameInput = toGameInput(form);
      if (gameId === null) return api.createGame(party.id, input);
      if (!readOnly) await api.updateGame(gameId, input);
      return api.setGamePlayers(party.id, gameId, input.players);
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
  const unlink = useMutation({ mutationFn: () => api.unlinkGame(party.id, gameId!), onSuccess: () => done('/manage/games', { gone: true }) });
  const remove = useMutation({ mutationFn: () => api.deleteGame(gameId!), onSuccess: () => done('/manage/games', { gone: true }) });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    save.mutate();
  };
  const set = (changes: Partial<FormState>) => setForm((f) => ({ ...f, ...changes }));
  const preview = form.imageUrl.trim() || bggImage;

  return (
    <section className="page">
      <PageTitle>{gameId ? 'Upravit hru' : 'Přidat hru'}</PageTitle>
      <form className="pad form" onSubmit={submit}>
        {readOnly && <p className="muted hint">Údaje hry jsou společné všem partám a upravuje je jen správce her. Tady můžeš změnit jen hráče hry své party.</p>}
        <fieldset className="plain" disabled={readOnly}>
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
        <div className="field">
          <label htmlFor="game-tags" className="field-label">Tagy</label>
          <TagInput id="game-tags" tags={form.tags} options={gameTags.data ?? []} onChange={(tags) => set({ tags })} />
        </div>

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

        </fieldset>
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

        <fieldset className="plain" disabled={readOnly}>
          <label>Cheatsheet (Markdown nebo HTML)<textarea rows={6} value={form.cheatSheet} onChange={(e) => set({ cheatSheet: e.target.value })} /></label>
        </fieldset>
        {cheatSheetHtml && (
          <details>
            <summary>Náhled cheatsheetu</summary>
            <SafeHtml html={cheatSheetHtml} />
          </details>
        )}
        <ErrorMessage error={save.error ?? unlink.error ?? remove.error} />
        {cropSrc && (
          <ImageCropPopup
            src={cropSrc}
            busy={upload.isPending}
            error={upload.error ? (upload.error.message) : null}
            onConfirm={(area) => upload.mutate(area)}
            onCancel={closeCrop}
          />
        )}
        {confirming === 'unlink' && (
          <ConfirmPopup
            title="Odebrat hru z party?"
            confirmLabel="Odebrat"
            destructive
            onCancel={() => setConfirming(null)}
            onConfirm={() => {
              unlink.mutate();
              setConfirming(null);
            }}
          >
            <p>Hra zůstane v databázi her.</p>
          </ConfirmPopup>
        )}
        {confirming === 'delete' && (
          <ConfirmPopup
            title="Smazat hru z databáze všech part?"
            confirmLabel="Smazat"
            destructive
            onCancel={() => setConfirming(null)}
            onConfirm={() => {
              remove.mutate();
              setConfirming(null);
            }}
          >
            <p>Hra zmizí z katalogu všech part a nejde to vrátit zpět. Hru s odehranými zápisy nelze smazat.</p>
          </ConfirmPopup>
        )}
        <Actions>
          <button className="btn" type="submit" disabled={save.isPending}>Uložit</button>
          <Link className="btn outline-secondary" to="/manage/games">Zpět</Link>
        </Actions>
        {gameId && (
          <p className="subtle-action">
            <button type="button" className="link subtle" onClick={() => setConfirming('unlink')}>Odebrat z party</button>
            {!readOnly && <> · <button type="button" className="link subtle" onClick={() => setConfirming('delete')}>Smazat hru z databáze</button></>}
          </p>
        )}
      </form>
    </section>
  );
}
