'use client';
import React from 'react';

const tickerItems = [
  { number: '50', label: 'States Licensed' },
  { number: '19', label: 'Advanced Compounds' },
  { number: '33', label: 'Precision SKUs' },
  { number: '10,000+', label: 'Clinical Studies Referenced' },
  { number: '100%', label: 'Physician-Reviewed Protocols' },
  { number: 'World-Class', label: 'Athletes & Executives' },
  { number: '24/7', label: 'AI Concierge Support' },
  { number: 'Dozens', label: 'High-Profile Ambassadors' },
];

const allItems = [...tickerItems, ...tickerItems];

export default function TrustBar() {
  return (
    <section
      id="trust-bar"
      style={{
        background: '#FFFFFF',
        borderTop: '1px solid rgba(0,0,0,0.06)',
        borderBottom: '1px solid rgba(0,0,0,0.06)',
      }}
      className="py-4 overflow-hidden"
    >
      <div className="ticker-track">
        {allItems?.map((item, i) => (
          <div key={i} className="flex items-center gap-2 flex-shrink-0 px-8">
            <span
              style={{
                color: '#1A1A1A',
                fontSize: '16px',
                fontWeight: 600,
                lineHeight: 1,
                fontFamily: 'DM Sans, system-ui, sans-serif',
                letterSpacing: '-0.02em',
              }}
            >
              {item?.number}
            </span>
            <span
              style={{
                color: '#6A6A6A',
                fontSize: '13px',
                whiteSpace: 'nowrap',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 400,
              }}
            >
              {item?.label}
            </span>
            <span className="ml-6" style={{ color: 'rgba(0,0,0,0.15)', fontSize: '16px' }}>·</span>
          </div>
        ))}
      </div>
    </section>
  );
}