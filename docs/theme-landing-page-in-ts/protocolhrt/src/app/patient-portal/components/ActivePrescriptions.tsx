'use client';
import React, { useState } from 'react';

interface Protocol {
  id: string;
  name: string;
  status: 'active' | 'pending' | 'paused';
  startDate: string;
  nextRefill: string;
  dosage: string;
  frequency: string;
  physician: string;
  category: 'TRT' | 'Peptide' | 'GLP-1';
}

interface ActivePrescriptionsProps {
  protocols: Protocol[];
  loading: boolean;
}

function StatusPill({ status }: { status: string }) {
  const map: Record<string, { label: string; bg: string; color: string }> = {
    active:  { label: 'Active',  bg: 'rgba(90,138,94,0.12)',  color: '#3A7A3E' },
    pending: { label: 'Pending', bg: 'rgba(201,168,76,0.12)', color: '#A07820' },
    paused:  { label: 'Paused',  bg: 'rgba(0,0,0,0.06)',      color: '#6A6A6A' },
  };
  const s = map[status] ?? { label: status, bg: 'rgba(0,0,0,0.06)', color: '#6A6A6A' };
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', padding: '3px 10px', borderRadius: '20px', fontSize: '11px', fontWeight: 600, letterSpacing: '0.04em', textTransform: 'uppercase' as const, background: s.bg, color: s.color, fontFamily: 'DM Sans, system-ui, sans-serif' }}>
      {s.label}
    </span>
  );
}

function CategoryBadge({ cat }: { cat: Protocol['category'] }) {
  const map: Record<string, { bg: string; color: string }> = {
    TRT:     { bg: 'rgba(201,168,76,0.12)', color: '#A07820' },
    Peptide: { bg: 'rgba(90,90,138,0.1)',   color: '#5A5A8A' },
    'GLP-1': { bg: 'rgba(90,138,94,0.12)',  color: '#3A7A3E' },
  };
  const s = map[cat] ?? { bg: 'rgba(0,0,0,0.06)', color: '#6A6A6A' };
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', padding: '2px 8px', borderRadius: '4px', fontSize: '10px', fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase' as const, background: s.bg, color: s.color, fontFamily: 'JetBrains Mono, monospace' }}>
      {cat}
    </span>
  );
}

const ADMIN_ROUTES: Record<string, string> = {
  'Testosterone Cypionate': 'Intramuscular injection',
  'Anastrozole': 'Oral tablet',
  'Sermorelin / GHRP-2': 'Subcutaneous injection',
  'Semaglutide': 'Subcutaneous injection',
  'BPC-157': 'Subcutaneous injection',
  'Tirzepatide': 'Subcutaneous injection',
};

const STORAGE_NOTES: Record<string, string> = {
  'Testosterone Cypionate': 'Store at room temperature (68–77°F). Keep away from light.',
  'Anastrozole': 'Store at room temperature. Keep in original container.',
  'Sermorelin / GHRP-2': 'Refrigerate after reconstitution (36–46°F). Use within 30 days.',
  'Semaglutide': 'Refrigerate (36–46°F). Do not freeze.',
  'BPC-157': 'Refrigerate after reconstitution. Use within 30 days.',
  'Tirzepatide': 'Refrigerate (36–46°F). Do not freeze.',
};

function getDaysUntilRefill(nextRefill: string): number {
  const today = new Date();
  const refillDate = new Date(nextRefill);
  const diff = Math.ceil((refillDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
  return diff;
}

function RefillCountdown({ nextRefill }: { nextRefill: string }) {
  const days = getDaysUntilRefill(nextRefill);
  const urgent = days <= 7;
  const soon = days <= 14;
  const color = urgent ? '#B43C3C' : soon ? '#A07820' : '#3A7A3E';
  const bg = urgent ? 'rgba(180,60,60,0.08)' : soon ? 'rgba(201,168,76,0.08)' : 'rgba(90,138,94,0.08)';

  return (
    <div style={{ background: bg, border: `1px solid ${color}22`, borderRadius: '10px', padding: '10px 14px', display: 'flex', alignItems: 'center', gap: '8px' }}>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
      </svg>
      <div>
        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color, fontWeight: 600, margin: 0 }}>
          {days <= 0 ? 'Refill overdue' : days === 1 ? 'Refill due tomorrow' : `Refill in ${days} days`}
        </p>
        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#8A8A8A', margin: 0 }}>{nextRefill}</p>
      </div>
    </div>
  );
}

