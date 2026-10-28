import { useQuery } from '@tanstack/react-query';
import { useEffect } from 'react';
import { api } from '../api';
import { qk } from '../api/queryKeys';

/** Na vývojovém prostředí (`APP_ENV=dev`) zelená varianta loga, ikon (favicon, ikona pro iOS) a tlačítka menu (`html[data-env='dev']` v `styles.css`), ať jde dev snadno odlišit od produkce. */
export function useAppEnv() {
  return useQuery({ queryKey: qk.health(), queryFn: api.health, staleTime: Infinity, retry: false });
}

/** Logo HESK. Dokud prostředí neznáme, logo se nezobrazí (bez probliknutí modrého); při chybě API zůstane modré. */
export function Logo({ className }: { className: string }) {
  const { data, isPending } = useAppEnv();
  const dev = data?.env === 'dev';
  useEffect(() => {
    if (!dev) return;
    document.documentElement.dataset.env = 'dev';
    document.querySelectorAll<HTMLLinkElement>('link[rel~="icon"], link[rel="apple-touch-icon"]').forEach((link) => {
      link.href = link.href.replace(/(?<!-dev)\.png$/, '-dev.png');
    });
  }, [dev]);
  return <img src={`${import.meta.env.BASE_URL}${dev ? 'logo-dev.svg' : 'logo.svg'}`} alt="HESK" className={className} style={isPending ? { visibility: 'hidden' } : undefined} />;
}
