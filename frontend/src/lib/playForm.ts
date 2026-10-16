import type { Game, Play, PlayDefaults, Player, PlayerInput, PlayInput, ScoreType } from '../api/types';
import { fromLocalInput, toLocalInput } from './datetime';

export interface PlayerDraft {
  playerId: number;
  score: string;
  rank: string;
  winner: boolean;
  starting: boolean;
  /** Postava hráče (jen u her s postavami). */
  characterId: number | null;
}

export interface PlayDraft {
  gameId: number | null;
  startedAt: string;
  endedAt: string;
  players: PlayerDraft[];
  addonIds: number[];
  tags: string[];
  notes: string;
  /** Číslo sezení jako text z inputu; prázdné = určí backend. */
  sessionNumber: string;
  /** Uživatel číslo ručně změnil (jinak se předvyplňuje podle času zápisu a posílá se jen tehdy). */
  sessionNumberTouched: boolean;
  /** U her se skóre: vítěze vybral uživatel ručně (jinak vyhrává nejlepší skóre, při shodě všichni se shodou). */
  winnersTouched: boolean;
}

export const isScored = (type: ScoreType): boolean => type === 'most_points' || type === 'fewest_points';

export function parseScore(text: string): number | null {
  const trimmed = text.trim();
  if (trimmed === '') return null;
  const n = Number(trimmed.replace(',', '.'));
  return Number.isNaN(n) ? null : n;
}

/** Kdo by vyhrál podle skóre (nejlepší hodnota, při shodě všichni se shodou); hráči bez skóre se nepočítají. */
export function bestScoreIds(scores: { playerId: number; score: number | null }[], type: ScoreType): Set<number> {
  const valid = scores.filter((s): s is { playerId: number; score: number } => s.score !== null);
  if (valid.length === 0) return new Set();
  const best = type === 'fewest_points' ? Math.min(...valid.map((s) => s.score)) : Math.max(...valid.map((s) => s.score));
  return new Set(valid.filter((s) => s.score === best).map((s) => s.playerId));
}

/** Je hráč vítězem tak, jak to formulář aktuálně ukazuje (u her se skóre buď ručně, nebo podle skóre). */
export function isWinner(draft: PlayDraft, game: Pick<Game, 'scoreType'>, playerId: number): boolean {
  const player = draft.players.find((p) => p.playerId === playerId);
  if (!player) return false;
  if (!isScored(game.scoreType)) return player.winner;
  if (draft.winnersTouched) return player.winner;
  return bestScoreIds(draft.players.map((p) => ({ playerId: p.playerId, score: parseScore(p.score) })), game.scoreType).has(playerId);
}

/** Číslo sezení z inputu; neplatné nebo prázdné → `null`. */
export function parseSessionNumber(text: string): number | null {
  const trimmed = text.trim();
  return /^\d+$/.test(trimmed) && Number(trimmed) >= 1 ? Number(trimmed) : null;
}

export const newPlayerDraft = (playerId: number): PlayerDraft => ({ playerId, score: '', rank: '', winner: false, starting: false, characterId: null });

/** Výchozí čas nového zápisu: začátek před hodinou, konec teď. */
export function defaultTimes(now = new Date()): { startedAt: string; endedAt: string } {
  return { startedAt: toLocalInput(new Date(now.getTime() - 60 * 60 * 1000)), endedAt: toLocalInput(now) };
}

/**
 * Předvybraní hráči nového zápisu: hráči posledního zápisu stejného sezení (do 24 h);
 * když sezení ještě není (první hra dne), členové party s účtem.
 */
export function defaultPlayerIds(defaults: Pick<PlayDefaults, 'playerIds'> | undefined, players: Pick<Player, 'id' | 'hasAccount'>[]): number[] {
  return defaults?.playerIds ?? players.filter((p) => p.hasAccount).map((p) => p.id);
}

/** Formulář nového zápisu; vlastní hráč hry (je-li) je vždy vybraný jako první, dál `playerIds` a předvyplněné číslo sezení. */
export function emptyDraft(game: Pick<Game, 'id' | 'player'>, now = new Date(), prefill: { playerIds?: number[]; sessionNumber?: number } = {}): PlayDraft {
  const playerIds = [...new Set([...(game.player ? [game.player.id] : []), ...(prefill.playerIds ?? [])])];
  return {
    gameId: game.id,
    ...defaultTimes(now),
    players: playerIds.map(newPlayerDraft),
    addonIds: [],
    tags: [],
    notes: '',
    sessionNumber: prefill.sessionNumber === undefined ? '' : String(prefill.sessionNumber),
    sessionNumberTouched: false,
    winnersTouched: false,
  };
}

