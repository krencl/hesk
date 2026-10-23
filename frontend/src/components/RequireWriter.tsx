import { Outlet } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { canWrite } from '../lib/roles';
import { EmptyState } from './PageState';

/** Stránky pro správu her party: smí na ně zapisovač a správce, obyčejný člen hry jen čte. */
export function RequireWriter() {
  const party = useActiveParty();
  if (canWrite(party.role)) return <Outlet />;
  return <EmptyState message="Hry party spravuje jen zapisovač nebo správce." backTo="/" backLabel="Zpět na hry" />;
}
