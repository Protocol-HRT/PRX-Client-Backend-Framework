'use client';
import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter, usePathname } from 'next/navigation';
import { openIntakeModal } from '@/lib/openIntakeModal';
import { useAuth } from '@/contexts/AuthContext';

const navLinks = [
  { label: 'About', href: '#founders' },
  { label: 'How It Works', href: '#process' },
  { label: 'HIM', href: '#him' },
  { label: 'HER', href: '#her' },
  { label: 'AI Agent', href: '#ai-concierge' },
  { label: 'Testimonials', href: '#testimonials' },
  { label: 'FAQ', href: '#faq' },
  { label: 'Dan Bilzerian', href: '/dan-bilzerian' },
];

export default function Header() {
  const [scrolled, setScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const { user, signOut } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 40);
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    if (!menuOpen) return;
    const handleScroll = () => setMenuOpen(false);
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, [menuOpen]);

  const handleNavClick = (href: string) => {
    setMenuOpen(false);
    // If it's an absolute path (starts with /), navigate directly
    if (href.startsWith('/')) {
      router.push(href);
      return;
    }
    const isHomepage = pathname === '/homepage' || pathname === '/';
    if (isHomepage) {
      const el = document.querySelector(href);
      if (el) el.scrollIntoView({ behavior: 'smooth' });
    } else {
      router.push(`/homepage${href}`);
    }
  };

  const handleSignOut = async () => {
    try {
      await signOut();
      router.push('/homepage');
    } catch {}
  };

  return (
    <>
      {/* Announcement Bar */}
      <div
        className="announcement-bar text-center py-2.5 px-4"
        style={{
          background: '#FFFFFF',
          borderBottom: '1px solid rgba(0,0,0,0.06)',
          fontSize: '13px',
          color: '#4A4A4A',
          fontFamily: 'DM Sans, system-ui, sans-serif',
        }}
      >
        <strong style={{ color: '#3A6A3E', fontWeight: 600 }}>Licensed in All 50 States</strong>
        {' '}· Physician-Reviewed Protocols · Your Physician. Your Protocol.
      </div>

      {/* Navigation */}
      <nav
        className="fixed left-0 right-0 z-50 transition-all duration-500"
        style={{
          top: scrolled ? '0' : '40px',
          background: scrolled ? 'rgba(255,255,255,0.95)' : 'rgba(255,255,255,0.85)',
          backdropFilter: 'blur(24px)',
          WebkitBackdropFilter: 'blur(24px)',
          borderBottom: '1px solid rgba(0,0,0,0.06)',
          boxShadow: scrolled ? '0 1px 0 rgba(0,0,0,0.05), 0 4px 20px rgba(0,0,0,0.04)' : 'none',
        }}
      >
        <div className="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">
          <div className="flex items-center justify-between h-16 sm:h-[68px]">
            {/* Logo */}
            <Link href="/homepage" className="flex items-center gap-2.5 flex-shrink-0">
              {/* Emblem */}
              <div
                style={{
                  width: 32,
                  height: 32,
                  borderRadius: 6,
                  overflow: 'hidden',
                  flexShrink: 0,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  background: '#1A1A1A',
                  border: '0.75px solid rgba(201,168,76,0.8)',
                }}
              >
                <img
                  src="/assets/protocol-p-emblem-gold.svg"
                  alt="Protocol HRT emblem"
                  style={{ width: '100%', height: '100%', objectFit: 'contain', display: 'block', padding: '3px' }}
                />
              </div>
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
            </Link>

            {/* Desktop Nav */}
            <div className="hidden lg:flex items-center gap-7">
              {navLinks.map((link) => (
                <button
                  key={link.label}
                  onClick={() => handleNavClick(link.href)}
                  className="transition-colors duration-200"
                  style={{
                    color: '#6A6A6A',
                    background: 'none',
                    border: 'none',
                    cursor: 'pointer',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontSize: '14px',
                    fontWeight: 400,
                    letterSpacing: '-0.01em',
                  }}
                  onMouseEnter={(e) => (e.currentTarget.style.color = '#1A1A1A')}
                  onMouseLeave={(e) => (e.currentTarget.style.color = '#6A6A6A')}
                >
                  {link.label}
                </button>
              ))}
            </div>

            {/* Desktop CTA */}
            <div className="hidden lg:flex items-center gap-3">
              {user ? (
                <>
                  <Link
                    href="/patient-portal"
                    style={{
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      fontSize: '14px',
                      fontWeight: 500,
                      color: '#5A8A5E',
                      textDecoration: 'none',
                      letterSpacing: '-0.01em',
                    }}
                  >
                    Patient Portal
                  </Link>
                  <button
                    onClick={handleSignOut}
                    style={{
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      fontSize: '14px',
                      fontWeight: 500,
                      color: '#6A6A6A',
                      background: 'none',
                      border: '1px solid rgba(0,0,0,0.12)',
                      borderRadius: 8,
                      padding: '8px 16px',
                      cursor: 'pointer',
                      letterSpacing: '-0.01em',
                    }}
                  >
                    Sign Out
                  </button>
                </>
              ) : (
                <>
                  <Link
                    href="/login"
                    style={{
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      fontSize: '14px',
                      fontWeight: 500,
                      color: '#6A6A6A',
                      textDecoration: 'none',
                      letterSpacing: '-0.01em',
                      border: '1px solid rgba(0,0,0,0.12)',
                      borderRadius: 8,
                      padding: '8px 16px',
                      display: 'inline-flex',
                      alignItems: 'center',
                    }}
                    onMouseEnter={(e) => { (e.currentTarget as HTMLAnchorElement).style.color = '#1A1A1A'; (e.currentTarget as HTMLAnchorElement).style.borderColor = 'rgba(0,0,0,0.25)'; }}
                    onMouseLeave={(e) => { (e.currentTarget as HTMLAnchorElement).style.color = '#6A6A6A'; (e.currentTarget as HTMLAnchorElement).style.borderColor = 'rgba(0,0,0,0.12)'; }}
                  >
                    Sign In / Create Account
                  </Link>
                  <button
                    onClick={() => openIntakeModal()}
                    className="btn-primary"
                    style={{ height: '40px', fontSize: '14px', padding: '0 20px', display: 'inline-flex', alignItems: 'center', cursor: 'pointer' }}
                  >
                    Get Started
                  </button>
                </>
              )}
            </div>

            {/* Mobile Hamburger */}
            <button
              className="lg:hidden flex flex-col gap-[5px] items-end w-10 h-10 justify-center"
              onClick={() => setMenuOpen(!menuOpen)}
              aria-label={menuOpen ? 'Close menu' : 'Open menu'}
            >
              <span
                className="block h-[1.5px] w-6 rounded-full transition-all duration-300"
                style={{
                  background: '#1A1A1A',
                  transform: menuOpen ? 'rotate(45deg) translateY(6.5px)' : 'none',
                }}
              />
              <span
                className="block h-[1.5px] rounded-full transition-all duration-300"
                style={{
                  background: '#1A1A1A',
                  width: menuOpen ? '0' : '18px',
                  opacity: menuOpen ? 0 : 1,
                }}
              />
              <span
                className="block h-[1.5px] w-6 rounded-full transition-all duration-300"
                style={{
                  background: '#1A1A1A',
                  transform: menuOpen ? 'rotate(-45deg) translateY(-6.5px)' : 'none',
                }}
              />
            </button>
          </div>
        </div>
      </nav>

      {/* Mobile Overlay Menu */}
      <div
        className="fixed inset-0 z-40 lg:hidden transition-all duration-500"
        style={{
          background: '#FFFFFF',
          opacity: menuOpen ? 1 : 0,
          pointerEvents: menuOpen ? 'auto' : 'none',
        }}
      >
        <div className="flex flex-col h-full pt-24 px-6 pb-8">
          <div className="flex flex-col gap-0 flex-1">
            {navLinks.map((link, i) => (
              <button
                key={link.label}
                onClick={() => handleNavClick(link.href)}
                className="text-left py-5 border-b transition-colors duration-200"
                style={{
                  color: '#1A1A1A',
                  fontSize: '26px',
                  fontWeight: 500,
                  letterSpacing: '-0.02em',
                  borderColor: 'rgba(0,0,0,0.06)',
                  background: 'none',
                  cursor: 'pointer',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  transitionDelay: menuOpen ? `${i * 35}ms` : '0ms',
                }}
              >
                {link.label}
              </button>
            ))}
            <Link
              href={user ? '/patient-portal' : '/login'}
              onClick={() => setMenuOpen(false)}
              className="text-left py-5 border-b"
              style={{
                color: '#5A8A5E',
                fontSize: '26px',
                fontWeight: 500,
                letterSpacing: '-0.02em',
                borderColor: 'rgba(0,0,0,0.06)',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                textDecoration: 'none',
                display: 'block',
              }}
            >
              {user ? 'Patient Portal' : 'Sign In / Create Account'}
            </Link>
          </div>
          <button
            onClick={() => { setMenuOpen(false); openIntakeModal(); }}
            className="btn-primary w-full mt-6"
            style={{ height: '52px', fontSize: '15px' }}
          >
            Build My Protocol →
          </button>
        </div>
      </div>
    </>
  );
}