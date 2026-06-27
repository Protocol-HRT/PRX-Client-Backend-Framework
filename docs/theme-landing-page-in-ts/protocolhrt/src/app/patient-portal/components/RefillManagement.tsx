'use client';
import React, { useState } from 'react';
import { createClient } from '@/lib/supabase/client';

interface Protocol {
  id: string;
  name: string;
  status: 'active' | 'pending' | 'paused';
  nextRefill: string;
  dosage: string;
  frequency: string;
  physician: string;
  category: 'TRT' | 'Peptide' | 'GLP-1';
}

interface RefillRequest {
  id: string;
  medication: string;
  requestedDate: string;
  status: 'approved' | 'pending' | 'requires_review';
  nextShipDate?: string;
  notes?: string;
}

interface RefillManagementProps {
  protocols: Protocol[];
  refills: RefillRequest[];
  loading: boolean;
  onRefillsUpdated: (newRefills: RefillRequest[]) => void;
}

function StatusPill({ status }: { status: string }) {
  const map: Record<string, { label: string; bg: string; color: string }> = {
    approved:        { label: 'Approved',        bg: 'rgba(90,138,94,0.12)',  color: '#3A7A3E' },
    pending:         { label: 'Pending Review',  bg: 'rgba(201,168,76,0.12)', color: '#A07820' },
    requires_review: { label: 'Needs Review',    bg: 'rgba(180,60,60,0.1)',   color: '#B43C3C' },
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

function getDaysUntilRefill(nextRefill: string): number {
  const today = new Date();
  const refillDate = new Date(nextRefill);
  return Math.ceil((refillDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
}

function ProtocolRefillCard({
  protocol,
  onRequestRefill,
  existingRefill,
}: {
  protocol: Protocol;
  onRequestRefill: (med: string) => Promise<void>;
  existingRefill?: RefillRequest;
}) {
  const [requesting, setRequesting] = useState(false);
  const [autoRefill, setAutoRefill] = useState(true);
  const days = getDaysUntilRefill(protocol.nextRefill);
  const urgent = days <= 7;
  const soon = days <= 14;

  const handleRequest = async () => {
    setRequesting(true);
    await onRequestRefill(protocol.name);
    setRequesting(false);
  };

  const alreadyRequested = existingRefill?.status === 'pending' || existingRefill?.status === 'approved';

  return (
    <div style={{ background: '#FFFFFF', border: `1px solid ${urgent ? 'rgba(180,60,60,0.15)' : 'rgba(0,0,0,0.07)'}`, borderRadius: '16px', padding: '20px 24px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '12px', flexWrap: 'wrap' as const, marginBottom: '16px' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '6px' }}>
            <CategoryBadge cat={protocol.category} />
            {urgent && (
              <span style={{ background: 'rgba(180,60,60,0.1)', color: '#B43C3C', fontSize: '10px', fontWeight: 600, padding: '2px 8px', borderRadius: '20px', fontFamily: 'DM Sans, system-ui, sans-serif', letterSpacing: '0.04em', textTransform: 'uppercase' as const }}>
                Refill Soon
              </span>
            )}
          </div>
          <h3 style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '16px', fontWeight: 600, color: '#1A1A1A', margin: '0 0 3px' }}>{protocol.name}</h3>
          <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '12px', color: '#5A8A5E', margin: 0 }}>{protocol.dosage} · {protocol.frequency}</p>
        </div>

        {/* Refill countdown */}
        <div style={{ textAlign: 'right' as const }}>
          <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '22px', fontWeight: 700, color: urgent ? '#B43C3C' : soon ? '#A07820' : '#3A7A3E', margin: 0, lineHeight: 1 }}>
            {days <= 0 ? 'Due' : days}
          </p>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#8A8A8A', margin: '2px 0 0' }}>
            {days <= 0 ? 'Overdue' : days === 1 ? 'day left' : 'days until refill'}
          </p>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#AAAAAA', margin: '2px 0 0' }}>{protocol.nextRefill}</p>
        </div>
      </div>

      {/* Progress bar */}
      <div style={{ marginBottom: '16px' }}>
        <div style={{ height: '4px', background: 'rgba(0,0,0,0.06)', borderRadius: '2px', overflow: 'hidden' }}>
          <div style={{
            height: '100%',
            borderRadius: '2px',
            background: urgent ? '#B43C3C' : soon ? '#C9A84C' : '#5A8A5E',
            width: `${Math.max(5, Math.min(100, 100 - (days / 30) * 100))}%`,
            transition: 'width 0.5s ease',
          }} />
        </div>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '4px' }}>
          <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#AAAAAA' }}>Supply used</span>
          <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#AAAAAA' }}>Refill: {protocol.nextRefill}</span>
        </div>
      </div>

      {/* Auto-refill toggle + action */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap' as const, gap: '10px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <button
            onClick={() => setAutoRefill(!autoRefill)}
            style={{
              width: '36px',
              height: '20px',
              borderRadius: '10px',
              background: autoRefill ? '#5A8A5E' : 'rgba(0,0,0,0.12)',
              border: 'none',
              cursor: 'pointer',
              position: 'relative',
              transition: 'background 0.2s ease',
              flexShrink: 0,
            }}
          >
            <span style={{ position: 'absolute', top: '2px', left: autoRefill ? '18px' : '2px', width: '16px', height: '16px', borderRadius: '50%', background: '#FFFFFF', boxShadow: '0 1px 2px rgba(0,0,0,0.2)', transition: 'left 0.2s ease' }} />
          </button>
          <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#4A4A4A' }}>Auto-refill {autoRefill ? 'on' : 'off'}</span>
        </div>

        {alreadyRequested ? (
          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <StatusPill status={existingRefill!.status} />
            {existingRefill?.nextShipDate && (
              <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#6A6A6A' }}>Ships {existingRefill.nextShipDate}</span>
            )}
          </div>
        ) : (
          <button
            onClick={handleRequest}
            disabled={requesting}
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
              background: requesting ? 'rgba(90,138,94,0.5)' : '#5A8A5E',
              color: '#FFFFFF',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '12px',
              fontWeight: 500,
              padding: '8px 16px',
              borderRadius: '50px',
              border: 'none',
              cursor: requesting ? 'not-allowed' : 'pointer',
              transition: 'background 0.2s ease',
            }}
          >
            {requesting ? (
              <>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ animation: 'spin 1s linear infinite' }}>
                  <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                Requesting…
              </>
            ) : 'Request Refill'}
          </button>
        )}
      </div>
    </div>
  );
}

