'use client';
import React, { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import { createClient } from '@/lib/supabase/client';
import { useAuth } from '@/contexts/AuthContext';
import { triggerPhysicianApproved, triggerOrderShipped } from '@/lib/n8n/webhooks';
import HRTRecommendationPanel from './components/HRTRecommendationPanel';
import ActivePrescriptions from './components/ActivePrescriptions';
import LabResultsHistory from './components/LabResultsHistory';
import AccountSettings from './components/AccountSettings';
import RefillManagement from './components/RefillManagement';
import ClinicalIntakeForms from './components/ClinicalIntakeForms';

// ─── Types ────────────────────────────────────────────────────────────────────

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

interface Order {
  id: string;
  date: string;
  items: string[];
  total: number;
  status: 'delivered' | 'shipped' | 'processing' | 'pending';
}

interface Shipment {
  id: string;
  medication: string;
  carrier: string;
  trackingNumber: string;
  status: 'delivered' | 'out_for_delivery' | 'in_transit' | 'label_created';
  estimatedDelivery: string;
  lastUpdate: string;
}

interface RefillRequest {
  id: string;
  medication: string;
  requestedDate: string;
  status: 'approved' | 'pending' | 'requires_review';
  nextShipDate?: string;
  notes?: string;
}

// ─── Helper Components ────────────────────────────────────────────────────────

function StatusPill({ status }: { status: string }) {
  const map: Record<string, { label: string; bg: string; color: string }> = {
    active:           { label: 'Active',            bg: 'rgba(90,138,94,0.12)',  color: '#3A7A3E' },
    pending:          { label: 'Pending',            bg: 'rgba(201,168,76,0.12)', color: '#A07820' },
    paused:           { label: 'Paused',             bg: 'rgba(0,0,0,0.06)',      color: '#6A6A6A' },
    delivered:        { label: 'Delivered',          bg: 'rgba(90,138,94,0.12)',  color: '#3A7A3E' },
    shipped:          { label: 'Shipped',            bg: 'rgba(90,138,94,0.12)',  color: '#3A7A3E' },
    processing:       { label: 'Processing',         bg: 'rgba(201,168,76,0.12)', color: '#A07820' },
    approved:         { label: 'Approved',           bg: 'rgba(90,138,94,0.12)',  color: '#3A7A3E' },
    requires_review:  { label: 'Requires Review',   bg: 'rgba(180,60,60,0.1)',   color: '#B43C3C' },
    in_transit:       { label: 'In Transit',         bg: 'rgba(90,138,94,0.12)',  color: '#3A7A3E' },
    out_for_delivery: { label: 'Out for Delivery',  bg: 'rgba(90,138,94,0.18)',  color: '#2A6A2E' },
    label_created:    { label: 'Label Created',      bg: 'rgba(0,0,0,0.06)',      color: '#6A6A6A' },
    'follow-up':      { label: 'Follow-Up',          bg: 'rgba(90,138,94,0.12)',  color: '#3A7A3E' },
    'check-in':       { label: 'Check-In',           bg: 'rgba(201,168,76,0.12)', color: '#A07820' },
    initial:          { label: 'Initial Consult',    bg: 'rgba(90,90,138,0.1)',   color: '#5A5A8A' },
  };
  const s = map[status] ?? { label: status, bg: 'rgba(0,0,0,0.06)', color: '#6A6A6A' };
  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        padding: '3px 10px',
        borderRadius: '20px',
        fontSize: '11px',
        fontWeight: 600,
        letterSpacing: '0.04em',
        textTransform: 'uppercase',
        background: s.bg,
        color: s.color,
        fontFamily: 'DM Sans, system-ui, sans-serif',
      }}
    >
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
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        padding: '2px 8px',
        borderRadius: '4px',
        fontSize: '10px',
        fontWeight: 700,
        letterSpacing: '0.06em',
        textTransform: 'uppercase',
        background: s.bg,
        color: s.color,
        fontFamily: 'JetBrains Mono, monospace',
      }}
    >
      {cat}
    </span>
  );
}

function LoadingSkeleton() {
  return (
    <div style={{ display: 'grid', gap: '12px' }}>
      {[1, 2, 3].map((i) => (
        <div key={i} style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '20px 24px', height: '100px', animation: 'pulse 1.5s ease-in-out infinite' }} />
      ))}
      <style>{`@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }`}</style>
    </div>
  );
}

