/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
        './app/View/Components/**/*.php',
    ],

    theme: {
        extend: {
            /**
             * Color tokens lifted from the Rocket source.
             * Light + dark mode pairs are encoded as semantic tokens (bg.primary, text.body, etc.)
             * — the actual switching is driven by CSS custom properties in app.css so a future
             * theme toggle / per-page override works without re-mapping classes.
             */
            colors: {
                bg: {
                    primary:  'var(--bg-primary)',
                    surface:  'var(--bg-surface)',
                    cream:    '#fafaf8',
                    mint:     '#f2f5f0',
                    dark:     '#0d0d0d',
                    'dark-2': '#141414',
                    'dark-3': '#1c1c1c',
                    card:     '#1e2124',
                    hero:     '#0d0d0d',
                },
                text: {
                    primary:        'var(--text-primary)',
                    body:           'var(--text-body)',
                    muted:          'var(--text-muted)',
                    'on-dark':      '#ffffff',
                    'on-dark-muted':'rgba(255,255,255,0.6)',
                },
                accent: {
                    gold:           '#c9a84c',
                    'gold-light':   'rgba(201,168,76,0.15)',
                    'gold-border':  'rgba(201,168,76,0.25)',
                    green:          '#5a8a5e',
                    'green-hover':  '#4a7a4e',
                    emerald:        '#2d7a4f',
                    'emerald-hover':'#3a9e68',
                    mint:           '#c8dec9',
                    blue:           '#6b8fa3',
                    'blue-deep':    '#5a7a8a',
                },
                border: {
                    light:    'rgba(0,0,0,0.07)',
                    dark:     'hsla(0,0%,100%,0.08)',
                    subtle:   'hsla(33,30%,87%,0.12)',
                    divider:  'hsla(33,30%,87%,0.08)',
                    card:     'hsla(36,24%,96%,0.06)',
                    mint:     'rgba(90,138,94,0.2)',
                    gold:     'rgba(201,168,76,0.25)',
                },
            },

            fontFamily: {
                display: ['Cormorant Garamond', 'Georgia', 'serif'],
                body:    ['DM Sans', 'system-ui', 'sans-serif'],
                sub:     ['Syne', 'sans-serif'],
                mono:    ['JetBrains Mono', 'Space Mono', 'monospace'],
            },

            // Display sizes for the editorial Cormorant headlines
            fontSize: {
                'display-xl': ['clamp(2.75rem, 6vw, 5rem)',   { lineHeight: '1.05', letterSpacing: '-0.02em' }],
                'display-lg': ['clamp(2.25rem, 4.5vw, 3.75rem)', { lineHeight: '1.08', letterSpacing: '-0.015em' }],
                'display-md': ['clamp(1.875rem, 3vw, 2.5rem)', { lineHeight: '1.15', letterSpacing: '-0.01em' }],
                'eyebrow':    ['0.75rem',  { lineHeight: '1', letterSpacing: '0.18em' }],
            },

            letterSpacing: {
                eyebrow: '0.18em',
            },

            boxShadow: {
                'card':       '0 4px 24px rgba(0,0,0,0.06)',
                'card-hover': '0 8px 32px rgba(0,0,0,0.10)',
                'card-dark':  '0 4px 24px rgba(0,0,0,0.4)',
                'gold':       '0 0 0 1px rgba(201,168,76,0.25), 0 8px 32px rgba(201,168,76,0.08)',
            },

            // Spacing rhythm matches the source's py-20 / py-24 / py-32 cadence.
            // Adding semantic aliases so sections don't have to memorise the cadence.
            spacing: {
                'section-y':    '6rem',   // py-24
                'section-y-lg': '8rem',   // py-32
            },

            animation: {
                'marquee':    'marquee 40s linear infinite',
                'fade-up':    'fadeUp 0.6s ease-out both',
                'fade-in':    'fadeIn 0.4s ease-out both',
            },

            keyframes: {
                marquee: {
                    '0%':   { transform: 'translateX(0)' },
                    '100%': { transform: 'translateX(-50%)' },
                },
                fadeUp: {
                    '0%':   { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                fadeIn: {
                    '0%':   { opacity: '0' },
                    '100%': { opacity: '1' },
                },
            },
        },
    },

    plugins: [],
};
