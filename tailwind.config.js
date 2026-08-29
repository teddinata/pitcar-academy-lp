/** @type {import('tailwindcss').Config} */
export default {
  // The palette below only flips under a `.dark` class, which nothing sets.
  // Leaving this on the default 'media' let `dark:` utilities fire on
  // prefers-color-scheme while the page background stayed white.
  darkMode: 'class',
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        // Semantic tokens. Sections alternate between `ink` and `paper`; every
        // other colour is defined relative to which of the two it sits on.
        ink: {
          DEFAULT: '#0A0A0A', // dark section background
          soft: '#141414',    // raised card on a dark section
          line: '#262626',    // hairline on a dark section
        },
        paper: {
          DEFAULT: '#FAFAF9', // light section background
          soft: '#F5F5F4',    // raised card on a light section
          line: '#E7E5E4',    // hairline on a light section
        },
        // #CC0000 only reaches 3.36:1 on ink, so accent TEXT on dark uses
        // brand-400. #CC0000 stays for CTA fills (white on it is 5.89:1) and
        // for accent text on paper (5.64:1).
        brand: {
          50: '#fff1f1',
          100: '#ffe0e0',
          200: '#ffc7c7',
          300: '#ff9e9e',
          400: '#ff6464', // accent text on ink
          500: '#ff2d2d',
          600: '#cc0000', // CTA fill, accent text on paper
          700: '#aa0000', // CTA hover
          800: '#990000',
          900: '#7a0000',
          950: '#440000',
        },
      },
      fontFamily: {
        // Barlow Condensed carries the industrial, workshop-signage character
        // the headlines need; Inter stays for body because it is already
        // self-hosted and is a neutral grotesk that pairs with it cleanly.
        display: ["'Barlow Condensed'", 'ui-sans-serif', 'system-ui', 'sans-serif'],
        sans: ["'Inter Variable'", 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      maxWidth: {
        prose: '68ch', // keeps body copy inside the 65-75 character band
      },
      lineHeight: {
        tighter: '1.02',
      },
    },
  },
  plugins: [],
};
