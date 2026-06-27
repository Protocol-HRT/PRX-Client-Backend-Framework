'use client';
import React, { useEffect, useRef } from 'react';

const formularyStats = [
  { number: '19', label: 'Advanced Compounds' },
  { number: '33', label: 'Precision SKUs' },
  { number: '10,000+', label: 'Clinical Studies Referenced' },
];

const categories = [
  {
    tag: 'PERFORMANCE',
    title: 'Peptide Therapy',
    desc: 'BPC-157, TB-500, CJC-1295, Ipamorelin and more.',
    sub: 'Recovery & Regeneration',
  },
  {
    tag: 'HORMONES',
    title: 'Hormone Optimization',
    desc: 'TRT, estrogen balance, thyroid optimization, cortisol management.',
    sub: 'Testosterone & Estrogen',
  },
  {
    tag: 'METABOLIC',
    title: 'Metabolic & Weight Loss',
    desc: 'GLP-1 support, metabolic accelerators, recomposition stacks.',
    sub: 'Fat Loss & Recomposition',
  },
  {
    tag: 'COGNITIVE',
    title: 'Nootropic Stacks',
    desc: 'Focus, memory, neuroplasticity, stress resilience protocols.',
    sub: 'Focus & Mental Clarity',
  },
  {
    tag: 'LONGEVITY',
    title: 'Longevity & Anti-Aging',
    desc: 'NAD+, senolytics, longevity compounds.',
    sub: 'Cellular Longevity',
  },
  {
    tag: 'PERFORMANCE',
    title: 'Athletic Performance',
    desc: 'Strength, endurance, VO2 max, recovery protocols.',
    sub: 'Strength & Endurance',
  },
  {
    tag: 'VITALITY',
    title: 'Sexual Health',
    desc: "Men\'s and women\'s targeted protocols. Hormonal balance at the root.",
    sub: 'Men & Women',
  },
  {
    tag: 'FOUNDATION',
    title: 'Core Supplement Stacks',
    desc: 'Evidence-based foundational compounds. No fillers.',
    sub: 'Foundation Protocols',
  },
];

export default function FormularySection() {
  const sectionRef = useRef<HTMLElement>(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
      },
      { threshold: 0.08, rootMargin: '0px 0px -60px 0px' }
    );
    sectionRef?.current?.querySelectorAll('.reveal-fade, .reveal-left, .reveal-right, .reveal-scale, .stagger-grid, .stagger-cards')?.forEach((el) => observer?.observe(el));
    return () => observer?.disconnect();
  }, []);

  return (
    <section
      id="formulary"
      ref={sectionRef}
      className="py-24 lg:py-32 px-4 sm:px-6 lg:px-8"
      style={{ background: '#F7F4F0', borderTop: '1px solid rgba(56,49,44,0.06)' }}
    >
      <div className="max-w-7xl mx-auto">
        {/* Section Label */}
        <div className="reveal-fade mb-5">
          <span className="section-label">06 / The Formulary</span>
        </div>

        {/* Headline */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
          <div>
            <h2
              className="font-display font-bold reveal-fade reveal-delay-1"
              style={{
                color: '#38312C',
                fontSize: 'clamp(34px, 5vw, 52px)',
                lineHeight: '1.05',
                letterSpacing: '-0.01em',
                fontFamily: 'Cormorant Garamond, serif',
              }}
            >
              Every protocol.{' '}
              <span style={{ color: '#779D7C' }}>One platform.</span>
            </h2>
          </div>
          <div className="flex flex-col justify-end">
            <p
              className="font-body reveal-fade reveal-delay-2"
              style={{ color: '#5C5248', fontSize: '17px', lineHeight: '1.75', fontFamily: 'Red Hat Text, sans-serif' }}
            >
              19 advanced compounds. 33 precision SKUs. The most comprehensive hormone and peptide formulary
              in telemedicine, all physician-reviewed, all evidence-based.
            </p>
          </div>
        </div>

        {/* Stat Row */}
        <div
          className="reveal-fade reveal-delay-3 flex flex-wrap gap-8 mb-12 pb-10"
          style={{ borderBottom: '1px solid rgba(56,49,44,0.08)' }}
        >
          {formularyStats?.map((s) => (
            <div key={s?.label} className="flex items-baseline gap-3">
              <span
                className="font-display font-bold"
                style={{ color: '#38312C', fontSize: '40px', lineHeight: 1, fontFamily: 'Cormorant Garamond, serif' }}
              >
                {s?.number}
              </span>
              <span
                className="font-body"
                style={{ color: '#5C5248', fontSize: '14px', fontFamily: 'Red Hat Text, sans-serif' }}
              >
                {s?.label}
              </span>
            </div>
          ))}
        </div>

        {/* Category Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10 stagger-cards">
          {categories?.map((cat) => (
            <div key={cat?.title} className="formulary-card card-shine">
              <span className="protocol-tag mb-3 inline-block">{cat?.tag}</span>
              <h3
                className="font-display font-bold mb-2"
                style={{ color: '#38312C', fontSize: '18px', fontFamily: 'Cormorant Garamond, serif' }}
              >
                {cat?.title}
              </h3>
              <p
                className="font-body mb-3"
                style={{ color: '#5C5248', fontSize: '13px', lineHeight: '1.6', fontFamily: 'Red Hat Text, sans-serif' }}
              >
                {cat?.desc}
              </p>
              <span
                className="font-mono text-xs"
                style={{ color: '#9B9189', fontSize: '10px', letterSpacing: '0.08em' }}
              >
                {cat?.sub}
              </span>
            </div>
          ))}
        </div>

        {/* CTA */}
        <div className="reveal-fade">
          <button
            className="btn-outline-green"
            style={{ height: '52px', minWidth: '240px' }}
          >
            View Full Formulary →
          </button>
        </div>
      </div>
    </section>
  );
}