function RefillHistoryRow({ refill }: { refill: RefillRequest }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr auto auto', gap: '12px', alignItems: 'center', padding: '14px 0', borderBottom: '1px solid rgba(0,0,0,0.04)' }}>
      <div>
        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', fontWeight: 500, color: '#1A1A1A', margin: '0 0 2px' }}>{refill.medication}</p>
        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#8A8A8A', margin: 0 }}>Requested {refill.requestedDate}</p>
        {refill.notes && <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#5A8A5E', fontStyle: 'italic', margin: '3px 0 0' }}>{refill.notes}</p>}
      </div>
      <StatusPill status={refill.status} />
      {refill.nextShipDate ? (
        <div style={{ textAlign: 'right' as const }}>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', textTransform: 'uppercase' as const, letterSpacing: '0.06em', margin: '0 0 2px' }}>Ships</p>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', fontWeight: 600, color: '#1A1A1A', margin: 0 }}>{refill.nextShipDate}</p>
        </div>
      ) : <div />}
    </div>
  );
}

function LoadingSkeleton() {
  return (
    <div style={{ display: 'grid', gap: '12px' }}>
      {[1, 2, 3].map((i) => (
        <div key={i} style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '20px 24px', height: '140px', animation: 'pulse 1.5s ease-in-out infinite' }} />
      ))}
      <style>{`@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }`}</style>
    </div>
  );
}

