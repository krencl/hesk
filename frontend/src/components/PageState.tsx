import { Link } from 'react-router-dom';
import { ApiError } from '../api/client';
import { Actions } from './Actions';
import { ErrorMessage } from './ErrorMessage';
import { ListSkeleton } from './Skeleton';

/** Server odpověděl „neexistuje“ nebo „nemáš přístup“ (u hry, party, zápisu to uživateli říkáme jednou větou). */
export function isNotFound(error: unknown): boolean {
  return error instanceof ApiError && (error.status === 404 || error.status === 403);
}

/** Stránka s jednou větou a tlačítkem zpět: nenalezeno, chybí oprávnění… */
export function EmptyState({ message, backTo, backLabel }: { message: string; backTo: string; backLabel: string }) {
  return (
    <section className="page pad">
      <p className="empty">{message}</p>
      <Actions><Link className="btn outline-secondary" to={backTo}>{backLabel}</Link></Actions>
    </section>
  );
}

/** Stránka, které zatím chybí data: při chybě zpráva přímo na stránce, jinak zástupné řádky (parametry jako `ListSkeleton`). */
export function LoadingOrError({ error, rows, withImage }: { error: unknown; rows?: number; withImage?: boolean }) {
  if (error) return <section className="page pad"><ErrorMessage inline error={error} /></section>;
  return <ListSkeleton rows={rows} withImage={withImage} />;
}

/** Stránky party (detail, hosté, úprava) při neexistující partě nebo bez členství. */
export function PartyNotFound() {
  return <EmptyState message="Tahle parta neexistuje nebo v ní nejsi." backTo="/parties" backLabel="Správa part" />;
}
