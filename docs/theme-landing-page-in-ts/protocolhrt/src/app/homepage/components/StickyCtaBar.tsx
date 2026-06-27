'use client';
import React, { useState, useEffect } from 'react';
import { openIntakeModal } from '@/lib/openIntakeModal';

export default function StickyCtaBar() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      setVisible(window.scrollY > window.innerHeight * 0.5);
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  return (
    <div
      className="md:hidden fixed bottom-0 left-0 right-0 z-50 transition-transform duration-300"
      style={{
        transform: visible ? 'translateY(0)' : 'translateY(100%)',
        paddingBottom: 'env(safe-area-inset-bottom, 0px)',
        background: 'linear-gradient(135deg, #1A1A1A 0%, #2A2218 100%)',
        borderTop: '1px solid rgba(201,168,76,0.25)',
        boxShadow: '0 -8px 32px rgba(0,0,0,0.4)',
      }}
    >
      <div className="flex items-stretch" style={{ height: '60px' }}>
        {/* Secondary: Find Protocol */}
        <button
          onClick={() => openIntakeModal()}
          className="flex items-center justify-center flex-1"
          style={{
            borderRight: '1px solid rgba(201,168,76,0.2)',
            color: 'rgba(255,255,255,0.65)',
            fontFamily: 'DM Sans, system-ui, sans-serif',
            fontWeight: 500,
            fontSize: '12px',
            letterSpacing: '0.04em',
            background: 'none',
            border: 'none',
            borderRight: '1px solid rgba(201,168,76,0.2)',
            cursor: 'pointer',
            gap: '6px',
          }}
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10" />
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          Find My Protocol
        </button>

        {/* Primary: Build Protocol */}
        <button
          onClick={() => openIntakeModal()}
          className="flex items-center justify-center"
          style={{
            flex: '1.4',
            background: '#C9A84C',
            color: '#0D0D0D',
            fontFamily: 'DM Sans, system-ui, sans-serif',
            fontWeight: 700,
            fontSize: '13px',
            letterSpacing: '0.06em',
            textTransform: 'uppercase',
            border: 'none',
            cursor: 'pointer',
            gap: '6px',
          }}
        >
          Build My Protocol
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
            <path d="M5 12h14M12 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>
  );
}