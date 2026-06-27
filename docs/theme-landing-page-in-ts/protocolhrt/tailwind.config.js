/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      colors: {
        'bg-primary': '#FFFFFF',
        'bg-hero': '#F5F7F5',
        'bg-surface': '#F8F7F5',
        'bg-mint': '#F2F5F0',
        'bg-cream': '#FAFAF8',
        'bg-card': '#F2F5F0',
        'bg-dark': '#1A1A1A',
        'text-primary': '#1A1A1A',
        'text-body': '#4A4A4A',
        'text-muted': '#8A8A8A',
        'accent-green': '#5A8A5E',
        'accent-green-hover': '#4A7A4E',
        'accent-mint': '#C8DEC9',
        'accent-gold': '#B8943C',
        'accent-blue': '#5A7A8A',
        'border-light': 'rgba(0,0,0,0.07)',
        'border-mint': 'rgba(90,138,94,0.2)',
      },
      fontFamily: {
        display: ['Cormorant Garamond', 'Georgia', 'serif'],
        sub: ['DM Sans', 'system-ui', 'sans-serif'],
        body: ['DM Sans', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
      fontSize: {
        'hero-desktop': ['68px', { lineHeight: '1.02', letterSpacing: '-0.025em' }],
        'hero-mobile': ['40px', { lineHeight: '1.08', letterSpacing: '-0.015em' }],
        'display-lg': ['58px', { lineHeight: '1.03' }],
        'display-md': ['46px', { lineHeight: '1.06' }],
        'display-sm': ['36px', { lineHeight: '1.1' }],
        'section-label': ['11px', { letterSpacing: '0.08em' }],
      },
      borderRadius: {
        'pill': '50px',
        'card': '20px',
        'card-lg': '32px',
      },
      boxShadow: {
        'card': '0 2px 16px rgba(0,0,0,0.06)',
        'card-hover': '0 16px 40px rgba(0,0,0,0.10)',
        'btn': '0 4px 16px rgba(90,138,94,0.2)',
      },
      backgroundImage: {
        'hero-gradient': 'linear-gradient(180deg, #F5F7F5 0%, #FFFFFF 60%)',
        'mint-gradient': 'linear-gradient(135deg, #F2F5F0 0%, #EBF0E8 100%)',
        'cream-gradient': 'linear-gradient(135deg, #FAFAF8 0%, #F5F2EE 100%)',
      },
      screens: {
        'xs': '375px',
      },
    },
  },
  plugins: [],
}