function EmptyState({ message }: { message: string }) {
  return (
    <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '32px 24px', textAlign: 'center' }}>
      <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', color: '#8A8A8A' }}>{message}</p>
    </div>
  );
}

// ─── Section: Active Protocols ────────────────────────────────────────────────

function ActiveProtocols({ protocols, loading }: { protocols: Protocol[]; loading: boolean }) {
  return (
    <section>
      <div className="flex items-center justify-between mb-5">
        <div>
          <p className="section-label mb-1">Your Protocols</p>
          <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '26px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2 }}>
            Active Protocols
          </h2>
        </div>
        <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#8A8A8A' }}>
          {loading ? '…' : `${protocols.length} active`}
        </span>
      </div>

      {loading ? <LoadingSkeleton /> : protocols.length === 0 ? (
        <EmptyState message="No active protocols found." />
      ) : (
        <div style={{ display: 'grid', gap: '12px' }}>
          {protocols.map((p) => (
            <div
              key={p.id}
              style={{
                background: '#FFFFFF',
                border: '1px solid rgba(0,0,0,0.07)',
                borderRadius: '16px',
                padding: '20px 24px',
                display: 'grid',
                gridTemplateColumns: '1fr auto',
                gap: '12px',
                alignItems: 'start',
              }}
            >
              <div>
                <div className="flex items-center gap-2 mb-1 flex-wrap">
                  <CategoryBadge cat={p.category} />
                  <StatusPill status={p.status} />
                </div>
                <h3 style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '16px', fontWeight: 600, color: '#1A1A1A', marginTop: '6px', marginBottom: '4px' }}>
                  {p.name}
                </h3>
                <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '12px', color: '#5A8A5E', marginBottom: '10px' }}>
                  {p.dosage} · {p.frequency}
                </p>
                <div style={{ display: 'flex', gap: '20px', flexWrap: 'wrap' }}>
                  <div>
                    <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#8A8A8A', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '2px' }}>Started</p>
                    <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#4A4A4A', fontWeight: 500 }}>{p.startDate}</p>
                  </div>
                  <div>
                    <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#8A8A8A', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '2px' }}>Next Refill</p>
                    <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#4A4A4A', fontWeight: 500 }}>{p.nextRefill}</p>
                  </div>
                  <div>
                    <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#8A8A8A', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '2px' }}>Prescribing Physician</p>
                    <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#4A4A4A', fontWeight: 500 }}>{p.physician}</p>
                  </div>
                </div>
              </div>
              <button
                style={{
                  background: 'none',
                  border: '1px solid rgba(0,0,0,0.1)',
                  borderRadius: '8px',
                  padding: '7px 14px',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontSize: '12px',
                  fontWeight: 500,
                  color: '#4A4A4A',
                  cursor: 'pointer',
                  whiteSpace: 'nowrap',
                }}
                onClick={() => {
                  alert(`Protocol: ${p.name}\nDosage: ${p.dosage}\nFrequency: ${p.frequency}\nPhysician: ${p.physician}\nStarted: ${p.startDate}\nNext Refill: ${p.nextRefill}`);
                }}
                aria-label={`View details for ${p.name}`}
              >
                View Details
              </button>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

// ─── Section: Order History ───────────────────────────────────────────────────

function OrderHistory({ orders, loading }: { orders: Order[]; loading: boolean }) {
  const [expanded, setExpanded] = useState<string | null>(null);

  return (
    <section>
      <div className="flex items-center justify-between mb-5">
        <div>
          <p className="section-label mb-1">Billing</p>
          <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '26px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2 }}>
            Order History
          </h2>
        </div>
      </div>

      {loading ? <LoadingSkeleton /> : orders.length === 0 ? (
        <EmptyState message="No orders found." />
      ) : (
        <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', overflow: 'hidden' }}>
          {orders.map((order, i) => (
            <div key={order.id}>
              {i > 0 && <div style={{ height: '1px', background: 'rgba(0,0,0,0.06)' }} />}
              <button
                onClick={() => setExpanded(expanded === order.id ? null : order.id)}
                style={{
                  width: '100%',
                  background: 'none',
                  border: 'none',
                  cursor: 'pointer',
                  padding: '18px 24px',
                  display: 'grid',
                  gridTemplateColumns: '1fr auto auto auto',
                  gap: '16px',
                  alignItems: 'center',
                  textAlign: 'left',
                }}
              >
                <div>
                  <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '12px', color: '#C9A84C', fontWeight: 500, marginBottom: '3px' }}>{order.id}</p>
                  <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', color: '#1A1A1A', fontWeight: 500 }}>{order.date}</p>
                </div>
                <StatusPill status={order.status} />
                <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '15px', fontWeight: 600, color: '#1A1A1A' }}>${order.total}/mo</p>
                <svg
                  width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8A8A8A" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
                  style={{ transform: expanded === order.id ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease', flexShrink: 0 }}
                >
                  <path d="m6 9 6 6 6-6"/>
                </svg>
              </button>
              {expanded === order.id && (
                <div style={{ padding: '0 24px 18px', borderTop: '1px solid rgba(0,0,0,0.05)' }}>
                  <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#8A8A8A', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '8px', marginTop: '14px' }}>Items Included</p>
                  <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: '6px' }}>
                    {order.items?.map((item, idx) => (
                      <li key={idx} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span style={{ width: '5px', height: '5px', borderRadius: '50%', background: '#5A8A5E', flexShrink: 0 }} />
                        <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#4A4A4A' }}>{item}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

// ─── Section: Medication Shipment Status ─────────────────────────────────────

function ShipmentStatus({ shipments, loading }: { shipments: Shipment[]; loading: boolean }) {
  const steps = ['Label Created', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered'];
  const progressMap: Record<Shipment['status'], number> = {
    label_created: 0,
    in_transit: 2,
    out_for_delivery: 3,
    delivered: 4,
  };

  return (
    <section>
      <div className="flex items-center justify-between mb-5">
        <div>
          <p className="section-label mb-1">Logistics</p>
          <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '26px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2 }}>
            Medication Shipments
          </h2>
        </div>
      </div>

      {loading ? <LoadingSkeleton /> : shipments.length === 0 ? (
        <EmptyState message="No active shipments." />
      ) : (
        <div style={{ display: 'grid', gap: '12px' }}>
          {shipments.map((s) => {
            const activeStep = progressMap[s.status] ?? 0;
            return (
              <div
                key={s.id}
                style={{
                  background: '#FFFFFF',
                  border: '1px solid rgba(0,0,0,0.07)',
                  borderRadius: '16px',
                  padding: '22px 24px',
                }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px', flexWrap: 'wrap', gap: '8px' }}>
                  <div>
                    <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '15px', fontWeight: 600, color: '#1A1A1A', marginBottom: '3px' }}>{s.medication}</p>
                    <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '11px', color: '#8A8A8A' }}>{s.carrier} · {s.trackingNumber}</p>
                  </div>
                  <StatusPill status={s.status} />
                </div>

                {/* Progress track */}
                <div style={{ position: 'relative', marginBottom: '20px' }}>
                  <div style={{ position: 'absolute', top: '9px', left: '9px', right: '9px', height: '2px', background: 'rgba(0,0,0,0.08)', borderRadius: '2px' }} />
                  <div
                    style={{
                      position: 'absolute',
                      top: '9px',
                      left: '9px',
                      height: '2px',
                      background: '#5A8A5E',
                      borderRadius: '2px',
                      width: `calc(${(activeStep / (steps.length - 1)) * 100}% - 18px * ${activeStep / (steps.length - 1)})`,
                      transition: 'width 0.5s ease',
                    }}
                  />
                  <div style={{ display: 'flex', justifyContent: 'space-between', position: 'relative' }}>
                    {steps.map((step, idx) => (
                      <div key={step} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '6px' }}>
                        <div
                          style={{
                            width: '20px',
                            height: '20px',
                            borderRadius: '50%',
                            background: idx <= activeStep ? '#5A8A5E' : '#FFFFFF',
                            border: idx <= activeStep ? '2px solid #5A8A5E' : '2px solid rgba(0,0,0,0.12)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: 'all 0.3s ease',
                          }}
                        >
                          {idx < activeStep && (
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                              <path d="M20 6 9 17l-5-5"/>
                            </svg>
                          )}
                          {idx === activeStep && (
                            <div style={{ width: '8px', height: '8px', borderRadius: '50%', background: '#FFFFFF' }} />
                          )}
                        </div>
                        <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '9px', color: idx <= activeStep ? '#3A7A3E' : '#8A8A8A', fontWeight: idx === activeStep ? 600 : 400, textAlign: 'center', maxWidth: '52px', lineHeight: 1.3 }}>
                          {step}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '8px' }}>
                  <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#8A8A8A' }}>{s.lastUpdate}</p>
                  <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#4A4A4A', fontWeight: 500 }}>
                    Est. delivery: <span style={{ color: '#1A1A1A', fontWeight: 600 }}>{s.estimatedDelivery}</span>
                  </p>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </section>
  );
}

// ─── Section: Prescription Refill Requests ───────────────────────────────────

function RefillRequests({ refills, loading, onRequestAll }: { refills: RefillRequest[]; loading: boolean; onRequestAll: () => void }) {
  const [requesting, setRequesting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const handleRequest = async () => {
    setRequesting(true);
    await onRequestAll();
    setTimeout(() => {
      setRequesting(false);
      setSubmitted(true);
    }, 1000);
  };

  return (
    <section>
      <div className="flex items-center justify-between mb-5">
        <div>
          <p className="section-label mb-1">Prescriptions</p>
          <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '26px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2 }}>
            Refill Requests
          </h2>
        </div>
        {!submitted ? (
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
              fontSize: '13px',
              fontWeight: 500,
              padding: '9px 16px',
              borderRadius: '50px',
              border: 'none',
              cursor: requesting ? 'not-allowed' : 'pointer',
              transition: 'background 0.2s ease',
            }}
          >
            {requesting ? (
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
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M20 6 9 17l-5-5"/>
            </svg>
            Requests submitted
          </span>
        )}
      </div>

      {loading ? <LoadingSkeleton /> : refills.length === 0 ? (
        <EmptyState message="No refill requests found." />
      ) : (
        <div style={{ display: 'grid', gap: '10px' }}>
          {refills.map((r) => (
            <div
              key={r.id}
              style={{
                background: '#FFFFFF',
                border: '1px solid rgba(0,0,0,0.07)',
                borderRadius: '14px',
                padding: '18px 22px',
                display: 'grid',
                gridTemplateColumns: '1fr auto',
                gap: '12px',
                alignItems: 'center',
              }}
            >
              <div>
                <div className="flex items-center gap-2 mb-1">
                  <StatusPill status={r.status} />
                </div>
                <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', fontWeight: 600, color: '#1A1A1A', marginTop: '5px', marginBottom: '3px' }}>{r.medication}</p>
                <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#8A8A8A', marginBottom: '4px' }}>Requested {r.requestedDate}</p>
                {r.notes && (
                  <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#5A8A5E', fontStyle: 'italic' }}>{r.notes}</p>
                )}
              </div>
              {r.nextShipDate && (
                <div style={{ textAlign: 'right' }}>
                  <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '2px' }}>Ships</p>
                  <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', fontWeight: 600, color: '#1A1A1A' }}>{r.nextShipDate}</p>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      <style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>
    </section>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function PatientPortalPage() {
  const router = useRouter();
  const { user, loading: authLoading } = useAuth();
  const [activeTab, setActiveTab] = useState<'overview' | 'intake' | 'prescriptions' | 'labs' | 'refills' | 'settings'>('overview');

  const [protocols, setProtocols] = useState<Protocol[]>([]);
  const [orders, setOrders] = useState<Order[]>([]);
  const [shipments, setShipments] = useState<Shipment[]>([]);
  const [refills, setRefills] = useState<RefillRequest[]>([]);
  const [loadingProtocols, setLoadingProtocols] = useState(true);
  const [loadingOrders, setLoadingOrders] = useState(true);
  const [loadingShipments, setLoadingShipments] = useState(true);
  const [loadingRefills, setLoadingRefills] = useState(true);

  useEffect(() => {
    if (!authLoading && !user) {
      router.replace('/login');
      return;
    }
    // Only fetch data when user is authenticated
    if (!user) return;

    const supabase = createClient();

    async function fetchData() {
      const { data: { user } } = await supabase.auth.getUser();

      // Fetch protocols
      const { data: protocolData } = await supabase
        .from('patient_protocols')
        .select('*')
        .eq('status', 'active')
        .order('created_at', { ascending: false });

      if (protocolData) {
        const mappedProtocols = protocolData.map((p: any) => ({
          id: p.id,
          name: p.name,
          status: p.status,
          startDate: p.start_date,
          nextRefill: p.next_refill,
          dosage: p.dosage,
          frequency: p.frequency,
          physician: p.physician,
          category: p.category,
        }));
        setProtocols(mappedProtocols);

        // Fire physician-approved webhook for each newly active protocol
        if (user) {
          for (const p of protocolData) {
            if (p.status === 'active') {
              await triggerPhysicianApproved({
                userId: user.id,
                protocolId: p.id,
                protocolName: p.name,
                serviceCategory: p.category,
                physician: p.physician ?? undefined,
              });
            }
          }
        }
      }
      setLoadingProtocols(false);

      // Fetch orders
      const { data: orderData } = await supabase
        .from('patient_orders')
        .select('*')
        .order('created_at', { ascending: false });

      if (orderData) {
        setOrders(orderData.map((o: any) => ({
          id: o.id,
          date: o.order_date,
          items: o.items || [],
          total: Number(o.total),
          status: o.status,
        })));
      }
      setLoadingOrders(false);

      // Fetch shipments
      const { data: shipmentData } = await supabase
        .from('patient_shipments')
        .select('*')
        .order('created_at', { ascending: false });

      if (shipmentData) {
        const mappedShipments = shipmentData.map((s: any) => ({
          id: s.id,
          medication: s.medication,
          carrier: s.carrier,
          trackingNumber: s.tracking_number,
          status: s.status,
          estimatedDelivery: s.estimated_delivery,
          lastUpdate: s.last_update,
        }));
        setShipments(mappedShipments);

        // Fire order-shipped webhook for shipments in transit or out for delivery
        if (user) {
          for (const s of shipmentData) {
            if (s.status === 'in_transit' || s.status === 'out_for_delivery') {
              await triggerOrderShipped({
                userId: user.id,
                shipmentId: s.id,
                medication: s.medication,
                carrier: s.carrier,
                trackingNumber: s.tracking_number,
                estimatedDelivery: s.estimated_delivery,
                status: s.status,
              });
            }
          }
        }
      }
      setLoadingShipments(false);

      // Fetch refills
      const { data: refillData } = await supabase
        .from('patient_refills')
        .select('*')
        .order('created_at', { ascending: false });

      if (refillData) {
        setRefills(refillData.map((r: any) => ({
          id: r.id,
          medication: r.medication,
          requestedDate: r.requested_date,
          status: r.status,
          nextShipDate: r.next_ship_date ?? undefined,
          notes: r.notes ?? undefined,
        })));
      }
      setLoadingRefills(false);
    }

    fetchData();
  }, [user, authLoading]);

  const handleRequestAllRefills = async () => {
    const supabase = createClient();
    const { data: { user } } = await supabase.auth.getUser();
    if (!user) return;

    const pendingMeds = protocols.map((p) => p.name);
    if (pendingMeds.length === 0) return;

    const today = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const inserts = pendingMeds.map((med) => ({
      user_id: user.id,
      medication: med,
      requested_date: today,
      status: 'pending',
      notes: 'Submitted via patient portal',
    }));

    const { data: newRefills } = await supabase
      .from('patient_refills')
      .insert(inserts)
      .select();

    if (newRefills) {
      setRefills((prev) => [
        ...newRefills.map((r: any) => ({
          id: r.id,
          medication: r.medication,
          requestedDate: r.requested_date,
          status: r.status,
          nextShipDate: r.next_ship_date ?? undefined,
          notes: r.notes ?? undefined,
        })),
        ...prev,
      ]);
    }
  };

  const tabs = [
    { id: 'overview',       label: 'Overview' },
    { id: 'intake',         label: 'Intake Forms' },
    { id: 'prescriptions',  label: 'Active Prescriptions' },
    { id: 'labs',           label: 'Lab Results' },
    { id: 'refills',        label: 'Refill Management' },
    { id: 'settings',       label: 'Account Settings' },
  ] as const;

  const activeProtocolCount = protocols.length;
  const activeShipmentCount = shipments.filter((s) => s.status !== 'delivered').length;

  return (
    <div style={{ background: '#F8F7F5', minHeight: '100vh' }}>
      <Header />

      {/* Top spacer for fixed header */}
      <div style={{ height: '108px' }} />

      {/* Portal Header Banner */}
      <div style={{ background: '#0D0D0D', borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
        <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '28px 24px 0' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', flexWrap: 'wrap', gap: '16px', marginBottom: '24px' }}>
            <div>
              <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '11px', color: 'rgba(201,168,76,0.8)', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '6px' }}>
                🔒 Secure Patient Portal
              </p>
              <h1 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '32px', fontWeight: 600, color: '#FFFFFF', letterSpacing: '-0.02em', lineHeight: 1.2, margin: 0 }}>
                My Protocol Dashboard
              </h1>
              <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', color: 'rgba(255,255,255,0.5)', marginTop: '6px' }}>
                {loadingProtocols ? 'Loading…' : `${activeProtocolCount} active protocol${activeProtocolCount !== 1 ? 's' : ''}`}
                {!loadingProtocols && protocols[0]?.nextRefill ? ` · Next refill ${protocols[0].nextRefill}` : ''}
              </p>
            </div>
            <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
              <div style={{ background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '12px', padding: '12px 18px', textAlign: 'center' }}>
                <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '20px', fontWeight: 700, color: '#C9A84C', margin: 0 }}>
                  {loadingProtocols ? '…' : activeProtocolCount}
                </p>
                <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: 'rgba(255,255,255,0.4)', margin: 0, marginTop: '2px' }}>Active Protocols</p>
              </div>
              <div style={{ background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '12px', padding: '12px 18px', textAlign: 'center' }}>
                <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '20px', fontWeight: 700, color: '#5A8A5E', margin: 0 }}>
                  {loadingOrders ? '…' : orders.length}
                </p>
                <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: 'rgba(255,255,255,0.4)', margin: 0, marginTop: '2px' }}>Total Orders</p>
              </div>
              <div style={{ background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '12px', padding: '12px 18px', textAlign: 'center' }}>
                <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '20px', fontWeight: 700, color: '#FFFFFF', margin: 0 }}>
                  {loadingShipments ? '…' : activeShipmentCount}
                </p>
                <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: 'rgba(255,255,255,0.4)', margin: 0, marginTop: '2px' }}>Shipments Active</p>
              </div>
            </div>
          </div>

          {/* Tabs */}
          <div style={{ display: 'flex', gap: '0', overflowX: 'auto' }}>
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                style={{
                  background: 'none',
                  border: 'none',
                  cursor: 'pointer',
                  padding: '10px 18px',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontSize: '13px',
                  fontWeight: activeTab === tab.id ? 600 : 400,
                  color: activeTab === tab.id ? '#FFFFFF' : 'rgba(255,255,255,0.4)',
                  borderBottom: activeTab === tab.id ? '2px solid #C9A84C' : '2px solid transparent',
                  transition: 'all 0.2s ease',
                  whiteSpace: 'nowrap',
                }}
              >
                {tab.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Content */}
      <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '36px 24px 80px' }}>
        {activeTab === 'overview' && (
          <div style={{ display: 'grid', gap: '40px' }}>
            <ActiveProtocols protocols={protocols} loading={loadingProtocols} />
            <HRTRecommendationPanel
              protocols={protocols}
              orders={orders}
              refills={refills}
              loading={loadingProtocols}
            />
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(340px, 1fr))', gap: '40px' }}>
              {/* Physician Calls placeholder — no DB table yet */}
              <section>
                <div className="flex items-center justify-between mb-5">
                  <div>
                    <p className="section-label mb-1">Consultations</p>
                    <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '26px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2 }}>
                      Upcoming Physician Calls
                    </h2>
                  </div>
                </div>
                <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '32px 24px', textAlign: 'center' }}>
                  <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', color: '#8A8A8A' }}>No upcoming calls scheduled.</p>
                  <button className="btn-secondary" style={{ marginTop: '12px', height: '38px', fontSize: '13px', padding: '0 18px' }}>Schedule a Call</button>
                </div>
              </section>
              <ShipmentStatus shipments={shipments} loading={loadingShipments} />
            </div>
            <RefillRequests refills={refills} loading={loadingRefills} onRequestAll={handleRequestAllRefills} />
          </div>
        )}
        {activeTab === 'intake' && (
          <ClinicalIntakeForms />
        )}
        {activeTab === 'prescriptions' && (
          <ActivePrescriptions protocols={protocols} loading={loadingProtocols} />
        )}
        {activeTab === 'labs' && (
          <LabResultsHistory loading={loadingProtocols} />
        )}
        {activeTab === 'refills' && (
          <RefillManagement
            protocols={protocols}
            refills={refills}
            loading={loadingRefills || loadingProtocols}
            onRefillsUpdated={setRefills}
          />
        )}
        {activeTab === 'settings' && (
          <AccountSettings loading={loadingProtocols} />
        )}
      </div>

      <Footer />
    </div>
  );
}
