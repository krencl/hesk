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

export interface Character {
  id: number;
  name: string;
}

export interface Game {
  id: number;
  name: string;
  /** Zkrácený název (statistiky); bez něj se použije celý. */
  shortName: string | null;
  scoreType: ScoreType;
  imageUrl: string | null;
  description: string | null;
  cheatSheet: string | null;
  addons: Addon[];
  characters: Character[];
  /** Vlastní hráč hry (kooperativní hry s „protihráčem“). */
  player: { id: number; name: string } | null;
}

export type GameSort = 'activity' | 'name';

export interface GameListQuery {
  q?: string;
  sort?: GameSort;
  sessionNumber?: number | null;
  limit?: number;
  offset?: number;
}

export interface LastPlay {
  id: number;
  startedAt: string;
  sessionNumber: number;
  winners: string[];
  winnerScore: number | null;
}

/** Hra včetně statistik ze seznamu/detailu (poslední zápis a počet zápisů). */
export interface GameWithStats extends Game {
  playCount: number;
  lastPlay: LastPlay | null;
}

export interface SessionInfo {
  id: number;
  number: number;
  startedAt: string;
  endedAt: string;
}

/** Rozšíření nebo postava při ukládání hry: s `id` se přejmenuje, bez něj vznikne nová. */
export interface NamedItemInput {
  id?: number;
  name: string;
}

export interface GameInput {
  name: string;
  shortName: string | null;
  scoreType: ScoreType;
  imageUrl: string | null;
  description: string | null;
  cheatSheet: string | null;
  addons: NamedItemInput[];
  characters: NamedItemInput[];
  playerId: number | null;
}

export interface Player {
  id: number;
  name: string;
  hasAccount: boolean;
}

export interface PlayResult {
  playerId: number;
  name: string;
  score: number | null;
  rank: number;
  winner: boolean;
  /** Hráč, který ve hře začínal. */
  starting: boolean;
  /** Postava, kterou hráč hrál (jen u her s postavami). */
  character: Character | null;
}

export interface Play {
  id: number;
  game: { id: number; name: string; imageUrl: string | null; scoreType: ScoreType };
  sessionId: number;
  sessionNumber: number;
  startedAt: string;
  endedAt: string;
  notes: string | null;
  addons: Addon[];
  tags: string[];
  results: PlayResult[];
}

export interface PlayerInput {
  playerId: number;
  score?: number | null;
  rank?: number | null;
  /** U her se skóre jen ručně určený vítěz (jinak se vítězové dopočítají ze skóre). */
  winner?: boolean | null;
  starting?: boolean;
  characterId?: number | null;
}

export interface PlayInput {
  gameId: number;
  startedAt: string;
  endedAt: string;
  players: PlayerInput[];
  addonIds: number[];
  tags: string[];
  notes: string | null;
  /** Ručně zadané číslo sezení; bez něj ho určí backend podle 24h pravidla. */
  sessionNumber?: number | null;
}

/** Výchozí hodnoty nového zápisu pro daný čas. */
export interface PlayDefaults {
  sessionNumber: number;
  /** Hráči posledního zápisu stejného sezení; `null`, když sezení ještě neexistuje (první hra dne). */
  playerIds: number[] | null;
  /** Tagy posledního zápisu stejného sezení (prázdné, když sezení ještě není). */
  tags: string[];
}

export interface Stats {
  wins: { playerId: number; name: string; wins: number; plays: number }[];
  topGames: { gameId: number; name: string; shortName: string | null; plays: number }[];
}
