/** Výřez v pixelech původního obrázku (tak ho vrací `react-easy-crop`). */
export interface CropArea {
  x: number;
  y: number;
  width: number;
  height: number;
}

/** Strana nahrávaného čtverce v px; pro náhledy ve stovkách pixelů to s rezervou stačí a soubor má desítky kB. */
export const COVER_SIZE = 600;

const loadImage = (src: string): Promise<HTMLImageElement> =>
  new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error('Obrázek se nepodařilo načíst.'));
    img.src = src;
  });

/** Vyřízne zvolený výřez ze zdroje (object URL) a zmenší ho na čtverec `size` × `size` a zakóduje jako WebP (jediný formát, který aplikace ukládá). */
export async function cropToBlob(src: string, area: CropArea, size = COVER_SIZE): Promise<Blob> {
  const img = await loadImage(src);
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext('2d');
  if (!ctx) throw new Error('Úprava obrázku není v tomto prohlížeči dostupná.');
  // průhledné části (PNG) by jinak zůstaly průhledné a v seznamu by prosvítalo pozadí
  ctx.fillStyle = '#fff';
  ctx.fillRect(0, 0, size, size);
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(img, area.x, area.y, area.width, area.height, 0, 0, size, size);
  const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/webp', 0.85));
  // prohlížeč, který WebP neumí kódovat, vrací tiše PNG
  if (!blob || blob.type !== 'image/webp') throw new Error('Tento prohlížeč neumí obrázek převést do formátu WebP. Zkus jiný prohlížeč.');
  return blob;
}
