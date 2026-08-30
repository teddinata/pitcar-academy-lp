/** @type {import('tailwindcss').Config} */
export default {
  // Theme is a `dark` class on <html>, set before paint by an inline script.
  // Never 'media': the palette must follow the toggle, not the OS.
  darkMode: 'class',
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        // Semantic tokens backed by CSS variables that flip in `.dark`.
        // Components reference these and never write a `dark:` variant, so a
        // colour cannot end up flipped in one place and not another.
        ground: 'rgb(var(--ground) / <alpha-value>)',
        'ground-alt': 'rgb(var(--ground-alt) / <alpha-value>)',
        surface: 'rgb(var(--surface) / <alpha-value>)',
        line: 'rgb(var(--line) / <alpha-value>)',
        body: 'rgb(var(--body) / <alpha-value>)',
        muted: 'rgb(var(--muted) / <alpha-value>)',
        faint: 'rgb(var(--faint) / <alpha-value>)',
        // Accent TEXT flips (#CC0000 fails AA on near-black at 3.36:1).
        accent: 'rgb(var(--accent) / <alpha-value>)',
        // Accent FILL does not: white on #CC0000 is 5.89:1 in both themes.
        'accent-fill': '#CC0000',
        'accent-fill-hover': '#AA0000',
      },
      fontFamily: {
        display: ["'Barlow Condensed'", 'ui-sans-serif', 'system-ui', 'sans-serif'],
        sans: ["'Inter Variable'", 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      maxWidth: {
        prose: '68ch',
      },
    },
  },
  plugins: [],
};