/** Hodnoty formuláře z existujícího zápisu (pro úpravu); opak funkce `toPlayInput`. */
export function draftFromPlay(play: Play): PlayDraft {
  const type = play.game.scoreType;
  // Ručně určený vítěz = vítězové se liší od toho, co by vyšlo ze skóre (např. rozhodnutí při shodě).
  const auto = bestScoreIds(play.results.map((r) => ({ playerId: r.playerId, score: r.score })), type);
  const actual = new Set(play.results.filter((r) => r.winner).map((r) => r.playerId));
  const touched = isScored(type) && (auto.size !== actual.size || [...actual].some((id) => !auto.has(id)));
  return {
    gameId: play.game.id,
    startedAt: toLocalInput(new Date(play.startedAt)),
    endedAt: toLocalInput(new Date(play.endedAt)),
    players: play.results.map((r) => ({
      playerId: r.playerId,
      score: r.score === null ? '' : String(r.score),
      rank: String(r.rank),
      winner: r.winner,
      starting: r.starting,
      characterId: r.character?.id ?? null,
    })),
    addonIds: play.addons.map((a) => a.id),
    tags: play.tags,
    notes: play.notes ?? '',
    sessionNumber: String(play.sessionNumber),
    sessionNumberTouched: false,
    winnersTouched: touched,
  };
}

/** Nejmenší nutná konzistence formuláře před odesláním; doménová pravidla hlídá backend. */
export function validateDraft(draft: PlayDraft, game: Game | undefined): string | null {
  if (!game) return 'Vyber hru.';
  if (draft.players.length === 0) return 'Vyber alespoň jednoho hráče.';
  if (!draft.startedAt || !draft.endedAt) return 'Vyplň začátek i konec.';
  if (new Date(draft.endedAt) < new Date(draft.startedAt)) return 'Konec nesmí být před začátkem.';
  if (draft.sessionNumberTouched && parseSessionNumber(draft.sessionNumber) === null) return 'Číslo sezení musí být kladné celé číslo.';
  for (const p of draft.players) {
    if (isScored(game.scoreType) && p.score.trim() === '') return 'Zadej skóre všem hráčům.';
    if (isScored(game.scoreType) && parseScore(p.score) === null) return 'Skóre musí být číslo.';
    if (game.scoreType === 'rank_order' && !(Number(p.rank) >= 1)) return 'Zadej pořadí (od 1) všem hráčům.';
  }
  if (game.scoreType === 'winner_only' && !draft.players.some((p) => p.winner)) return 'Označ alespoň jednoho vítěze.';
  if (isScored(game.scoreType) && draft.winnersTouched && !draft.players.some((p) => p.winner)) return 'Označ alespoň jednoho vítěze.';
  if (draft.players.filter((p) => p.starting).length > 1) return 'Začínající hráč může být jen jeden.';
  return null;
}

export function toPlayInput(draft: PlayDraft, game: Game): PlayInput {
  const players: PlayerInput[] = draft.players.map((p) => {
    const character = p.characterId !== null && game.characters.some((c) => c.id === p.characterId) ? { characterId: p.characterId } : {};
    switch (game.scoreType) {
      case 'most_points':
      case 'fewest_points':
        return { playerId: p.playerId, score: Number(p.score.replace(',', '.')), starting: p.starting, ...(draft.winnersTouched ? { winner: p.winner } : {}), ...character };
      case 'rank_order':
        return { playerId: p.playerId, rank: Number(p.rank), starting: p.starting, ...character };
      case 'winner_only':
        return { playerId: p.playerId, winner: p.winner, starting: p.starting, ...character };
    }
  });
  return {
    gameId: game.id,
    startedAt: fromLocalInput(draft.startedAt),
    endedAt: fromLocalInput(draft.endedAt),
    players,
    addonIds: draft.addonIds.filter((id) => game.addons.some((a) => a.id === id)),
    tags: draft.tags,
    notes: draft.notes.trim() || null,
    ...(draft.sessionNumberTouched ? { sessionNumber: parseSessionNumber(draft.sessionNumber) } : {}),
  };
}
