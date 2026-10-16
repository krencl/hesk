import { GameImage } from './GameImage';

/** Malý náhled a název hry nad detailem hry, taháčkem i formuláři zápisu; stejná výška jako PageTitle. */
export function GameHeader({ name, imageUrl }: { name: string; imageUrl: string | null }) {
  return (
    <div className="page-head with-image">
      <GameImage url={imageUrl} small />
      <h2 className="page-title">{name}</h2>
    </div>
  );
}
