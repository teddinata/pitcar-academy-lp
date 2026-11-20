/**
 * Online-class prices live in the LMS database (admin › Produk online). The
 * site is static, so each build reads them from the API once and writes them
 * over the defaults in content.config.ts. The browser then asks the API again
 * on every visit (src/scripts/livePrices.ts) and swaps any price that changed
 * since the build, so a new price shows without republishing. Only meta tags
 * and structured data keep the build-time price until the next build.
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
  /** Students who hold the class now, for "bergabung dengan N siswa lain". */
  students?: number;
}

/** Below this the classmate line is not shown at all. */
export const MIN_CLASSMATES = 10;

export const rupiah = (n: number) => 'Rp' + Math.round(n).toLocaleString('id-ID');
const digits = (text: string) => Number(text.replace(/\D/g, '')) || 0;

/**
 * Attributes for an element whose printed prices belong to one online class.
 * Every price, strike price and saving of that class inside the element (text,
 * links, labels) is refreshed in the browser. The page body takes them for the
 * page's own class; a block about another class carries its own.
 */
export function livePriceAttrs(slug: string, course: { price: number; strikePriceDisplay: string }) {
  return { 'data-live-price': slug, 'data-live-built': `${course.price}/${digits(course.strikePriceDisplay)}` };
}

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
        const data = (await res.json()) as { course?: { price?: unknown; strike_price?: unknown; students?: unknown } };
        const price = data.course?.price;
        const strike = data.course?.strike_price;
        const students = data.course?.students;
        if (typeof students === 'number' && Number.isInteger(students) && students >= 0) course.students = students;
        if (typeof price !== 'number' || !Number.isInteger(price) || price <= 0) throw new Error('harga tidak valid');

        // A missing or lower strike price keeps the printed one, as the browser refresh does.
        const strikeNow = typeof strike === 'number' && strike > price ? strike : digits(course.strikePriceDisplay);
        course.price = price;
        course.priceDisplay = rupiah(price);
        if (strikeNow > 0) course.strikePriceDisplay = rupiah(strikeNow);
        if (course.savingsDisplay !== undefined && strikeNow > price) course.savingsDisplay = rupiah(strikeNow - price);
      } catch (error) {
        console.warn(`[harga] ${slug}: memakai harga bawaan ${course.priceDisplay} (${error instanceof Error ? error.message : error})`);
      }
    }),
  );
}
