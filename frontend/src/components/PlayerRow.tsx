import type { Game } from '../api/types';
import type { PlayerDraft } from '../lib/playForm';
import { Icon } from './Icon';

interface Props {
  member: { id: number; name: string };
  game: Game;
  /** Rozpracovaný údaj hráče v zápisu; `undefined` = hráč v zápisu nehraje (nezaškrtnutý). */
  player: PlayerDraft | undefined;
  /** Vlastní hráč hry (kooperativní hry). */
  isGamePlayer: boolean;
  scored: boolean;
  /** Je hráč právě vítěz (u her se skóre i podle skóre, dokud vítěze uživatel nezvolí ručně). */
  winner: boolean;
  onToggle: () => void;
  onPatch: (changes: Partial<PlayerDraft>) => void;
  onToggleWinner: () => void;
  onToggleStarting: () => void;
}

/** Řádek hráče ve formuláři zápisu: zaškrtnutí, skóre/pořadí, postava, vítěz a začínající. */
export function PlayerRow({ member, game, player, isGamePlayer, scored, winner, onToggle, onPatch, onToggleWinner, onToggleStarting }: Props) {
  return (
    <div className={`player${player ? ' selected' : ''}`}>
      <label className="check">
        <input type="checkbox" checked={!!player} onChange={onToggle} />
        <span className="player-name">
          {member.name}
          {isGamePlayer && <span className="muted hint"> hráč hry</span>}
        </span>
      </label>
      {player && scored && (
        <input inputMode="decimal" aria-label={`Skóre ${member.name}`} placeholder="skóre" value={player.score} onChange={(e) => onPatch({ score: e.target.value })} />
      )}
      {player && game.scoreType === 'rank_order' && (
        <input type="number" min={1} aria-label={`Pořadí ${member.name}`} placeholder="pořadí" value={player.rank} onChange={(e) => onPatch({ rank: e.target.value })} />
      )}
      {player && (
        <div className="chips toggles">
          {game.scoreType !== 'rank_order' && (
            <button type="button" className="chip" aria-pressed={winner} aria-label={`Vítěz ${member.name}`} onClick={onToggleWinner}>
              <Icon name="workspace_premium" /> Vítěz
            </button>
          )}
          <button type="button" className="chip" aria-pressed={player.starting} aria-label={`Začíná ${member.name}`} onClick={onToggleStarting}>
            <Icon name="looks_one" /> Začíná
          </button>
        </div>
      )}
      {player && game.characters.length > 0 && (
        <select className="character" aria-label={`Postava ${member.name}`} value={player.characterId ?? ''} onChange={(e) => onPatch({ characterId: e.target.value ? Number(e.target.value) : null })}>
          <option value="">— postava —</option>
          {game.characters.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      )}
    </div>
  );
}
