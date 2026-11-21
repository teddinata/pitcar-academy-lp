import { describe, expect, it } from 'vitest';
import { parseRupiah, priceChanges, quote, ribu, rupiah, strike, type Offer, type Priced } from './pricing';

const ac: Priced = { slug: 'kelas-online-ac', title: 'Kelas AC', price: 99000, strike_price: 499000 };
const inspeksi: Offer = { slug: 'kelas-online-inspeksi', title: 'Kelas Inspeksi', price: 99000, strike_price: 499000, offer_price: 49000 };

describe('formatting', () => {
  it('writes rupiah without a space and rounds', () => {
    expect(rupiah(99000)).toBe('Rp99.000');
    expect(rupiah(1499.6)).toBe('Rp1.500');
  });

  it('reads round thousands as ribu', () => {
    expect(ribu(49000)).toBe('49 ribu');
    expect(ribu(49500)).toBe('Rp49.500');
  });

  it('reads printed prices back, with or without a space', () => {
    expect(parseRupiah('Rp 7.500.000')).toBe(7500000);
    expect(parseRupiah('Rp499.000')).toBe(499000);
    expect(parseRupiah('')).toBe(0);
    expect(parseRupiah(undefined)).toBe(0);
  });
});

describe('strike', () => {
  it('takes the LMS strike price only above the price', () => {
    expect(strike(99000, 499000)).toBe(499000);
    expect(strike(99000, 99000)).toBeNull();
    expect(strike(99000, null)).toBeNull();
  });

  it('falls back to the printed one when the LMS has none or a lower one', () => {
    expect(strike(99000, null, 499000)).toBe(499000);
    expect(strike(99000, 50000, 499000)).toBe(499000);
    expect(strike(599000, null, 499000)).toBeNull();
  });
});

describe('quote', () => {
  it('charges the class alone at its price, saving against its strike price', () => {
    const q = quote(ac, [inspeksi], []);
    expect(q.lines).toEqual([{ slug: 'kelas-online-ac', title: 'Kelas AC', normal: 499000, price: 99000, saving: 400000 }]);
    expect(q).toMatchObject({ normal: 499000, saving: 400000, total: 99000 });
  });

  it('gives an add-on the same harga normal as the class: its strike price', () => {
    const q = quote(ac, [inspeksi], ['kelas-online-inspeksi']);
    expect(q.lines[1]).toMatchObject({ normal: 499000, price: 49000, saving: 450000 });
    expect(q).toMatchObject({ normal: 998000, saving: 850000, total: 148000, missed: null });
  });

  it('uses the add-on bought alone as harga normal when it has no strike price', () => {
    const q = quote(ac, [{ ...inspeksi, strike_price: null }], ['kelas-online-inspeksi']);
    expect(q.lines[1]).toMatchObject({ normal: 99000, saving: 50000 });
  });

  it('never shows a negative saving', () => {
    const q = quote({ ...ac, strike_price: null }, [{ ...inspeksi, price: 30000, strike_price: null }], ['kelas-online-inspeksi']);
    expect(q.lines.map((l) => l.saving)).toEqual([0, 0]);
    expect(q.saving).toBe(0);
  });

  it('lists every offer for the cards, with what it costs alone', () => {
    const q = quote(ac, [inspeksi], []);
    expect(q.addons).toEqual([{ slug: 'kelas-online-inspeksi', title: 'Kelas Inspeksi', normal: 499000, price: 49000, saving: 450000, alone: 99000, chosen: false }]);
  });

  it('names the unchosen add-on that saves the most', () => {
    const small: Offer = { ...inspeksi, slug: 'kecil', strike_price: null };
    const q = quote(ac, [small, inspeksi], []);
    expect(q.missed?.slug).toBe('kelas-online-inspeksi');
    expect(quote(ac, [small, inspeksi], ['kelas-online-inspeksi']).missed?.slug).toBe('kecil');
  });

  it('names none when no unchosen add-on saves anything', () => {
    expect(quote(ac, [{ ...inspeksi, price: 49000, strike_price: null }], []).missed).toBeNull();
  });
});

describe('priceChanges', () => {
  it('is empty when nothing changed', () => {
    expect(priceChanges({ price: 99000, strike: 499000 }, { price: 99000, strike: 499000 }).size).toBe(0);
  });

  it('swaps price, strike and saving', () => {
    const map = priceChanges({ price: 99000, strike: 499000 }, { price: 129000, strike: 599000 });
    expect([...map]).toEqual([
      ['Rp99.000', 'Rp129.000'],
      ['Rp499.000', 'Rp599.000'],
      ['Rp400.000', 'Rp470.000'],
    ]);
  });

  it('keeps the printed strike price when the LMS has none', () => {
    const map = priceChanges({ price: 99000, strike: 499000 }, { price: 149000, strike: null });
    expect([...map]).toEqual([
      ['Rp99.000', 'Rp149.000'],
      ['Rp400.000', 'Rp350.000'],
    ]);
  });
});
