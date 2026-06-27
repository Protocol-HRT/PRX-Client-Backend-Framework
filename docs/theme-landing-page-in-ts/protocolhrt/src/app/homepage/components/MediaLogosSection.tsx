'use client';
import React, { useEffect, useRef } from 'react';

const mediaLogos = [
  { name: 'Forbes', text: 'Forbes' },
  { name: 'Bloomberg', text: 'Bloomberg' },
  { name: 'Healthline', text: 'Healthline' },
  { name: 'WebMD', text: 'WebMD' },
  { name: 'Men\'s Health', text: "Men\'s Health" },
  { name: 'Shape', text: 'Shape' },
  { name: 'Muscle & Fitness', text: 'Muscle & Fitness' },
];

const allLogos = [...mediaLogos, ...mediaLogos];

export default function MediaLogosSection() {
  return (
    <section
      className="py-10 px-4 sm:px-6 lg:px-8 overflow-hidden"
      style={{ background: '#FFFFFF', borderBottom: '1px solid rgba(56,49,44,0.08)' }}
    >
      <div className="max-w-7xl mx-auto">
        <p
          className="text-center font-body mb-6"
          style={{
            color: '#9B9189',
            fontSize: '13px',
            fontFamily: 'Red Hat Text, sans-serif',
            letterSpacing: '0.05em',
          }}
        >
          Proud to be featured and recognized in
        </p>

        {/* Logo Ticker */}
        <div className="overflow-hidden">
          <div className="logo-ticker-track">
            {allLogos?.map((logo, i) => (
              <div
                key={i}
                className="flex-shrink-0 flex items-center justify-center"
                style={{ minWidth: '120px' }}
              >
                <span
                  className="font-display font-bold"
                  style={{
                    color: 'rgba(56,49,44,0.25)',
                    fontSize: '18px',
                    fontFamily: 'Cormorant Garamond, serif',
                    letterSpacing: '-0.01em',
                    whiteSpace: 'nowrap',
                    transition: 'color 0.3s ease',
                  }}
                  onMouseEnter={(e) => (e.currentTarget.style.color = 'rgba(56,49,44,0.6)')}
                  onMouseLeave={(e) => (e.currentTarget.style.color = 'rgba(56,49,44,0.25)')}
                >
                  {logo?.text}
                </span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
