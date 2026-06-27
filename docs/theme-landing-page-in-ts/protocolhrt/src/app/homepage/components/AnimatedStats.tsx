'use client';
import React, { useEffect, useRef, useState } from 'react';

interface Stat {
  end: number;
  suffix: string;
  prefix?: string;
  label: string;
  sublabel: string;
  icon: React.ReactNode;
}

const stats: Stat[] = [
  {
    end: 12400,
    suffix: '+',
    label: 'Patients Optimized',
    sublabel: 'Active patients on protocol',
    icon: (
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
      </svg>
    ),
  },
  {
    end: 38600,
    suffix: '+',
    label: 'Protocols Delivered',
    sublabel: 'Physician-reviewed prescriptions',
    icon: (
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
        <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18" />
      </svg>
    ),
  },
  {
    end: 50,
    suffix: '',
    label: 'States Licensed',
    sublabel: 'Nationwide telemedicine coverage',
    icon: (
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
        <polyline points="9 22 9 12 15 12 15 22" />
      </svg>
    ),
  },
  {
    end: 97,
    suffix: '%',
    label: 'Patient Satisfaction',
    sublabel: 'Would recommend to a friend',
    icon: (
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
      </svg>
    ),
  },
];

function useCountUp(end: number, duration: number, started: boolean) {
  const [count, setCount] = useState(0);

  useEffect(() => {
    if (!started) return;
    let startTime: number | null = null;
    const startVal = 0;

    const step = (timestamp: number) => {
      if (!startTime) startTime = timestamp;
      const progress = Math.min((timestamp - startTime) / duration, 1);
      // Ease out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      setCount(Math.floor(startVal + (end - startVal) * eased));
      if (progress < 1) requestAnimationFrame(step);
    };

    requestAnimationFrame(step);
  }, [end, duration, started]);

  return count;
}

function StatCard({ stat, started }: { stat: Stat; started: boolean }) {
  const count = useCountUp(stat.end, 1800, started);

  const formatNumber = (n: number) => {
    if (n >= 1000) return n.toLocaleString();
    return n.toString();
  };

  return (
    <div
      style={{
        background: 'rgba(255,255,255,0.03)',
        border: '1px solid rgba(201,168,76,0.12)',
        borderRadius: '20px',
        padding: '28px 24px',
        textAlign: 'center',
        transition: 'border-color 0.3s',
      }}
      onMouseEnter={(e) => (e.currentTarget.style.borderColor = 'rgba(201,168,76,0.3)')}
      onMouseLeave={(e) => (e.currentTarget.style.borderColor = 'rgba(201,168,76,0.12)')}
    >
      <div
        className="w-12 h-12 mx-auto mb-4 rounded-2xl flex items-center justify-center"
        style={{ background: 'rgba(201,168,76,0.08)', border: '1px solid rgba(201,168,76,0.15)' }}
      >
        {stat.icon}
      </div>
      <div
        style={{
          fontFamily: 'Cormorant Garamond, serif',
          fontSize: 'clamp(36px, 4vw, 52px)',
          fontWeight: 700,
          color: '#C9A84C',
          lineHeight: 1,
          letterSpacing: '-0.02em',
          marginBottom: '6px',
        }}
      >
        {stat.prefix || ''}{formatNumber(count)}{stat.suffix}
      </div>
      <div
        style={{
          fontFamily: 'DM Sans, system-ui, sans-serif',
          fontSize: '15px',
          fontWeight: 600,
          color: '#FFFFFF',
          marginBottom: '4px',
        }}
      >
        {stat.label}
      </div>
      <div
        style={{
          fontFamily: 'DM Sans, system-ui, sans-serif',
          fontSize: '12px',
          color: 'rgba(255,255,255,0.55)',
        }}
      >
        {stat.sublabel}
      </div>
    </div>
  );
}

export default function AnimatedStats() {
  const sectionRef = useRef<HTMLElement>(null);
  const [started, setStarted] = useState(false);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) {
          setStarted(true);
          observer.disconnect();
        }
      },
      { threshold: 0.25 }
    );
    if (sectionRef.current) observer.observe(sectionRef.current);
    return () => observer.disconnect();
  }, []);

  return (
    <section
      ref={sectionRef}
      id="stats"
      className="py-20 px-4 sm:px-6 lg:px-8"
      style={{ background: '#141414', borderTop: '1px solid rgba(255,255,255,0.05)' }}
    >
      <div className="max-w-6xl mx-auto">
        <div className="text-center mb-12">
          <span
            style={{
              fontFamily: 'JetBrains Mono, monospace',
              fontSize: '11px',
              letterSpacing: '0.12em',
              textTransform: 'uppercase',
              color: '#C9A84C',
              display: 'block',
              marginBottom: '12px',
            }}
          >
            By the Numbers
          </span>
          <h2
            style={{
              fontFamily: 'Cormorant Garamond, serif',
              fontSize: 'clamp(28px, 4vw, 44px)',
              fontWeight: 700,
              color: '#FFFFFF',
              lineHeight: 1.08,
              letterSpacing: '-0.02em',
            }}
          >
            The results speak{' '}
            <em style={{ color: '#C9A84C', fontStyle: 'italic' }}>for themselves.</em>
          </h2>
        </div>

        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {stats.map((stat) => (
            <StatCard key={stat.label} stat={stat} started={started} />
          ))}
        </div>

        <p
          className="text-center mt-6"
          style={{
            color: 'rgba(255,255,255,0.2)',
            fontSize: '10px',
            letterSpacing: '0.06em',
            fontFamily: 'JetBrains Mono, monospace',
          }}
        >
          * Data reflects ProtocolHRT patient metrics as of 2025
        </p>
      </div>
    </section>
  );
}
