'use client';
import React, { useEffect, useRef } from 'react';
import AppImage from '@/components/ui/AppImage';
import { openIntakeModal } from '@/lib/openIntakeModal';

const himProtocols = [
{
  tag: 'HORMONES',
  badge: 'Most Popular',
  title: 'Testosterone Optimization',
  body: "Low-T isn't just about libido. It affects your muscle mass, mental clarity, mood, and drive. Our TRT protocols are physician-designed, lab-verified, and built around your bloodwork, not a generic dose."
},
{
  tag: 'PERFORMANCE',
  badge: 'Elite Protocol',
  title: 'Peptide Therapy',
  body: 'BPC-157, TB-500, CJC-1295, Ipamorelin: precision peptide stacks for accelerated recovery, lean muscle growth, and cellular regeneration. The same compounds elite athletes have used for years.'
},
{
  tag: 'METABOLIC',
  badge: 'Transform',
  title: 'Body Recomposition',
  body: 'Lose fat. Build muscle. Optimize metabolic function. Our recomposition protocols combine hormone optimization with evidence-based metabolic compounds for measurable body transformation.'
},
{
  tag: 'VITALITY',
  badge: 'Restore',
  title: 'Sexual Health & Vitality',
  body: 'Restore libido, performance, and confidence with targeted protocols addressing the root hormonal causes, not surface-level symptoms.'
}];


export default function HimSection() {
  const sectionRef = useRef<HTMLElement>(null);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -60px 0px' }
    );
    sectionRef.current?.querySelectorAll('.reveal-fade, .reveal-left, .reveal-right, .reveal-scale, .stagger-grid, .stagger-cards').forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, []);

  const scrollToSection = (id: string) => {
    document.querySelector(id)?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <section
      id="him"
      ref={sectionRef}
      className="py-24 lg:py-32 px-5 sm:px-8 lg:px-10"
      style={{ background: '#141414', borderTop: '1px solid rgba(255,255,255,0.05)' }}>
      
      <div className="max-w-7xl mx-auto">
        <div className="reveal-fade mb-6">
          <span className="editorial-tag">For Him</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-10 items-start">
          {/* Left: Headline + CTA + Photo */}
          <div className="lg:col-span-1">
            <h2
              className="font-display font-bold reveal-fade reveal-delay-1 mb-5"
              style={{
                color: '#FFFFFF',
                fontSize: 'clamp(38px, 4.5vw, 58px)',
                lineHeight: '1.04',
                letterSpacing: '-0.025em',
                fontFamily: 'Cormorant Garamond, serif'
              }}>
              
              Reclaim your{' '}
              <em style={{ color: '#C9A84C', fontStyle: 'italic' }}>edge.</em>
            </h2>

            <p
              className="reveal-fade reveal-delay-2 mb-8"
              style={{
                color: 'rgba(255,255,255,0.55)',
                fontSize: 'clamp(15px, 1.5vw, 17px)',
                lineHeight: '1.7',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 400
              }}>
              
              Built for men who refuse to accept fatigue, decline, and mediocrity as inevitable.
              Your hormones drive everything: your strength, drive, clarity, confidence.
              We put you back in control.
            </p>

            <div
              className="reveal-fade reveal-delay-2 mb-8 overflow-hidden ambassador-photo-frame"
              style={{ borderRadius: '20px', height: '340px', border: '1px solid rgba(201,168,76,0.15)' }}>
              
              {/* ORIGINAL IMAGE (revert): src="https://img.rocket.new/generatedImages/rocket_gen_img_1909e16ae-1772206338141.png" */}
              <AppImage
                src="/assets/images/him_section_attractive_man.png"
                alt="Shirtless athletic man with lean muscular physique in a luxury gym setting, the aspirational result of testosterone and hormone optimization"
                width={500}
                height={400}
                className="w-full h-full object-cover" />
              
            </div>

            <div className="reveal-fade reveal-delay-3">
              <button
                className="btn-gold"
                onClick={() => openIntakeModal()}
                style={{ height: '52px', minWidth: '220px' }}>
                
                Build My HIM Protocol
              </button>
            </div>
          </div>

          {/* Right: Protocol Cards */}
          <div className="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 stagger-cards">
            {himProtocols.map((p) =>
            <div
              key={p.title}
              className="dark-card card-shine"
              style={{ padding: '28px' }}>
              
                <div className="flex items-center justify-between mb-4">
                  <span
                  style={{
                    fontFamily: 'JetBrains Mono, monospace',
                    fontSize: '10px',
                    fontWeight: 500,
                    letterSpacing: '0.1em',
                    textTransform: 'uppercase' as const,
                    color: '#C9A84C'
                  }}>
                  
                    {p.tag}
                  </span>
                  <span
                  style={{
                    background: 'rgba(201,168,76,0.1)',
                    border: '1px solid rgba(201,168,76,0.25)',
                    color: '#C9A84C',
                    fontSize: '11px',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontWeight: 500,
                    padding: '3px 10px',
                    borderRadius: '20px'
                  }}>
                  
                    {p.badge}
                  </span>
                </div>
                <h3
                className="font-display font-bold mb-3"
                style={{ color: '#FFFFFF', fontSize: '21px', fontFamily: 'Cormorant Garamond, serif' }}>
                
                  {p.title}
                </h3>
                <p
                style={{
                  color: 'rgba(255,255,255,0.55)',
                  fontSize: '14px',
                  lineHeight: '1.7',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontWeight: 400
                }}>
                
                  {p.body}
                </p>
              </div>
            )}
          </div>
        </div>
      </div>
    </section>);

}