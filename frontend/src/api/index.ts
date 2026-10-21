import { del, get, patch, post, put } from './client';
import type { Game, GameListQuery, GameWithStats, Player, GameInput, SessionInfo, Me, Party, PartyDetail, PartyRole, Play, PlayDefaults, PlayInput, Stats } from './types';

/** Sestaví `?a=1&b=2`; prázdné hodnoty (undefined, null, '') vynechá. */
export function queryString(params: object): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') search.set(key, String(value));
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
  game: (id: number) => get<GameWithStats>(`/games/${id}`),
  sessions: (partyId: number) => get<SessionInfo[]>(`/parties/${partyId}/sessions`),
  createGame: (partyId: number, input: GameInput) => post<Game>(`/parties/${partyId}/games`, input),
  updateGame: (id: number, input: GameInput) => put<Game>(`/games/${id}`, input),
  deleteGame: (id: number) => del(`/games/${id}`),

  plays: (partyId: number, limit = 50, offset = 0, gameId?: number) =>
    get<Play[]>(`/parties/${partyId}/plays${queryString({ limit, offset, gameId })}`),
  playDefaults: (partyId: number, startedAt: string, endedAt: string) =>
    get<PlayDefaults>(`/parties/${partyId}/plays/defaults${queryString({ startedAt, endedAt })}`),
  tags: (partyId: number) => get<string[]>(`/parties/${partyId}/tags`),
  play: (id: number) => get<Play>(`/plays/${id}`),
  createPlay: (partyId: number, input: PlayInput) => post<Play>(`/parties/${partyId}/plays`, input),
  updatePlay: (id: number, input: PlayInput) => put<Play>(`/plays/${id}`, input),
  deletePlay: (id: number) => del(`/plays/${id}`),

  stats: (partyId: number) => get<Stats>(`/parties/${partyId}/stats`),
};
