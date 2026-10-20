import { NavLink } from 'react-router-dom';
import type { PartyRole } from '../api/types';
import { PageTitle } from './PageTitle';

/**
 * Záhlaví stránek správy party: název party a přepínač sekcí. Každá sekce je samostatná stránka s vlastní adresou:
 * členové (`/parties/:id`), hosté (`/parties/:id/guests`) a nastavení (`/parties/:id/edit`, jen admin).
 */
export function PartyHeader({ party }: { party: { id: number; name: string; role: PartyRole } }) {
  return (
    <>
      <PageTitle label="Parta">{party.name}</PageTitle>
      <nav className="tabs" aria-label="Správa party">
        <NavLink to={`/parties/${party.id}`} end>Členové</NavLink>
        <NavLink to={`/parties/${party.id}/guests`}>Hosté</NavLink>
        {party.role === 'admin' && <NavLink to={`/parties/${party.id}/edit`}>Nastavení</NavLink>}
      </nav>
    </>
  );
}
