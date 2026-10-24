export type ScoreType = 'most_points' | 'fewest_points' | 'rank_order' | 'winner_only';
/** Admin spravuje partu, zapisovač přidává a upravuje zápisy, člen je jen čte. */
export type PartyRole = 'admin' | 'writer' | 'member';

export interface Me {
  id: number;
  nickname: string | null;
  email: string;
  hasNickname: boolean;
  /** Role účtu: vždy `ROLE_USER`, správce sdíleného katalogu her má navíc `ROLE_GAMES_ADMIN`. */
  roles: string[];
}

export interface Party {
  id: number;
  name: string;
  /** Zkratka do kulatého tlačítka v hlavičce (1–3 znaky); při založení se vygeneruje z názvu. */
  shortName: string;
  role: PartyRole;
}

export interface Member {
  userId: number;
  nickname: string | null;
  role: PartyRole;
}

export interface PartyDetail extends Party {
  members: Member[];
  /** Jen v odpovědi na přijetí pozvánky pro hosta: jméno hosta, který se propojil s novým členem. */
  linkedGuest?: string | null;
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
  /** Id hry na BoardGameGeek; null = vlastní hra. */
  bggId: number | null;
  description: string | null;
  cheatSheet: string | null;
  addons: Addon[];
  characters: Character[];
  /** Vlastní hráči hry (kooperativní hry s „protihráčem“); jsou to hráči party (typicky hosté). */
  players: { id: number; name: string }[];
  /** Tagy hry ve sdíleném katalogu (rychlovka, strategická, …). */
  tags: string[];
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
  bggId: number | null;
  description: string | null;
  cheatSheet: string | null;
  addons: NamedItemInput[];
  characters: NamedItemInput[];
  /** Hráči hry: `id` je id hráče party (přejmenuje se), bez něj se spáruje podle jména nebo vznikne host. */
  players: NamedItemInput[];
  tags: string[];
}

export interface Player {
  id: number;
  name: string;
  hasAccount: boolean;
  /** Host s `false` se nenabízí ve výběru hráčů při zápisu (už zapsaní hráči zůstávají vidět). */
  visible: boolean;
  /** Počet zápisů, ve kterých hráč hrál (jen v seznamu hráčů party). */
  playCount: number;
  /** Hráč některé hry party (protivník v kooperativní hře): patří hře, ne správě hostů (jen v seznamu hráčů party). */
  gamePlayer: boolean;
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

/** Odpověď na přesun zápisu: zápis, cílová parta a jména hostů, kteří v ní kvůli přesunu vznikli. */
export interface MovedPlay {
  play: Play;
  party: { id: number; name: string };
  createdGuests: string[];
}

/** Co se při přesunu zápisu stane s jedním hráčem (cílové jméno, člen/host před a po, zda se v cílové partě zakládá). */
export interface MovePreviewPlayer {
  playerId: number;
  name: string;
  targetName: string;
  fromMember: boolean;
  toMember: boolean;
  created: boolean;
}

/** Náhled přesunu zápisu do jiné party. */
export interface MovePreview {
  party: { id: number; name: string };
  /** Hra v cílové partě ještě není, přesunem se do ní zařadí. */
  gameAdded: boolean;
  players: MovePreviewPlayer[];
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

/** Filtr statistik (hry a tagy zahrnuté / vyloučené); posílá se jako opakované parametry dotazu. */
export interface StatsQuery {
  tags?: string[];
  excludeTags?: string[];
  games?: number[];
  excludeGames?: number[];
}

export interface Stats {
  wins: { playerId: number; name: string; wins: number; plays: number }[];
  topGames: { gameId: number; name: string; shortName: string | null; plays: number }[];
  /** Kolikrát byl hráč označen jako začínající (jen u her, kde se začínající eviduje). */
  starters: { playerId: number; name: string; starts: number }[];
  /** Nejčastější tagy zápisů (uživatel do nich dává, co chce: místo, příležitost, …). */
  playTags: { name: string; plays: number }[];
}

/** Výsledek hledání při přidávání hry: `party` = hra už v partě, `catalog` = hra z katalogu, kterou parta nemá (stačí ji zařadit podle `gameId`), `cache` = lokální cache BGG, `bgg` = živé BGG API. */
export interface BggSearchResult {
  source: 'party' | 'catalog' | 'cache' | 'bgg';
  name: string;
  year: number | null;
  bggId: number | null;
  gameId: number | null;
  /** Obálka k zobrazení: lokální (`/uploads/…`) nebo z BGG CDN; null = žádná. */
  imageUrl: string | null;
}

export interface BggSearch {
  results: BggSearchResult[];
  /** Jestli se použilo živé BGG: `used`, `skipped` (stačily lokální zdroje) nebo `unavailable` (nedostupné). */
  bgg: 'used' | 'skipped' | 'unavailable';
}

/** Hra z BGG připravená pro formulář: `bggImageUrl` je originál na BGG CDN (lokálně se uloží až výřez). */
export interface BggPreparedGame {
  bggId: number;
  name: string;
  bggImageUrl: string | null;
}
