import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { api } from '../api';
import { qk } from '../api/queryKeys';
import { type FilterItem, toStatsQuery } from '../lib/statsFilter';
import { BarChart } from './BarChart';

const TOP_PLAYERS = 4;

/**
 * Kompaktní přehled nejčastějších výherců hry nad jejími zápisy: jen první čtyři a odkaz na statistiky party
 * s touto hrou ve filtru. Filtr je stejný jako ve statistikách (`toStatsQuery`), takže se dotaz sdílí z cache.
 * Bez výher (nebo když se statistiky nenačtou) se nezobrazí nic.
 */
export function GameTopWinners({ partyId, gameId, gameName }: { partyId: number; gameId: number; gameName: string }) {
  const queryClient = useQueryClient();
  const filter = toStatsQuery([{ kind: 'game', id: gameId, name: gameName, mode: 'include' } satisfies FilterItem]);
  const { data } = useQuery({ queryKey: qk.stats(partyId, filter), queryFn: () => api.stats(partyId, filter) });
  const top = (data?.wins ?? []).filter((w) => w.wins > 0).slice(0, TOP_PLAYERS);
  if (top.length === 0) return null;

  return (
    <div className="pad top-winners">
      <h3>Nejčastější výherci</h3>
      <BarChart items={top.map((w) => ({ key: w.playerId, label: w.name, value: w.wins }))} />
      <Link
        className="btn small outline"
        to={`/parties/${partyId}/stats?games=${gameId}`}
        onClick={() => queryClient.setQueryData(qk.statsGameName(partyId, gameId), gameName)} // štítek filtru hned ukáže název, ne „Hra #id“
      >Zobrazit další statistiky</Link>
    </div>
  );
}
