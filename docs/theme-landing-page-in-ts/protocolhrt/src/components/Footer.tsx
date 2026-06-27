'use client';
import React, { useState, useEffect } from 'react';
import Link from 'next/link';

const footerColumns = [
  {
    title: 'Protocols',
    links: ['Testosterone Optimization', 'Peptide Therapy', 'Weight Loss', "Women\'s Hormones", 'Longevity', 'Athletic Performance'],
  },
  {
    title: 'Company',
    links: ['About Us', 'Our Physicians', 'How It Works', 'Ambassadors', 'Blog', 'Careers'],
  },
  {
    title: 'Support',
    links: ['AI Concierge', 'FAQ', 'Contact Us', 'Patient Portal', 'HIPAA Notice'],
  },
  {
    title: 'Legal',
    links: ['Privacy Policy', 'Terms of Service', 'Telehealth Consent', 'Accessibility'],
  },
];

export default function Footer() {
  const [year, setYear] = useState('2025');

  useEffect(() => {
    setYear(new Date()?.getFullYear()?.toString());
  }, []);

  return (
    <footer
      id="footer"
      style={{ background: '#FAFAF8', borderTop: '1px solid rgba(0,0,0,0.06)' }}
      className="pt-16 pb-8 px-5 sm:px-8 lg:px-10"
    >
      <div className="max-w-7xl mx-auto">
        {/* Top Row */}
        <div className="flex flex-col sm:flex-row sm:items-start justify-between mb-12 gap-6">
          <div>
            <div className="flex items-center gap-2.5 mb-3">
              <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M16 2L28 8.5V23.5L16 30L4 23.5V8.5L16 2Z"
                  fill="#1A1A1A"
                />
                <path
                  d="M16 2L28 8.5V23.5L16 30L4 23.5V8.5L16 2Z"
                  fill="none"
                  stroke="#C9A84C"
                  strokeWidth="0.75"
                  opacity="0.8"
                />
                <text
                  x="16"
                  y="22"
                  textAnchor="middle"
                  fill="white"
                  fontSize="16"
                  fontWeight="700"
                  fontFamily="Georgia, serif"
                  letterSpacing="-0.5"
                >P</text>
              </svg>
              <span
                style={{
                  color: '#1A1A1A',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontSize: '17px',
                  fontWeight: 600,
                  letterSpacing: '-0.02em',
                }}
              >
                Protocol<span style={{ fontWeight: 800, fontStyle: 'italic', color: '#C9A84C' }}>HRT</span>
              </span>
            </div>
            <p
              style={{
                color: '#8A8A8A',
                maxWidth: '240px',
                lineHeight: '1.6',
                fontSize: '14px',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 400,
              }}
            >
              Built by physicians. Proven by the world&apos;s best. Made for everyone.
            </p>
          </div>

          <div className="flex flex-col gap-2">
            <div className="flex items-center gap-2">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5A8A5E" strokeWidth="1.5">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                <polyline points="22,6 12,13 2,6" />
              </svg>
              <span style={{ color: '#4A4A4A', fontSize: '14px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
                help@protocolhrt.com
              </span>
            </div>
            <div className="flex items-center gap-2">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#5A8A5E" strokeWidth="1.5">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.38 2 2 0 0 1 3.58 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.56a16 16 0 0 0 5.53 5.53l1.62-1.62a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
              </svg>
              <span style={{ color: '#4A4A4A', fontSize: '14px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
                (800) HRT-PROT
              </span>
            </div>
          </div>
        </div>

        {/* Link Columns */}
        <div
          className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12 pb-12"
          style={{ borderBottom: '1px solid rgba(0,0,0,0.06)' }}
        >
          {footerColumns?.map((col) => (
            <div key={col?.title}>
              <h4
                style={{
                  color: '#1A1A1A',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontSize: '13px',
                  fontWeight: 600,
                  letterSpacing: '-0.01em',
                  marginBottom: '14px',
                }}
              >
                {col?.title}
              </h4>
              <ul className="space-y-2.5">
                {col?.links?.map((link) => (
                  <li key={link}>
                    <Link
                      href="#"
                      className="transition-colors duration-200"
                      style={{ color: '#8A8A8A', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', fontWeight: 400 }}
                      onMouseEnter={(e) => (e.currentTarget.style.color = '#1A1A1A')}
                      onMouseLeave={(e) => (e.currentTarget.style.color = '#8A8A8A')}
                    >
                      {link}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        {/* Bottom Bar */}
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 mb-8">
          <p style={{ color: '#8A8A8A', fontSize: '13px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
            © {year} ProtocolHRT. All rights reserved. Licensed in all 50 states.
          </p>
          <div className="flex items-center gap-4">
            <a
              href="#"
              aria-label="Twitter / X"
              className="transition-opacity duration-200 hover:opacity-50"
              style={{ color: '#8A8A8A' }}
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.629L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z" />
              </svg>
            </a>
            <a
              href="#"
              aria-label="Instagram"
              className="transition-opacity duration-200 hover:opacity-50"
              style={{ color: '#8A8A8A' }}
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                <rect x="2" y="2" width="20" height="20" rx="5" />
                <circle cx="12" cy="12" r="4" />
                <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
              </svg>
            </a>
          </div>
        </div>

        {/* FDA Disclaimer */}
        <p
          className="text-center leading-relaxed"
          style={{ fontSize: '11px', color: 'rgba(0,0,0,0.3)', lineHeight: '1.7', fontFamily: 'DM Sans, system-ui, sans-serif' }}
        >
          *These statements have not been evaluated by the FDA. Not intended to diagnose, treat, cure, or prevent any disease.
          All protocols are physician-reviewed. Consult your physician before starting any program.
          ProtocolHRT is a telemedicine platform licensed in all 50 states. Individual results may vary.
        </p>
      </div>
    </footer>
  );
}