import type { Play, PlayResult, ScoreType } from '../api/types';

export const SCORE_TYPE_LABELS: Record<ScoreType, string> = {
  most_points: 'Nejvíc bodů vyhrává',
  fewest_points: 'Nejméně bodů vyhrává',
  rank_order: 'Pořadí bez skóre',
  winner_only: 'Jen vítěz',
};

export const SCORE_TYPES = Object.keys(SCORE_TYPE_LABELS) as ScoreType[];

export function winners(play: Pick<Play, 'results'>): PlayResult[] {
  return play.results.filter((r) => r.winner);
}

/** Text pro seznam zápisů: jména vítězů (případně se skóre prvního). */
export function winnerSummary(play: Pick<Play, 'results'>): string {
  const w = winners(play);
  if (w.length === 0) return '—';
  const names = w.map((r) => r.name).join(', ');
  const score = w[0].score;
  return score === null ? names : `${names} (${formatScore(score)})`;
}

export function formatScore(score: number): string {
  return Number.isInteger(score) ? String(score) : score.toLocaleString('cs-CZ', { maximumFractionDigits: 2 });
}

export interface SessionGroup {
  sessionId: number;
  plays: Play[];
}

/** Seskupí zápisy (již seřazené od nejnovějšího) do seancí; pořadí skupin zachová. */
export function groupBySession(plays: Play[]): SessionGroup[] {
  const groups: SessionGroup[] = [];
  for (const play of plays) {
    const last = groups[groups.length - 1];
    if (last && last.sessionId === play.sessionId) last.plays.push(play);
    else groups.push({ sessionId: play.sessionId, plays: [play] });
  }
  return groups;
}