export default function RefillManagement({ protocols, refills, loading, onRefillsUpdated }: RefillManagementProps) {
  const [requestingAll, setRequestingAll] = useState(false);
  const [allRequested, setAllRequested] = useState(false);

  const handleSingleRefill = async (medication: string) => {
    const supabase = createClient();
    const { data: { user } } = await supabase.auth.getUser();
    if (!user) return;

    const today = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const { data: newRefill } = await supabase
      .from('patient_refills')
      .insert({ user_id: user.id, medication, requested_date: today, status: 'pending', notes: 'Submitted via patient portal' })
      .select()
      .single();

    if (newRefill) {
      onRefillsUpdated([{
        id: newRefill.id,
        medication: newRefill.medication,
        requestedDate: newRefill.requested_date,
        status: newRefill.status,
        nextShipDate: newRefill.next_ship_date ?? undefined,
        notes: newRefill.notes ?? undefined,
      }, ...refills]);
    }
  };

  const handleRequestAll = async () => {
    setRequestingAll(true);
    const supabase = createClient();
    const { data: { user } } = await supabase.auth.getUser();
    if (!user) { setRequestingAll(false); return; }

    const today = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const inserts = protocols.map((p) => ({
      user_id: user.id,
      medication: p.name,
      requested_date: today,
      status: 'pending',
      notes: 'Submitted via patient portal',
    }));

    const { data: newRefills } = await supabase.from('patient_refills').insert(inserts).select();
    if (newRefills) {
      onRefillsUpdated([
        ...newRefills.map((r: any) => ({
          id: r.id,
          medication: r.medication,
          requestedDate: r.requested_date,
          status: r.status,
          nextShipDate: r.next_ship_date ?? undefined,
          notes: r.notes ?? undefined,
        })),
        ...refills,
      ]);
    }
    setRequestingAll(false);
    setAllRequested(true);
  };

  const getExistingRefill = (medName: string) =>
    refills.find((r) => r.medication.toLowerCase().includes(medName.toLowerCase().split(' ')[0]));

  const urgentCount = protocols.filter((p) => getDaysUntilRefill(p.nextRefill) <= 7).length;

  return (
    <section>
      <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: '24px', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', color: '#C9A84C', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '4px' }}>Prescriptions</p>
          <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '30px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2, margin: 0 }}>
            Refill Management
          </h2>
        </div>
        {!allRequested ? (
          <button
            onClick={handleRequestAll}
            disabled={requestingAll || loading || protocols.length === 0}
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
              background: requestingAll ? 'rgba(90,138,94,0.5)' : '#5A8A5E',
              color: '#FFFFFF',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '13px',
              fontWeight: 500,
              padding: '10px 20px',
              borderRadius: '50px',
              border: 'none',
              cursor: requestingAll || loading || protocols.length === 0 ? 'not-allowed' : 'pointer',
              transition: 'background 0.2s ease',
            }}
          >
            {requestingAll ? (
              <>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ animation: 'spin 1s linear infinite' }}>
                  <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                Submitting…
              </>
            ) : (
              <>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M12 5v14M5 12h14"/>
                </svg>
                Request All Refills
              </>
            )}
          </button>
        ) : (
          <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#3A7A3E', fontWeight: 500, display: 'flex', alignItems: 'center', gap: '5px' }}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            All refills requested
          </span>
        )}
      </div>

      {/* Urgent alert */}
      {urgentCount > 0 && !loading && (
        <div style={{ background: 'rgba(180,60,60,0.05)', border: '1px solid rgba(180,60,60,0.15)', borderRadius: '12px', padding: '14px 18px', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '10px' }}>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B43C3C" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
          </svg>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#7A2A2A', margin: 0 }}>
            <strong>{urgentCount} prescription{urgentCount > 1 ? 's' : ''}</strong> due for refill within 7 days. Request now to avoid a gap in your protocol.
          </p>
        </div>
      )}

      {/* Protocol refill cards */}
      <div style={{ marginBottom: '32px' }}>
        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#8A8A8A', textTransform: 'uppercase', letterSpacing: '0.07em', fontWeight: 600, marginBottom: '12px' }}>Your Protocols</p>
        {loading ? (
          <LoadingSkeleton />
        ) : protocols.length === 0 ? (
          <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '32px 24px', textAlign: 'center' }}>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', color: '#8A8A8A' }}>No active protocols found.</p>
          </div>
        ) : (
          <div style={{ display: 'grid', gap: '12px' }}>
            {protocols.map((p) => (
              <ProtocolRefillCard
                key={p.id}
                protocol={p}
                onRequestRefill={handleSingleRefill}
                existingRefill={getExistingRefill(p.name)}
              />
            ))}
          </div>
        )}
      </div>

      {/* Refill History */}
      <div>
        <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#8A8A8A', textTransform: 'uppercase', letterSpacing: '0.07em', fontWeight: 600, marginBottom: '12px' }}>Refill History</p>
        {loading ? (
          <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '20px 24px', height: '120px', animation: 'pulse 1.5s ease-in-out infinite' }}>
            <style>{`@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }`}</style>
          </div>
        ) : refills.length === 0 ? (
          <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '24px', textAlign: 'center' }}>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', color: '#8A8A8A' }}>No refill history yet.</p>
          </div>
        ) : (
          <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '4px 24px 8px' }}>
            {refills.map((r) => (
              <RefillHistoryRow key={r.id} refill={r} />
            ))}
          </div>
        )}
      </div>

      <style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>
    </section>
  );
}
