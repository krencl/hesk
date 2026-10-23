import type { ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { GameImage } from './GameImage';

interface Props {
  imageUrl: string | null;
  name: string;
  /** Řádky pod názvem (`secondary`). */
  children?: ReactNode;
  /** Odkaz, nebo tlačítko s `onClick`; bez obojího je řádek neaktivní (šedý, nereaguje). */
  to?: string;
  onClick?: () => void;
  disabled?: boolean;
  /** Tlačítka vpravo od řádku (vedle něj, ne uvnitř, ať nejsou tlačítka v tlačítku). */
  actions?: ReactNode;
}

/** Řádek se hrou (obálka, název, podtitul): společný seznamu her, správě her i hledání při přidání hry. */
export function GameListItem({ imageUrl, name, children, to, onClick, disabled, actions }: Props) {
  const content = (
    <>
      <GameImage url={imageUrl} />
      <span className="list-item-body">
        <strong className="h5">{name}</strong>
        {children}
      </span>
    </>
  );
  return (
    <li className={actions ? 'with-actions' : undefined}>
      {to ? (
        <Link to={to} className="list-item">{content}</Link>
      ) : onClick ? (
        <button type="button" className="list-item" disabled={disabled} onClick={onClick}>{content}</button>
      ) : (
        <div className="list-item inactive">{content}</div>
      )}
      {actions && <span className="list-item-actions">{actions}</span>}
    </li>
  );
}
