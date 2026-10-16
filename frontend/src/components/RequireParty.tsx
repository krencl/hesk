import { Link, Outlet } from 'react-router-dom';
import { useApp } from '../AppContext';

/** Stránky závislé na aktivní partě; bez ní nabídne její vytvoření. */
export function RequireParty() {
  const { activeParty } = useApp();
  if (!activeParty) {
    return (
      <div className="empty">
        <p>Zatím nejsi v žádné partě.</p>
        <Link className="btn" to="/parties">Vytvořit partu</Link>
      </div>
    );
  }
  return <Outlet />;
}
