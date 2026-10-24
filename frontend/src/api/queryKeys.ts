/**
 * Klíče TanStack Query na jednom místě. Struktura je záměrně „od obecného ke konkrétnímu“, aby šlo zneplatňovat
 * podle předpony: `qk.games(partyId)` zasáhne seznamy her, jejich kola i filtr statistik party; `qk.game(gameId)`
 * (bez party) detail hry ve všech partách.
 */
export const qk = {
  me: () => ['me'] as const,
  parties: () => ['parties'] as const,
  /** Detail party; bez `id` všechny načtené detaily. */
  party: (id?: number) => (id === undefined ? (['party'] as const) : (['party', id] as const)),
  players: (partyId: number) => ['players', partyId] as const,
  /** Návrhy tagů zápisů v partě. */
  tags: (partyId: number) => ['tags', partyId] as const,
  sessions: (partyId: number) => ['sessions', partyId] as const,

  /** Všechno, co se týká seznamů her party (předpona pro zneplatnění). */
  games: (partyId: number) => ['games', partyId] as const,
  gameList: (partyId: number, params: object) => ['games', partyId, 'list', params] as const,
  /** Zápisy jedné hry (stránkované). */
  gamePlays: (partyId: number, gameId: number) => ['games', partyId, 'plays', gameId] as const,
  /** Hledání her ve filtru statistik. */
  statsFilterGames: (partyId: number, query: string) => ['games', partyId, 'statsFilter', query] as const,
  /** Detail hry; bez `partyId` předpona pro všechny party. */
  game: (gameId: number | null | undefined, partyId?: number) => (partyId === undefined ? (['game', gameId] as const) : (['game', gameId, partyId] as const)),

  /** Tagy her v katalogu (našeptávání ve formuláři hry). */
  gameTags: () => ['gameTags'] as const,
  /** Tagy her v dané partě (filtr statistik); bez `partyId` všechny party. */
  partyGameTags: (partyId?: number) => (partyId === undefined ? (['partyGameTags'] as const) : (['partyGameTags', partyId] as const)),

  play: (id: number) => ['play', id] as const,
  /** Náhled přesunu zápisu do jiné party. */
  movePreview: (playId: number, partyId: number) => ['movePreview', playId, partyId] as const,
  /** Výchozí hodnoty nového zápisu; bez časů předpona pro celou partu. */
  playDefaults: (partyId: number, startedIso?: string | null, endedIso?: string | null) =>
    startedIso === undefined ? (['playDefaults', partyId] as const) : (['playDefaults', partyId, startedIso, endedIso] as const),

  stats: (partyId: number, filter?: object) => (filter === undefined ? (['stats', partyId] as const) : (['stats', partyId, filter] as const)),
  statsGameName: (partyId: number, gameId: number) => ['statsGameName', partyId, gameId] as const,
  bggSearch: (partyId: number, query: string) => ['bggSearch', partyId, query] as const,
};
