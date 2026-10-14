import type { GameSort } from '../api/types';

export interface GameListSettings {
  query: string;
  filterVisible: boolean;
  sort: GameSort;
  sessionNumber: number | null;
}

export const DEFAULT_GAME_LIST_SETTINGS: GameListSettings = { query: '', filterVisible: false, sort: 'activity', sessionNumber: null };

const KEY = 'hesk.gameList';

/** Nastavení hledání/filtru seznamu her přežije přechod na detail a zpět (jen v rámci záložky). */
export function loadGameListSettings(): GameListSettings {
  try {
    const raw = JSON.parse(sessionStorage.getItem(KEY) ?? 'null') as Partial<GameListSettings> | null;
    if (!raw || typeof raw !== 'object') return DEFAULT_GAME_LIST_SETTINGS;
    return {
      query: typeof raw.query === 'string' ? raw.query : '',
      filterVisible: raw.filterVisible === true,
      sort: raw.sort === 'name' ? 'name' : 'activity',
      sessionNumber: typeof raw.sessionNumber === 'number' && Number.isInteger(raw.sessionNumber) ? raw.sessionNumber : null,
    };
  } catch {
    return DEFAULT_GAME_LIST_SETTINGS;
  }
}

export function saveGameListSettings(settings: GameListSettings): void {
  try {
    sessionStorage.setItem(KEY, JSON.stringify(settings));
  } catch {
    // úložiště nemusí být dostupné; nastavení se prostě nezapamatuje
  }
}
