import { useQuery } from '@tanstack/react-query';
import { api } from '../api';
import { qk } from '../api/queryKeys';

/** Logo HESK; na vývojovém prostředí (`APP_ENV=dev`) zelené, ať jde dev snadno odlišit od produkce. Dokud prostředí neznáme, logo se nezobrazí (bez probliknutí modrého). */
export function Logo({ className }: { className: string }) {
  const { data, isPending } = useQuery({ queryKey: qk.health(), queryFn: api.health, staleTime: Infinity, retry: false });
  const file = data?.env === 'dev' ? 'logo-dev.svg' : 'logo.svg';
  return <img src={`${import.meta.env.BASE_URL}${file}`} alt="HESK" className={className} style={isPending ? { visibility: 'hidden' } : undefined} />;
}
