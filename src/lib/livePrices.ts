/**
 * Online-class prices live in the LMS database (admin › Produk online). The
 * site is static, so each build reads them from the API once and writes them
 * over the defaults in content.config.ts. "Terbitkan ulang situs" in the admin
 * runs that build after a price change.
 *
 * When the API cannot be reached the build still succeeds with the printed
 * defaults, and says so in the log: a down API must never take the site down.
 * The checkout always charges the database price either way.
 */

interface PricedCourse {
  price: number;
  priceDisplay: string;
  strikePriceDisplay: string;
  /** "hemat RpX" next to the price, where a page shows one. */
  savingsDisplay?: string;
}

export const rupiah = (n: number) => 'Rp' + Math.round(n).toLocaleString('id-ID');

export async function applyLivePrices(courses: Record<string, PricedCourse>): Promise<void> {
  const base = (import.meta.env.PUBLIC_API_BASE_URL ?? import.meta.env.PUBLIC_LEAD_API_BASE_URL ?? '').trim().replace(/\/$/, '');
  if (!base) return;

  await Promise.all(
    Object.entries(courses).map(async ([slug, course]) => {
      try {
        const res = await fetch(`${base}/api/checkout/courses/${encodeURIComponent(slug)}`, {
          headers: { Accept: 'application/json' },
          signal: AbortSignal.timeout(5000),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = (await res.json()) as { course?: { price?: unknown; strike_price?: unknown } };
        const price = data.course?.price;
        const strike = data.course?.strike_price;
        if (typeof price !== 'number' || !Number.isInteger(price) || price <= 0) throw new Error('harga tidak valid');

        course.price = price;
        course.priceDisplay = rupiah(price);
        if (typeof strike === 'number' && strike > price) {
          course.strikePriceDisplay = rupiah(strike);
          if (course.savingsDisplay !== undefined) course.savingsDisplay = rupiah(strike - price);
        }
      } catch (error) {
        console.warn(`[harga] ${slug}: memakai harga bawaan ${course.priceDisplay} (${error instanceof Error ? error.message : error})`);
      }
    }),
  );
}
