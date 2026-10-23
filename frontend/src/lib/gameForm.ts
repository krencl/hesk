import type { Game, GameInput } from '../api/types';
import { type EditItem, fromEditItems, toEditItems } from './namedItems';

/** Rozpracovaný formulář hry (textová pole jako řetězce; seznamy jako řádky editoru s klientským klíčem). */
export interface FormState {
  name: string;
  shortName: string;
  scoreType: GameInput['scoreType'];
  imageUrl: string;
  description: string;
  cheatSheet: string;
  addons: EditItem[];
  characters: EditItem[];
  players: EditItem[];
  tags: string[];
  bggId: number | null;
}

export function emptyFormState(changes: Partial<FormState> = {}): FormState {
  return { name: '', shortName: '', scoreType: 'most_points', imageUrl: '', description: '', cheatSheet: '', addons: [], characters: [], players: [], tags: [], bggId: null, ...changes };
}

/** Uložená hra → stav formuláře (null z API se mění na prázdný řetězec). */
export function toFormState(game: Game): FormState {
  return {
    name: game.name,
    shortName: game.shortName ?? '',
    scoreType: game.scoreType,
    imageUrl: game.imageUrl ?? '',
    description: game.description ?? '',
    cheatSheet: game.cheatSheet ?? '',
    addons: toEditItems(game.addons),
    characters: toEditItems(game.characters),
    players: toEditItems(game.players),
    tags: game.tags,
    bggId: game.bggId,
  };
}

/** Stav formuláře → tělo požadavku: texty oříznuté, prázdné volitelné hodnoty jako null, seznamy bez prázdných řádků. */
export function toGameInput(form: FormState): GameInput {
  return {
    name: form.name.trim(),
    shortName: form.shortName.trim() || null,
    scoreType: form.scoreType,
    imageUrl: form.imageUrl.trim() || null,
    description: form.description.trim() || null,
    cheatSheet: form.cheatSheet.trim() || null,
    addons: fromEditItems(form.addons),
    characters: fromEditItems(form.characters),
    players: fromEditItems(form.players),
    tags: form.tags,
    bggId: form.bggId,
  };
}