function PrescriptionCard({ protocol }: { protocol: Protocol }) {
  const [expanded, setExpanded] = useState(false);
  const adminRoute = ADMIN_ROUTES[protocol.name] ?? 'See prescribing instructions';
  const storageNote = STORAGE_NOTES[protocol.name] ?? 'Store per label instructions.';

  return (
    <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', overflow: 'hidden' }}>
      {/* Card Header */}
      <div style={{ padding: '22px 24px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '12px', flexWrap: 'wrap' as const }}>
          <div style={{ flex: 1 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px', flexWrap: 'wrap' as const }}>
              <CategoryBadge cat={protocol.category} />
              <StatusPill status={protocol.status} />
            </div>
            <h3 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '22px', fontWeight: 600, color: '#1A1A1A', margin: '0 0 4px', letterSpacing: '-0.01em' }}>
              {protocol.name}
            </h3>
            <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '13px', color: '#5A8A5E', margin: 0 }}>
              {protocol.dosage} · {protocol.frequency}
            </p>
          </div>
          <RefillCountdown nextRefill={protocol.nextRefill} />
        </div>

        {/* Key Info Grid */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: '16px', marginTop: '20px', paddingTop: '20px', borderTop: '1px solid rgba(0,0,0,0.05)' }}>
          <div>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', textTransform: 'uppercase' as const, letterSpacing: '0.07em', marginBottom: '3px' }}>Administration</p>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#1A1A1A', fontWeight: 500 }}>{adminRoute}</p>
          </div>
          <div>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', textTransform: 'uppercase' as const, letterSpacing: '0.07em', marginBottom: '3px' }}>Prescribing Physician</p>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#1A1A1A', fontWeight: 500 }}>{protocol.physician}</p>
          </div>
          <div>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', textTransform: 'uppercase' as const, letterSpacing: '0.07em', marginBottom: '3px' }}>Protocol Start</p>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#1A1A1A', fontWeight: 500 }}>{protocol.startDate}</p>
          </div>
        </div>
      </div>

      {/* Expand Toggle */}
      <button
        onClick={() => setExpanded(!expanded)}
        style={{ width: '100%', background: 'rgba(0,0,0,0.02)', border: 'none', borderTop: '1px solid rgba(0,0,0,0.05)', padding: '10px 24px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', cursor: 'pointer', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#6A6A6A', fontWeight: 500 }}
      >
        <span>{expanded ? 'Hide details' : 'View dosage instructions & storage'}</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8A8A8A" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ transform: expanded ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease' }}>
          <path d="m6 9 6 6 6-6"/>
        </svg>
      </button>

      {/* Expanded Details */}
      {expanded && (
        <div style={{ padding: '20px 24px', borderTop: '1px solid rgba(0,0,0,0.05)', display: 'grid', gap: '16px' }}>
          <div style={{ background: 'rgba(90,138,94,0.04)', border: '1px solid rgba(90,138,94,0.12)', borderRadius: '10px', padding: '14px 16px' }}>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#5A8A5E', textTransform: 'uppercase' as const, letterSpacing: '0.07em', fontWeight: 600, marginBottom: '6px' }}>Dosage Instructions</p>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#2A2A2A', lineHeight: 1.6, margin: 0 }}>
              Take {protocol.dosage} via {adminRoute.toLowerCase()} {protocol.frequency.toLowerCase()}. Follow your physician's specific instructions. Do not adjust dose without consulting your care team.
            </p>
          </div>
          <div style={{ background: 'rgba(201,168,76,0.04)', border: '1px solid rgba(201,168,76,0.12)', borderRadius: '10px', padding: '14px 16px' }}>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#A07820', textTransform: 'uppercase' as const, letterSpacing: '0.07em', fontWeight: 600, marginBottom: '6px' }}>Storage</p>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#2A2A2A', lineHeight: 1.6, margin: 0 }}>{storageNote}</p>
          </div>
          <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' as const }}>
            <button style={{ background: 'none', border: '1px solid rgba(0,0,0,0.1)', borderRadius: '8px', padding: '8px 16px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 500, color: '#4A4A4A', cursor: 'pointer' }}>
              Download Rx Label
            </button>
            <button style={{ background: 'none', border: '1px solid rgba(0,0,0,0.1)', borderRadius: '8px', padding: '8px 16px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 500, color: '#4A4A4A', cursor: 'pointer' }}>
              Message Physician
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <div style={{ display: 'grid', gap: '12px' }}>
      {[1, 2, 3].map((i) => (
        <div key={i} style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '22px 24px', height: '140px', animation: 'pulse 1.5s ease-in-out infinite' }} />
      ))}
      <style>{`@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }`}</style>
    </div>
  );
}

export default function ActivePrescriptions({ protocols, loading }: ActivePrescriptionsProps) {
  const activeCount = protocols.filter((p) => p.status === 'active').length;

  return (
    <section>
      <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: '24px', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', color: '#C9A84C', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '4px' }}>Prescriptions</p>
          <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '30px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2, margin: 0 }}>
            Active Prescriptions
          </h2>
        </div>
        <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '10px', padding: '8px 16px', textAlign: 'center' }}>
          <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '18px', fontWeight: 700, color: '#5A8A5E', margin: 0 }}>{loading ? '…' : activeCount}</p>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', margin: 0, marginTop: '1px' }}>Active Rx</p>
        </div>
      </div>

      {/* Notice Banner */}
      <div style={{ background: 'rgba(201,168,76,0.06)', border: '1px solid rgba(201,168,76,0.2)', borderRadius: '12px', padding: '14px 18px', marginBottom: '20px', display: 'flex', alignItems: 'flex-start', gap: '10px' }}>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0, marginTop: '1px' }}>
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#6A5A2A', lineHeight: 1.5, margin: 0 }}>
          All prescriptions are reviewed and approved by a licensed physician. Do not adjust dosage without consulting your care team. Questions? Message your physician directly from any prescription card.
        </p>
      </div>

      {loading ? (
        <LoadingSkeleton />
      ) : protocols.length === 0 ? (
        <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '48px 24px', textAlign: 'center' }}>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#CCCCCC" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ margin: '0 auto 12px' }}>
            <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v11m0 0H5a2 2 0 0 1-2-2V9m6 5h10a2 2 0 0 0 2-2V9m0 0H3"/>
          </svg>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '15px', color: '#4A4A4A', fontWeight: 500, marginBottom: '6px' }}>No active prescriptions</p>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#8A8A8A' }}>Your prescriptions will appear here once your physician approves your protocol.</p>
        </div>
      ) : (
        <div style={{ display: 'grid', gap: '14px' }}>
          {protocols.map((p) => (
            <PrescriptionCard key={p.id} protocol={p} />
          ))}
        </div>
      )}
    </section>
  );
}
