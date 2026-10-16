import type { GameSort } from '../api/types';

export interface GameListSettings {
  query: string;
  filterVisible: boolean;
  sort: GameSort;
  sessionNumber: number | null;
}

export const DEFAULT_GAME_LIST_SETTINGS: GameListSettings = { query: '', filterVisible: false, sort: 'activity', sessionNumber: null };
/** Správa her řadí výchozí podle názvu. */
export const DEFAULT_MANAGE_GAMES_SETTINGS: GameListSettings = { ...DEFAULT_GAME_LIST_SETTINGS, sort: 'name' };

export const GAME_LIST_KEY = 'hesk.gameList';
export const MANAGE_GAMES_KEY = 'hesk.manageGames';

/** Nastavení hledání/filtru seznamu her přežije přechod na detail a zpět (jen v rámci záložky). */
export function loadGameListSettings(key = GAME_LIST_KEY, defaults = DEFAULT_GAME_LIST_SETTINGS): GameListSettings {
  try {
    const raw = JSON.parse(sessionStorage.getItem(key) ?? 'null') as Partial<GameListSettings> | null;
    if (!raw || typeof raw !== 'object') return defaults;
    return {
      query: typeof raw.query === 'string' ? raw.query : '',
      filterVisible: raw.filterVisible === true,
      sort: raw.sort === 'name' || raw.sort === 'activity' ? raw.sort : defaults.sort,
      sessionNumber: typeof raw.sessionNumber === 'number' && Number.isInteger(raw.sessionNumber) ? raw.sessionNumber : null,
    };
  } catch {
    return defaults;
  }
}

export function saveGameListSettings(settings: GameListSettings, key = GAME_LIST_KEY): void {
  try {
    sessionStorage.setItem(key, JSON.stringify(settings));
  } catch {
    // úložiště nemusí být dostupné; nastavení se prostě nezapamatuje
  }
}
