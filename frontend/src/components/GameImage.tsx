const PLACEHOLDER = `${import.meta.env.BASE_URL}cover-placeholder.jpg`;

export function GameImage({ url, small = false }: { url: string | null; small?: boolean }) {
  return <img className={`game-image${small ? ' small' : ''}`} src={url ?? PLACEHOLDER} alt="" loading="lazy" />;
}
