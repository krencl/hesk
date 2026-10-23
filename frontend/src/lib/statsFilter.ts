import type { StatsQuery } from '../api/types';

/** Položka filtru statistik: hra nebo tag hry; `include` = jen takové hry (zobrazeno „+“), `exclude` = bez nich („−“). */
export type FilterItem =
  | { kind: 'game'; id: number; name: string; mode: 'include' | 'exclude' }
  | { kind: 'tag'; name: string; mode: 'include' | 'exclude' };

export const itemKey = (item: FilterItem): string => (item.kind === 'game' ? `game:${item.id}` : `tag:${item.name}`);

/** Přidá položku jako „+“; už vybraná se nezdvojí. */
export function addItem(items: FilterItem[], item: FilterItem): FilterItem[] {
  return items.some((i) => itemKey(i) === itemKey(item)) ? items : [...items, item];
}

export const removeItem = (items: FilterItem[], item: FilterItem): FilterItem[] => items.filter((i) => itemKey(i) !== itemKey(item));

/** Přepne „+“ ↔ „−“. */
export const toggleItem = (items: FilterItem[], item: FilterItem): FilterItem[] =>
  items.map((i) => (itemKey(i) === itemKey(item) ? { ...i, mode: i.mode === 'include' ? 'exclude' : 'include' } : i));

/** Parametry pro `GET /stats`; seřazené, ať je klíč dotazu stabilní. */
export function toStatsQuery(items: FilterItem[]): Required<StatsQuery> {
  const names = (kind: 'tag', mode: FilterItem['mode']) => items.flatMap((i) => (i.kind === kind && i.mode === mode ? [i.name] : [])).sort();
  const ids = (mode: FilterItem['mode']) => items.flatMap((i) => (i.kind === 'game' && i.mode === mode ? [i.id] : [])).sort((a, b) => a - b);
  return { tags: names('tag', 'include'), excludeTags: names('tag', 'exclude'), games: ids('include'), excludeGames: ids('exclude') };
}

/**
 * Filtr v adrese stránky (sdílení odkazem): opakované parametry `games`, `excludeGames` (id her) a `tags`, `excludeTags`
 * (názvy tagů), stejně jako ve `GET /stats`. Pořadí štítků je vždy stejné (hry podle id, tagy podle názvu), ať štítky
 * po přepnutí „+“ ↔ „−“ neskáčou.
 */
export function statsSearch(items: FilterItem[]): URLSearchParams {
  const query = toStatsQuery(items);
  const params = new URLSearchParams();
  for (const [key, values] of Object.entries(query)) values.forEach((v) => params.append(key, String(v)));
  return params;
}

/** Opak `statsSearch`; `gameName` doplní názvy her (v adrese jsou jen id). Neplatná id se zahodí. */
export function parseStatsSearch(params: URLSearchParams, gameName: (id: number) => string): FilterItem[] {
  const ids = (key: string) => [...new Set(params.getAll(key).map(Number).filter((n) => Number.isInteger(n) && n > 0))].sort((a, b) => a - b);
  const tags = (key: string) => [...new Set(params.getAll(key).map((t) => t.trim()).filter(Boolean))].sort();
  const games = (mode: FilterItem['mode'], list: number[]): FilterItem[] => list.map((id) => ({ kind: 'game', id, name: gameName(id), mode }));
  const tagItems = (mode: FilterItem['mode'], list: string[]): FilterItem[] => list.map((name) => ({ kind: 'tag', name, mode }));
  const include = ids('games');
  const exclude = ids('excludeGames').filter((id) => !include.includes(id));
  const includeTags = tags('tags');
  const excludeTags = tags('excludeTags').filter((t) => !includeTags.includes(t));
  const gameItems = [...games('include', include), ...games('exclude', exclude)].sort((a, b) => (a.kind === 'game' && b.kind === 'game' ? a.id - b.id : 0));
  const tagList = [...tagItems('include', includeTags), ...tagItems('exclude', excludeTags)].sort((a, b) => a.name.localeCompare(b.name, 'cs'));
  return [...gameItems, ...tagList];
}
