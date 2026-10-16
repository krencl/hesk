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

export function formatScore(score: number): string {
  return Number.isInteger(score) ? String(score) : score.toLocaleString('cs-CZ', { maximumFractionDigits: 2 });
}
