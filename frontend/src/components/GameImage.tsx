export function GameImage({ url, name }: { url: string | null; name: string }) {
  return url ? (
    <img className="game-image" src={url} alt="" loading="lazy" />
  ) : (
    <div className="game-image placeholder" aria-hidden>{name.slice(0, 1).toUpperCase()}</div>
  );
}
