import { Link, Outlet } from 'react-router-dom';
import { useActiveParty } from '../AppContext';
import { canWrite } from '../lib/roles';
import { Actions } from './Actions';

/** Stránky pro správu her party: smí na ně zapisovač a správce, obyčejný člen hry jen čte. */
export function RequireWriter() {
  const party = useActiveParty();
  if (canWrite(party.role)) return <Outlet />;
  return (
    <section className="page pad">
      <p className="empty">Hry party spravuje jen zapisovač nebo správce.</p>
      <Actions><Link className="btn outline-secondary" to="/games">Zpět na hry</Link></Actions>
    </section>
  );
}
