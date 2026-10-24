import { del, get, patch, post, put } from './client';
import type { BggPreparedGame, BggSearch, Game, GameListQuery, GameWithStats, NamedItemInput, Player, GameInput, SessionInfo, Me, Party, PartyDetail, PartyRole, MovedPlay, Play, PlayDefaults, PlayInput, Stats, StatsQuery } from './types';

/** Sestaví `?a=1&b=2`; prázdné hodnoty (undefined, null, '') vynechá, pole jde jako `a[]=1&a[]=2`. */
export function queryString(params: object): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (Array.isArray(value)) value.forEach((v) => search.append(`${key}[]`, String(v)));
    else if (value !== undefined && value !== null && value !== '') search.set(key, String(value));
  }
  const text = search.toString();
  return text ? `?${text}` : '';
}

export const api = {
  me: () => get<Me>('/me'),
  setNickname: (nickname: string) => patch<Me>('/me', { nickname }),
  logout: () => post<void>('/auth/logout'),

  parties: () => get<Party[]>('/parties'),
  party: (id: number) => get<PartyDetail>(`/parties/${id}`),
  createParty: (name: string) => post<PartyDetail>('/parties', { name }),
  updateParty: (id: number, input: { name: string; shortName: string }) => patch<PartyDetail>(`/parties/${id}`, input),
  /** S `guestId` je pozvánka pro hosta: po přijetí se jeho zápisy převedou na účet pozvaného. */
  invite: (id: number, guestId?: number) => post<{ token: string; expiresAt: string }>(`/parties/${id}/invitations`, guestId === undefined ? undefined : { guestId }),
  /** Admin propojí hosta s členem: zápisy hosta přejdou na člena a host zmizí. */
  linkGuest: (playerId: number, userId: number) => post<Player>(`/players/${playerId}/link-member`, { userId }),
  acceptInvitation: (token: string) => post<PartyDetail>(`/invitations/${token}/accept`),
  changeRole: (partyId: number, userId: number, role: PartyRole) => patch<PartyDetail>(`/parties/${partyId}/members/${userId}`, { role }),
  removeMember: (partyId: number, userId: number) => del(`/parties/${partyId}/members/${userId}`),

  players: (partyId: number) => get<Player[]>(`/parties/${partyId}/players`),
  updatePlayer: (id: number, changes: { name?: string; visible?: boolean }) => patch<Player>(`/players/${id}`, changes),
  deletePlayer: (id: number) => del(`/players/${id}`),
  createGuest: (partyId: number, name: string) => post<Player>(`/parties/${partyId}/players`, { name }),

  games: (partyId: number, query: GameListQuery = {}) => get<GameWithStats[]>(`/parties/${partyId}/games${queryString(query)}`),
  /** Hra v partě (sdílená hra + statistiky a vlastní hráči hry té party). */
  game: (partyId: number, id: number) => get<GameWithStats>(`/parties/${partyId}/games/${id}`),
  sessions: (partyId: number) => get<SessionInfo[]>(`/parties/${partyId}/sessions`),
  createGame: (partyId: number, input: GameInput) => post<Game>(`/parties/${partyId}/games`, input),
  /** Nahraje už oříznutý čtvercový obrázek hry; vrací adresu pro `imageUrl`. */
  uploadGameImage: (partyId: number, image: Blob) => {
    const form = new FormData();
    form.append('file', image, image.type === 'image/webp' ? 'cover.webp' : 'cover.jpg');
    return post<{ url: string }>(`/parties/${partyId}/game-images`, form);
  },
  /** Hledání hry pro přidání: hry party a katalogu, pak cache BGG, pak živé BGG. */
  bggSearch: (partyId: number, q: string) => get<BggSearch>(`/parties/${partyId}/game-search${queryString({ q })}`),
  bggImport: (partyId: number, bggId: number) => post<BggPreparedGame>(`/parties/${partyId}/bgg/import`, { bggId }),
  /** Zařadí hru z katalogu do party (bez zakládání nové hry). */
  linkGame: (partyId: number, gameId: number) => post<Game>(`/parties/${partyId}/game-links`, { gameId }),
  /** Odebere hru z party; hra zůstává v katalogu. */
  unlinkGame: (partyId: number, gameId: number) => del(`/parties/${partyId}/games/${gameId}`),
  /** Vlastní hráči hry v partě. */
  setGamePlayers: (partyId: number, gameId: number, players: NamedItemInput[]) => put<Game>(`/parties/${partyId}/games/${gameId}/players`, { players }),
  /** Úprava sdílených údajů hry; smí jen správce her. */
  updateGame: (id: number, input: GameInput) => put<Game>(`/games/${id}`, input),
  /** Smazání hry z katalogu; smí jen správce her a jen hru bez zápisů. */
  deleteGame: (id: number) => del(`/games/${id}`),

  plays: (partyId: number, limit = 50, offset = 0, gameId?: number) =>
    get<Play[]>(`/parties/${partyId}/plays${queryString({ limit, offset, gameId })}`),
  playDefaults: (partyId: number, startedAt: string, endedAt: string) =>
    get<PlayDefaults>(`/parties/${partyId}/plays/defaults${queryString({ startedAt, endedAt })}`),
  tags: (partyId: number) => get<string[]>(`/parties/${partyId}/tags`),
  /** Všechny tagy her v katalogu (našeptávání ve formuláři hry). */
  gameTags: () => get<string[]>('/game-tags'),
  /** Tagy her, které má aspoň jedna hra zařazená v partě (filtr statistik). */
  partyGameTags: (partyId: number) => get<string[]>(`/parties/${partyId}/game-tags`),
  play: (id: number) => get<Play>(`/plays/${id}`),
  createPlay: (partyId: number, input: PlayInput) => post<Play>(`/parties/${partyId}/plays`, input),
  updatePlay: (id: number, input: PlayInput) => put<Play>(`/plays/${id}`, input),
  deletePlay: (id: number) => del(`/plays/${id}`),
  /** Správce přesune zápis do jiné party, kde je taky správce; hráči se převedou, chybějící vzniknou jako hosté. */
  movePlay: (id: number, partyId: number) => post<MovedPlay>(`/plays/${id}/move`, { partyId }),

  /** Statistiky; `tags`/`games` = jen vybrané hry nebo hry s aspoň jedním z tagů, `excludeTags`/`excludeGames` = bez nich. */
  stats: (partyId: number, filter: StatsQuery = {}) => get<Stats>(`/parties/${partyId}/stats${queryString(filter)}`),
};

/** Obrázek z BGG CDN přes náš server (stejný origin, takže ho jde načíst do canvasu při ořezu). */
export const bggImageProxyUrl = (cdnUrl: string) => `/api/bgg/image${queryString({ url: cdnUrl })}`;
