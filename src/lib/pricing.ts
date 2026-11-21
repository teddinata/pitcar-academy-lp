/**
 * What an online class costs and what the buyer saves, in one place (see
 * GLOSSARY.md). The build, the browser's live-price refresh and the checkout
 * all read prices through here, so a rule changes once.
 *
 * Pure: no fetch, no DOM. The callers bring the LMS answer and render the result.
 */

export const rupiah = (n: number) => 'Rp' + Math.round(n).toLocaleString('id-ID');

/** "79 ribu" reads cheaper than "Rp79.000"; odd amounts keep the full figure. */
export const ribu = (n: number) => (n >= 1000 && n % 1000 === 0 ? `${(n / 1000).toLocaleString('id-ID')} ribu` : rupiah(n));

/** A printed price ("Rp 499.000", "Rp499.000") back to its number; 0 when there is none. */
export const parseRupiah = (text: string | null | undefined) => Number((text ?? '').replace(/\D/g, '')) || 0;

/**
 * The strike price worth showing next to `price`, or null. One from the LMS
 * counts only above the price; when it is missing or lower, the one printed on
 * the page stands, so an admin who clears it does not wipe the page's.
 */
export function strike(price: number, live: number | null | undefined, printed = 0): number | null {
  if (typeof live === 'number' && live > price) return live;
  return printed > price ? printed : null;
}

/** A class as the LMS sends it (GET /api/checkout/courses/{slug}). */
export interface Priced {
  slug: string;
  title: string;
  price: number;
  strike_price: number | null;
}

/** Another class offered under the order bump, at `offer_price` instead of its own `price`. */
export interface Offer extends Priced {
  offer_price: number;
}

export interface Line {
  slug: string;
  title: string;
  /** Harga normal: the class's strike price when it has one, otherwise what it costs on its own. */
  normal: number;
  /** What this checkout charges for it. */
  price: number;
  saving: number;
}

export interface AddOn extends Line {
  /** What it costs bought later on its own. */
  alone: number;
  chosen: boolean;
}

export interface Quote {
  /** What is charged: the class first, then each chosen add-on. */
  lines: Line[];
  /** Every offer, chosen or not, for the add-on cards. */
  addons: AddOn[];
  normal: number;
  saving: number;
  total: number;
  /** The unchosen add-on that would save the most, to name under the receipt. */
  missed: AddOn | null;
}

function line(item: Priced, charged: number): Line {
  const normal = Math.max(strike(item.price, item.strike_price) ?? item.price, charged);
  return { slug: item.slug, title: item.title, normal, price: charged, saving: normal - charged };
}

/** The receipt for `course` with the add-ons whose slugs are in `chosen`. */
export function quote(course: Priced, offers: Offer[], chosen: string[]): Quote {
  const addons = offers.map((o) => ({ ...line(o, o.offer_price), alone: o.price, chosen: chosen.includes(o.slug) }));
  const lines: Line[] = [line(course, course.price), ...addons.filter((a) => a.chosen)];
  const total = lines.reduce((sum, l) => sum + l.price, 0);
  const normal = lines.reduce((sum, l) => sum + l.normal, 0);
  const missed = addons.filter((a) => !a.chosen && a.saving > 0).reduce<AddOn | null>((best, a) => (!best || a.saving > best.saving ? a : best), null);
  return { lines, addons, normal, saving: normal - total, total, missed };
}

/**
 * Printed strings to swap once the live price is known: price, strike price
 * and saving, each keyed by the string the build printed. Empty when nothing a
 * visitor would see has changed.
 */
export function priceChanges(built: { price: number; strike: number }, live: { price: number; strike: number | null }): Map<string, string> {
  const now = strike(live.price, live.strike) ?? built.strike;
  const map = new Map<string, string>();
  if (live.price !== built.price) map.set(rupiah(built.price), rupiah(live.price));
  if (built.strike > 0 && now !== built.strike) map.set(rupiah(built.strike), rupiah(now));
  const savedBefore = built.strike - built.price;
  const savedNow = now - live.price;
  if (savedBefore > 0 && savedNow > 0 && savedNow !== savedBefore) map.set(rupiah(savedBefore), rupiah(savedNow));
  return map;
}
