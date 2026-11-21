/**
 * Ad and GA4 events around the buy button of a sales page, shared by the
 * catalog and each class page. Payment and Purchase are the checkout's.
 *
 * The class comes from the page's buy links ([data-checkout] with
 * data-item-id, data-item-name and data-price); the live-price refresh keeps
 * data-price current, so the view waits for it (at most a few seconds) and a
 * click reads it then. A link without its own item, such as the floating
 * WhatsApp button, counts for the page's first class.
 */

type Item = { item_id: string; item_name: string; price: number; quantity: 1 };

interface Page {
  /** 'item' for a class page; a list for the catalog. */
  view: 'item' | { list_id: string; list_name: string };
  /**
   * begin_checkout sends InitiateCheckout and tells the checkout not to send
   * it again; add_to_cart sends AddToCart and leaves InitiateCheckout to the
   * checkout (the inspection campaign optimises for AddToCart).
   */
  click: 'begin_checkout' | 'add_to_cart';
}

// The checkout reads these off its own URL, so carrying them across keeps the ad source attached to the order.
const ATTRIBUTION = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'ttclid', 'fbclid'];
const PIXEL = { begin_checkout: 'InitiateCheckout', add_to_cart: 'AddToCart' } as const;
const PRICES_WAIT_MS = 3000;

function itemOf(el: HTMLElement): Item | null {
  const { itemId, itemName, price } = el.dataset;
  return itemId && itemName ? { item_id: itemId, item_name: itemName, price: Number(price) || 0, quantity: 1 } : null;
}

function pageItems(): Item[] {
  const items = new Map<string, Item>();
  for (const link of document.querySelectorAll<HTMLElement>('[data-checkout][data-item-id]')) {
    const item = itemOf(link);
    if (item && !items.has(item.item_id)) items.set(item.item_id, item);
  }
  return [...items.values()];
}

/** Runs once the page is parsed and the live prices are in, or after a few seconds without them. */
function afterPrices(run: () => void) {
  let done = false;
  const once = () => {
    if (done) return;
    done = true;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, { once: true });
    else run();
  };
  if (document.documentElement.dataset.livePrices === 'done') return once();
  document.addEventListener('live-prices:done', once, { once: true });
  setTimeout(once, PRICES_WAIT_MS);
}

export function trackSalesPage(page: Page) {
  afterPrices(() => {
    const items = pageItems();
    if (page.view === 'item') {
      const [item] = items;
      if (item) window.trackFunnelEvent?.('view_item', { currency: 'IDR', value: item.price, items: [item] });
    } else {
      window.trackFunnelEvent?.('view_item_list', { item_list_id: page.view.list_id, item_list_name: page.view.list_name, items });
    }
  });

  document.addEventListener('click', (event) => {
    const link = (event.target as Element | null)?.closest?.<HTMLAnchorElement>('[data-checkout]');
    if (!link) return;
    const toCheckout = link.hostname === location.hostname && link.pathname.startsWith('/checkout');
    if (toCheckout || link.hostname.endsWith('myscalev.com')) {
      const from = new URLSearchParams(location.search);
      const to = new URL(link.href);
      for (const key of ATTRIBUTION) {
        if (from.has(key) && !to.searchParams.has(key)) to.searchParams.set(key, from.get(key)!);
      }
      link.href = to.toString();
    }

    const item = itemOf(link) ?? pageItems()[0];
    if (!item) return;
    window.trackFunnelEvent?.(page.click, { currency: 'IDR', value: item.price, items: [item], source_cta: link.dataset.sourceCta || 'unknown' });
    window.fbq?.('track', PIXEL[page.click], { value: item.price, currency: 'IDR', content_name: item.item_name, content_ids: [item.item_id], content_type: 'product' });
    // The checkout page sends InitiateCheckout itself unless told this click already did.
    if (page.click === 'begin_checkout' && toCheckout) {
      try { sessionStorage.setItem('pitcar_checkout_initiated', new URL(link.href).searchParams.get('kelas') || ''); } catch { /* storage blocked */ }
    }
  });
}
