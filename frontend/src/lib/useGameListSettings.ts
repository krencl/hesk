import { useEffect, useState } from 'react';
import { DEFAULT_GAME_LIST_SETTINGS, GAME_LIST_KEY, type GameListSettings, loadGameListSettings, saveGameListSettings } from './listSettings';

/** Hledání/filtr seznamu her: načte se z úložiště záložky, změny se průběžně ukládají (přežijí přechod na detail a zpět). */
export function useGameListSettings(key = GAME_LIST_KEY, defaults = DEFAULT_GAME_LIST_SETTINGS) {
  const [settings, setSettings] = useState(() => loadGameListSettings(key, defaults));
  const patch = (changes: Partial<GameListSettings>) => setSettings((s) => ({ ...s, ...changes }));
  useEffect(() => saveGameListSettings(settings, key), [settings, key]);
  return [settings, patch] as const;
}
