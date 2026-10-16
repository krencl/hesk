import type { Game, Play, PlayerInput, PlayInput, ScoreType } from '../api/types';
import { fromLocalInput, toLocalInput } from './datetime';

export interface PlayerDraft {
  playerId: number;
  score: string;
  rank: string;
  winner: boolean;
  starting: boolean;
}

export interface PlayDraft {
  gameId: number | null;
  startedAt: string;
  endedAt: string;
  players: PlayerDraft[];
  addonIds: number[];
  place: string;
  tags: string;
  notes: string;
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

/** Prázdný formulář nového zápisu: začátek před hodinou, konec teď. */
export function emptyDraft(gameId: number, now = new Date()): PlayDraft {
  return {
    gameId,
    startedAt: toLocalInput(new Date(now.getTime() - 60 * 60 * 1000)),
    endedAt: toLocalInput(now),
    players: [],
    addonIds: [],
    place: '',
    tags: '',
    notes: '',
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
    })),
    addonIds: play.addons.map((a) => a.id),
    place: play.place ?? '',
    tags: play.tags.join(', '),
    notes: play.notes ?? '',
    winnersTouched: touched,
  };
}

/** Nejmenší nutná konzistence formuláře před odesláním; doménová pravidla hlídá backend. */
export function validateDraft(draft: PlayDraft, game: Game | undefined): string | null {
  if (!game) return 'Vyber hru.';
  if (draft.players.length === 0) return 'Vyber alespoň jednoho hráče.';
  if (!draft.startedAt || !draft.endedAt) return 'Vyplň začátek i konec.';
  if (new Date(draft.endedAt) < new Date(draft.startedAt)) return 'Konec nesmí být před začátkem.';
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
    switch (game.scoreType) {
      case 'most_points':
      case 'fewest_points':
        return { playerId: p.playerId, score: Number(p.score.replace(',', '.')), starting: p.starting, ...(draft.winnersTouched ? { winner: p.winner } : {}) };
      case 'rank_order':
        return { playerId: p.playerId, rank: Number(p.rank), starting: p.starting };
      case 'winner_only':
        return { playerId: p.playerId, winner: p.winner, starting: p.starting };
    }
  });
  return {
    gameId: game.id,
    startedAt: fromLocalInput(draft.startedAt),
    endedAt: fromLocalInput(draft.endedAt),
    players,
    addonIds: draft.addonIds.filter((id) => game.addons.some((a) => a.id === id)),
    place: draft.place.trim() || null,
    tags: draft.tags.split(',').map((t) => t.trim()).filter(Boolean),
    notes: draft.notes.trim() || null,
  };
}
