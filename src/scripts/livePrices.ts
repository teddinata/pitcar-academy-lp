/**
 * Refreshes printed online-class prices from the LMS on every visit, so a
 * price saved in the admin shows without rebuilding the site.
 *
 * An element marked with livePriceAttrs() (src/lib/livePrices.ts) names a
 * class and the prices the build printed for it. Inside it, each of those
 * strings (price, strike price, saving) is swapped for the current one, in
 * text, link targets and labels. The nearest marked ancestor decides which
 * class a string belongs to, so a block about another class can sit inside a
 * page about this one. If the API does not answer, the printed prices stay.
 *
 * The same answer carries the class's reviews, which refill the "Kata siswa"
 * section (CourseReviews.astro).
 *
 * When it is done, answered or not, <html> gets data-live-prices="done" and
 * the document hears "live-prices:done", so analytics can wait for the price
 * a visitor actually sees (src/scripts/checkoutClick.ts).
 */

import { MIN_RATINGS, parseReviews, type CourseReviews } from '../lib/livePrices';
import { priceChanges } from '../lib/pricing';

type Built = { price: number; strike: number };
type Live = { price: number; strike: number | null; students: number | null; reviews: CourseReviews | null };

// Same floor as the build (MIN_CLASSMATES in src/lib/livePrices.ts).
const MIN_CLASSMATES = 10;

const base = (import.meta.env.PUBLIC_API_BASE_URL ?? import.meta.env.PUBLIC_LEAD_API_BASE_URL ?? '').trim().replace(/\/$/, '');
const ATTRIBUTES = ['href', 'aria-label', 'title'];

