export type ScoreType = 'most_points' | 'fewest_points' | 'rank_order' | 'winner_only';
export type PartyRole = 'admin' | 'member';

export interface Me {
  id: number;
  nickname: string | null;
  email: string;
  hasNickname: boolean;
}

export interface Party {
  id: number;
  name: string;
  role: PartyRole;
}

export interface Member {
  userId: number;
  nickname: string | null;
  role: PartyRole;
}

export interface PartyDetail extends Party {
  members: Member[];
}

export interface Addon {
  id: number;
  name: string;
}

export interface Game {
  id: number;
  name: string;
  scoreType: ScoreType;
  imageUrl: string | null;
  description: string | null;
  cheatSheet: string | null;
  addons: Addon[];
}

export interface GameInput {
  name: string;
  scoreType: ScoreType;
  imageUrl: string | null;
  description: string | null;
  cheatSheet: string | null;
  addons: string[];
}

export interface PlayResult {
  userId: number;
  nickname: string | null;
  score: number | null;
  rank: number;
  winner: boolean;
}

export interface Play {
  id: number;
  game: { id: number; name: string; imageUrl: string | null; scoreType: ScoreType };
  sessionId: number;
  startedAt: string;
  endedAt: string;
  place: string | null;
  notes: string | null;
  addons: Addon[];
  tags: string[];
  results: PlayResult[];
}

export interface PlayerInput {
  userId: number;
  score?: number | null;
  rank?: number | null;
  winner?: boolean | null;
}

export interface PlayInput {
  gameId: number;
  startedAt: string;
  endedAt: string;
  players: PlayerInput[];
  addonIds: number[];
  place: string | null;
  tags: string[];
  notes: string | null;
}

export interface Stats {
  wins: { userId: number; nickname: string | null; wins: number; plays: number }[];
  topGames: { gameId: number; name: string; plays: number }[];
}
