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

import { parseRupiah, rupiah, strike } from './pricing';

interface PricedCourse {
  price: number;
  priceDisplay: string;
  strikePriceDisplay: string;
  /** Students who hold the class now, for "bergabung dengan N siswa lain". */
  students?: number;
  reviews?: CourseReviews;
}

export interface CourseReview {
  /** First name only: what the student agreed to. */
  name: string;
  rating: number;
  body: string;
  date: string | null;
}

/** Every rating makes the score; items are the reviews the student allowed and the team chose. */
export interface CourseReviews {
  average: number | null;
  count: number;
  items: CourseReview[];
}

/** Below this the average is not shown: "5,0 dari 1 penilaian" proves nothing. */
export const MIN_RATINGS = 3;

/** The API answer, checked field by field; anything malformed becomes null. */
export function parseReviews(value: unknown): CourseReviews | null {
  if (!value || typeof value !== 'object') return null;
  const v = value as Record<string, unknown>;
  const count = typeof v.count === 'number' && Number.isInteger(v.count) && v.count >= 0 ? v.count : 0;
  const average = typeof v.average === 'number' && v.average >= 1 && v.average <= 5 ? v.average : null;
  const items = (Array.isArray(v.items) ? v.items : []).flatMap((item): CourseReview[] => {
    const i = item as Record<string, unknown>;
    if (typeof i?.name !== 'string' || typeof i.body !== 'string' || !i.body.trim()) return [];
    const rating = typeof i.rating === 'number' ? Math.min(5, Math.max(1, Math.round(i.rating))) : 5;
    return [{ name: i.name, rating, body: i.body, date: typeof i.date === 'string' ? i.date : null }];
  });
  return { average, count, items };
}

/** Below this the classmate line is not shown at all. */
export const MIN_CLASSMATES = 10;

/**
 * Attributes for an element whose printed prices belong to one online class.
 * Every price, strike price and saving of that class inside the element (text,
 * links, labels) is refreshed in the browser. The page body takes them for the
 * page's own class; a block about another class carries its own.
 */
export function livePriceAttrs(slug: string, course: { price: number; strikePriceDisplay: string }) {
  return { 'data-live-price': slug, 'data-live-built': `${course.price}/${parseRupiah(course.strikePriceDisplay)}` };
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
        const data = (await res.json()) as { course?: { price?: unknown; strike_price?: unknown; students?: unknown }; reviews?: unknown };
        const reviews = parseReviews(data.reviews);
        if (reviews) course.reviews = reviews;
        const price = data.course?.price;
        const strikePrice = data.course?.strike_price;
        const students = data.course?.students;
        if (typeof students === 'number' && Number.isInteger(students) && students >= 0) course.students = students;
        if (typeof price !== 'number' || !Number.isInteger(price) || price <= 0) throw new Error('harga tidak valid');

        // A missing or lower strike price keeps the printed one, as the browser refresh does.
        const strikeNow = strike(price, typeof strikePrice === 'number' ? strikePrice : null, parseRupiah(course.strikePriceDisplay));
        course.price = price;
        course.priceDisplay = rupiah(price);
        if (strikeNow) course.strikePriceDisplay = rupiah(strikeNow);
      } catch (error) {
        console.warn(`[harga] ${slug}: memakai harga bawaan ${course.priceDisplay} (${error instanceof Error ? error.message : error})`);
      }
    }),
  );
}