async function fetchPrice(slug: string): Promise<Live | null> {
  try {
    const res = await fetch(`${base}/api/checkout/courses/${encodeURIComponent(slug)}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) return null;
    const data = (await res.json()) as { course?: { price?: unknown; strike_price?: unknown; students?: unknown }; reviews?: unknown };
    const price = data.course?.price;
    const strike = data.course?.strike_price;
    const students = data.course?.students;
    if (typeof price !== 'number' || !Number.isInteger(price) || price <= 0) return null;
    return {
      price,
      strike: typeof strike === 'number' ? strike : null,
      students: typeof students === 'number' && Number.isInteger(students) && students >= 0 ? students : null,
      reviews: parseReviews(data.reviews),
    };
  } catch {
    return null;
  }
}

/** One pass, so a new price that equals another old string is not replaced twice. */
function replacer(map: Map<string, string>): (text: string) => string {
  const escape = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  // A price must not be the tail or head of a longer number.
  const pattern = new RegExp(`(?<![\\d.])(${[...map.keys()].map(escape).join('|')})(?![\\d.])`, 'g');
  return (text) => text.replace(pattern, (match) => map.get(match) ?? match);
}

function apply(scope: HTMLElement, built: Built, live: Live) {
  const map = priceChanges(built, live);
  if (map.size === 0) return;
  const swap = replacer(map);
  const own = (el: Element | null) => el?.closest('[data-live-price]') === scope;

  const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, {
    acceptNode: (node) => {
      const parent = node.parentElement;
      if (!parent || parent.closest('script, style, noscript') || !own(parent)) return NodeFilter.FILTER_REJECT;
      return NodeFilter.FILTER_ACCEPT;
    },
  });
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    const next = swap(node.nodeValue ?? '');
    if (next !== node.nodeValue) node.nodeValue = next;
  }

  for (const el of [scope, ...scope.querySelectorAll<HTMLElement>('*')]) {
    if (!own(el)) continue;
    for (const name of ATTRIBUTES) {
      const value = el.getAttribute(name);
      if (value === null) continue;
      // WhatsApp links carry the price URL-encoded; "Rp99.000" encodes to itself.
      const next = swap(value);
      if (next !== value) el.setAttribute(name, next);
    }
    // Checkout links report this number to analytics.
    if (el.dataset.price === String(built.price)) el.dataset.price = String(live.price);
  }

  if (scope === document.body) document.title = swap(document.title);
}

/** "Bergabung dengan N siswa lain" lines (ClassmateCount.astro) take today's count. */
function applyClassmates(live: Map<string, Live | null>) {
  for (const line of document.querySelectorAll<HTMLElement>('[data-classmates]')) {
    const students = live.get(line.dataset.classmates!)?.students;
    if (students == null) continue;
    const count = line.querySelector('[data-classmates-count]');
    if (count) count.textContent = students.toLocaleString('id-ID');
    line.hidden = students < MIN_CLASSMATES;
  }
}

const score = (n: number) => n.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const month = (date: string) => new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(new Date(date));

/** "Kata siswa" sections take today's score and the reviews the team has published. */
function applyReviews(live: Map<string, Live | null>) {
  for (const section of document.querySelectorAll<HTMLElement>('[data-reviews]')) {
    const reviews = live.get(section.dataset.reviews!)?.reviews;
    if (!reviews) continue;
    const showScore = reviews.average !== null && reviews.count >= MIN_RATINGS;
    const scoreBox = section.querySelector<HTMLElement>('[data-reviews-score]')!;
    scoreBox.hidden = !showScore;
    if (showScore) {
      scoreBox.querySelector('[data-reviews-average]')!.textContent = score(reviews.average!);
      scoreBox.querySelector('[data-reviews-count]')!.textContent = reviews.count.toLocaleString('id-ID');
    }

    const list = section.querySelector<HTMLElement>('[data-reviews-list]')!;
    const template = section.querySelector<HTMLTemplateElement>('[data-reviews-item]')!;
    list.replaceChildren(
      ...reviews.items.map((review) => {
        const item = template.content.firstElementChild!.cloneNode(true) as HTMLElement;
        const set = (sel: string, text: string) => (item.querySelector(sel)!.textContent = text);
        item.querySelector('[role="img"]')!.setAttribute('aria-label', `Nilai ${review.rating} dari 5`);
        set('[data-r-on]', '★'.repeat(review.rating));
        set('[data-r-off]', '★'.repeat(5 - review.rating));
        set('[data-r-body]', review.body);
        set('[data-r-name]', review.name);
        set('[data-r-meta]', `, siswa kelas ini${review.date ? `, ${month(review.date)}` : ''}`);
        return item;
      }),
    );
    list.hidden = reviews.items.length === 0;
    section.hidden = reviews.items.length === 0 && !showScore;
  }
}

async function refresh() {
  if (!base) return;
  const scopes = [...document.querySelectorAll<HTMLElement>('[data-live-price]')];
  const lines = [...document.querySelectorAll<HTMLElement>('[data-classmates]')];
  const reviewed = [...document.querySelectorAll<HTMLElement>('[data-reviews]')];
  if (scopes.length === 0 && lines.length === 0 && reviewed.length === 0) return;
  const slugs = [
    ...new Set([...scopes.map((s) => s.dataset.livePrice!), ...lines.map((l) => l.dataset.classmates!), ...reviewed.map((r) => r.dataset.reviews!)]),
  ];
  const live = new Map(await Promise.all(slugs.map(async (slug) => [slug, await fetchPrice(slug)] as const)));
  applyClassmates(live);
  applyReviews(live);

  for (const scope of scopes) {
    const price = live.get(scope.dataset.livePrice!);
    const [builtPrice, builtStrike] = (scope.dataset.liveBuilt ?? '').split('/').map(Number);
    if (!price || !builtPrice) continue;
    apply(scope, { price: builtPrice, strike: builtStrike || 0 }, price);
  }
}

refresh()
  .catch(() => { /* the printed prices stay */ })
  .finally(() => {
    document.documentElement.dataset.livePrices = 'done';
    document.dispatchEvent(new Event('live-prices:done'));
  });
