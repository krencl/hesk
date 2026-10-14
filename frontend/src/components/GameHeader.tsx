import { GameImage } from './GameImage';

/** Malý náhled a název hry nad detailem hry a taháčkem. */
export function GameHeader({ name, imageUrl }: { name: string; imageUrl: string | null }) {
  return (
    <div className="game-header">
      <GameImage url={imageUrl} small />
      <strong className="h5">{name}</strong>
    </div>
  );
}
