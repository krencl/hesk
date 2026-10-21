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

/** Vyřízne zvolený výřez ze zdroje (object URL) a zmenší ho na čtverec `size` × `size` a zakóduje: jako WebP (formát, který aplikace ukládá), a když ho prohlížeč nekóduje (Safari/iPhone, canvas pak tiše vrací PNG), jako JPEG, který na WebP převede server. */
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
  // Test cesty pro prohlížeče bez WebP (iPhone): v adrese formuláře hry `?coverFormat=jpeg` přeskočí kódování WebP.
  const forceJpeg = new URLSearchParams(window.location.search).get('coverFormat') === 'jpeg';
  const encode = (type: string, quality: number) => new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, type, quality));
  const webp = forceJpeg ? null : await encode('image/webp', 0.85);
  if (webp?.type === 'image/webp') return webp;
  const jpeg = await encode('image/jpeg', 0.9);
  if (!jpeg) throw new Error('Obrázek se nepodařilo zpracovat.');
  return jpeg;
}
