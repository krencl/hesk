import type { Game, PlayerInput, PlayInput } from '../api/types';
import { fromLocalInput } from './datetime';

export interface PlayerDraft {
  playerId: number;
  score: string;
  rank: string;
  winner: boolean;
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
}

/** Nejmenší nutná konzistence formuláře před odesláním; doménová pravidla hlídá backend. */
export function validateDraft(draft: PlayDraft, game: Game | undefined): string | null {
  if (!game) return 'Vyber hru.';
  if (draft.players.length === 0) return 'Vyber alespoň jednoho hráče.';
  if (!draft.startedAt || !draft.endedAt) return 'Vyplň začátek i konec.';
  if (new Date(draft.endedAt) < new Date(draft.startedAt)) return 'Konec nesmí být před začátkem.';
  for (const p of draft.players) {
    if ((game.scoreType === 'most_points' || game.scoreType === 'fewest_points') && p.score.trim() === '') return 'Zadej skóre všem hráčům.';
    if (game.scoreType === 'rank_order' && !(Number(p.rank) >= 1)) return 'Zadej pořadí (od 1) všem hráčům.';
  }
  if (game.scoreType === 'winner_only' && !draft.players.some((p) => p.winner)) return 'Označ alespoň jednoho vítěze.';
  return null;
}

export function toPlayInput(draft: PlayDraft, game: Game): PlayInput {
  const players: PlayerInput[] = draft.players.map((p) => {
    switch (game.scoreType) {
      case 'most_points':
      case 'fewest_points':
        return { playerId: p.playerId, score: Number(p.score.replace(',', '.')) };
      case 'rank_order':
        return { playerId: p.playerId, rank: Number(p.rank) };
      case 'winner_only':
        return { playerId: p.playerId, winner: p.winner };
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
