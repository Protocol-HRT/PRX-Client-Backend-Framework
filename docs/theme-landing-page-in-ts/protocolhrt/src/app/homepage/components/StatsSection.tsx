'use client';
import React, { useEffect, useRef } from 'react';
import AppImage from '@/components/ui/AppImage';

const stats = [
{
  number: '6x',
  label: 'More effective than diet and exercise alone',
  icon:
  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
      </svg>

},
{
  number: '18%',
  label: 'Average improvement in key health biomarkers',
  icon:
  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
        <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
        <polyline points="16 7 22 7 22 13" />
      </svg>

},
{
  number: '93%',
  label: 'Maintained results for the long term',
  icon:
  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
      </svg>

}];


export default function StatsSection() {
  const sectionRef = useRef<HTMLElement>(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
      },
      { threshold: 0.1 }
    );
    sectionRef?.current?.querySelectorAll('.reveal-fade, .reveal-left, .reveal-right, .reveal-scale, .stagger-grid, .stagger-cards')?.forEach((el) => observer?.observe(el));
    return () => observer?.disconnect();
  }, []);

  return (
    <section
      ref={sectionRef}
      className="py-20 px-4 sm:px-6 lg:px-8"
      style={{ background: '#141414', borderTop: '1px solid rgba(255,255,255,0.05)' }}>
      
      <div className="max-w-7xl mx-auto">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center">
          {/* Left: Headline */}
          <div>
            <div className="reveal-fade mb-6">
              <span className="editorial-tag">The change we&apos;ve all been waiting for.</span>
            </div>
            <h2
              className="font-display font-bold reveal-fade reveal-delay-1 mb-5"
              style={{
                color: '#FFFFFF',
                fontSize: 'clamp(30px, 4vw, 48px)',
                lineHeight: '1.06',
                letterSpacing: '-0.02em',
                fontFamily: 'Cormorant Garamond, serif'
              }}>
              
              We will fix your{' '}
              <em style={{ color: '#C9A84C', fontStyle: 'italic' }}>broken metabolism</em>{' '}
              and optimize your biology.
            </h2>
            <p
              className="reveal-fade reveal-delay-2 mb-8"
              style={{
                color: 'rgba(255,255,255,0.5)',
                fontSize: '16px',
                lineHeight: '1.75',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                maxWidth: '440px'
              }}>
              
              Traditional approaches don&apos;t work because nearly 70% of hormonal decline is genetically determined.
              With our protocols, you will work{' '}
              <strong style={{ color: 'rgba(255,255,255,0.8)' }}>with your body</strong>{' '}
              rather than against it.
            </p>

            {/* Lifestyle photo pair */}
            <div className="reveal-fade reveal-delay-3 grid grid-cols-2 gap-3">
              <div className="overflow-hidden ambassador-photo-frame" style={{ borderRadius: '20px', height: '200px', border: '1px solid rgba(201,168,76,0.1)' }}>
                <AppImage
                  src="https://images.unsplash.com/photo-1584952449358-f2e2191ef094"
                  alt="Healthy man with strong physique showing results of hormone optimization treatment"
                  width={300}
                  height={250}
                  className="w-full h-full object-cover" />
                
              </div>
              <div className="overflow-hidden ambassador-photo-frame" style={{ borderRadius: '20px', height: '200px', border: '1px solid rgba(201,168,76,0.1)' }}>
                <AppImage
                  src="https://img.rocket.new/generatedImages/rocket_gen_img_126a024c8-1775474453215.png"
                  alt="Fit healthy woman with toned body demonstrating vitality and energy from hormone therapy"
                  width={300}
                  height={250}
                  className="w-full h-full object-cover" />
                
              </div>
            </div>
          </div>

          {/* Right: Stats */}
          <div className="grid grid-cols-1 gap-4 stagger-cards">
            {stats?.map((stat) =>
            <div
              key={stat?.label}
              className="flex items-center gap-5 p-5 rounded-2xl"
              style={{
                background: 'rgba(255,255,255,0.03)',
                border: '1px solid rgba(201,168,76,0.12)'
              }}>
              
                <div
                className="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0"
                style={{ background: 'rgba(201,168,76,0.08)', border: '1px solid rgba(201,168,76,0.15)' }}>
                
                  {stat?.icon}
                </div>
                <div>
                  <div
                  className="font-display font-bold"
                  style={{ color: '#C9A84C', fontSize: '34px', lineHeight: 1, fontFamily: 'Cormorant Garamond, serif' }}>
                  
                    {stat?.number}
                  </div>
                  <div
                  style={{ color: 'rgba(255,255,255,0.5)', fontSize: '14px', marginTop: '4px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
                  
                    {stat?.label}
                  </div>
                </div>
              </div>
            )}
            <p
              style={{ color: 'rgba(255,255,255,0.2)', fontSize: '10px', letterSpacing: '0.06em', fontFamily: 'JetBrains Mono, monospace' }}>
              
              * Data based on ProtocolHRT patients over their first 6 months of treatment
            </p>
          </div>
        </div>
      </div>
    </section>);

}