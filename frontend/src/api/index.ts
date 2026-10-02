import { del, get, patch, post, put } from './client';
import type { Game, GameInput, Me, Party, PartyDetail, PartyRole, Play, PlayInput, Stats } from './types';

export const api = {
  me: () => get<Me>('/me'),
  setNickname: (nickname: string) => patch<Me>('/me', { nickname }),
  logout: () => post<void>('/auth/logout'),

  parties: () => get<Party[]>('/parties'),
  party: (id: number) => get<PartyDetail>(`/parties/${id}`),
  createParty: (name: string) => post<PartyDetail>('/parties', { name }),
  renameParty: (id: number, name: string) => patch<PartyDetail>(`/parties/${id}`, { name }),
  invite: (id: number) => post<{ token: string; expiresAt: string }>(`/parties/${id}/invitations`),
  acceptInvitation: (token: string) => post<PartyDetail>(`/invitations/${token}/accept`),
  changeRole: (partyId: number, userId: number, role: PartyRole) => patch<PartyDetail>(`/parties/${partyId}/members/${userId}`, { role }),
  removeMember: (partyId: number, userId: number) => del(`/parties/${partyId}/members/${userId}`),

  games: (partyId: number) => get<Game[]>(`/parties/${partyId}/games`),
  game: (id: number) => get<Game>(`/games/${id}`),
  createGame: (partyId: number, input: GameInput) => post<Game>(`/parties/${partyId}/games`, input),
  updateGame: (id: number, input: GameInput) => put<Game>(`/games/${id}`, input),
  deleteGame: (id: number) => del(`/games/${id}`),

  plays: (partyId: number, limit = 50, offset = 0) => get<Play[]>(`/parties/${partyId}/plays?limit=${limit}&offset=${offset}`),
  createPlay: (partyId: number, input: PlayInput) => post<Play>(`/parties/${partyId}/plays`, input),
  deletePlay: (id: number) => del(`/plays/${id}`),

  stats: (partyId: number) => get<Stats>(`/parties/${partyId}/stats`),
};
