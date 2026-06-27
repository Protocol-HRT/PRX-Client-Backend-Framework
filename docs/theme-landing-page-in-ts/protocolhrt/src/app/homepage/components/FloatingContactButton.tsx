'use client';
import React from 'react';

export default function FloatingContactButton() {
  return (
    <a
      href="sms:+1"
      aria-label="Contact us via SMS"
      className="md:hidden fixed bottom-20 left-4 z-50 w-12 h-12 rounded-full flex items-center justify-center transition-transform duration-200 hover:scale-110 active:scale-95"
      style={{
        background: 'rgba(119,157,124,0.15)',
        border: '1.5px solid rgba(119,157,124,0.4)',
        backdropFilter: 'blur(12px)',
        color: '#779D7C',
      }}
    >
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
      </svg>
    </a>
  );
}