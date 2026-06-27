'use client';
import React, { useState, useEffect } from 'react';
import { createClient } from '@/lib/supabase/client';

interface LabResult {
  id: string;
  marker: string;
  value: number;
  unit: string;
  referenceMin: number;
  referenceMax: number;
  optimalMin?: number;
  optimalMax?: number;
  status: 'optimal' | 'normal' | 'low' | 'high' | 'critical';
  trend?: 'up' | 'down' | 'stable';
  previousValue?: number;
}

interface LabPanel {
  id: string;
  panelName: string;
  orderedDate: string;
  resultDate: string;
  orderedBy: string;
  status: 'resulted' | 'pending' | 'processing';
  results: LabResult[];
}

// ─── Demo data (shown when no DB data exists) ─────────────────────────────────

const DEMO_PANELS: LabPanel[] = [
  {
    id: 'lab-001',
    panelName: 'Comprehensive Hormone Panel',
    orderedDate: 'Mar 20, 2026',
    resultDate: 'Mar 24, 2026',
    orderedBy: 'Dr. Sarah Chen, MD',
    status: 'resulted',
    results: [
      { id: 'r1', marker: 'Total Testosterone', value: 680, unit: 'ng/dL', referenceMin: 300, referenceMax: 1000, optimalMin: 600, optimalMax: 900, status: 'optimal', trend: 'up', previousValue: 420 },
      { id: 'r2', marker: 'Free Testosterone', value: 18.4, unit: 'pg/mL', referenceMin: 9, referenceMax: 30, optimalMin: 15, optimalMax: 25, status: 'optimal', trend: 'up', previousValue: 11.2 },
      { id: 'r3', marker: 'Estradiol (E2)', value: 28, unit: 'pg/mL', referenceMin: 10, referenceMax: 40, optimalMin: 20, optimalMax: 35, status: 'optimal', trend: 'stable', previousValue: 29 },
      { id: 'r4', marker: 'LH', value: 3.2, unit: 'mIU/mL', referenceMin: 1.7, referenceMax: 8.6, status: 'normal', trend: 'stable' },
      { id: 'r5', marker: 'FSH', value: 4.1, unit: 'mIU/mL', referenceMin: 1.5, referenceMax: 12.4, status: 'normal', trend: 'stable' },
      { id: 'r6', marker: 'SHBG', value: 32, unit: 'nmol/L', referenceMin: 10, referenceMax: 57, optimalMin: 20, optimalMax: 45, status: 'optimal', trend: 'down', previousValue: 48 },
      { id: 'r7', marker: 'PSA', value: 0.8, unit: 'ng/mL', referenceMin: 0, referenceMax: 4.0, status: 'normal', trend: 'stable' },
      { id: 'r8', marker: 'Hematocrit', value: 47.2, unit: '%', referenceMin: 38.3, referenceMax: 48.6, optimalMin: 40, optimalMax: 48, status: 'optimal', trend: 'up', previousValue: 44.1 },
    ],
  },
  {
    id: 'lab-002',
    panelName: 'Metabolic & Lipid Panel',
    orderedDate: 'Mar 20, 2026',
    resultDate: 'Mar 24, 2026',
    orderedBy: 'Dr. Sarah Chen, MD',
    status: 'resulted',
    results: [
      { id: 'r9', marker: 'Total Cholesterol', value: 188, unit: 'mg/dL', referenceMin: 0, referenceMax: 200, status: 'normal', trend: 'down', previousValue: 204 },
      { id: 'r10', marker: 'HDL Cholesterol', value: 52, unit: 'mg/dL', referenceMin: 40, referenceMax: 200, optimalMin: 50, optimalMax: 200, status: 'optimal', trend: 'up', previousValue: 46 },
      { id: 'r11', marker: 'LDL Cholesterol', value: 112, unit: 'mg/dL', referenceMin: 0, referenceMax: 130, status: 'normal', trend: 'down', previousValue: 128 },
      { id: 'r12', marker: 'Triglycerides', value: 98, unit: 'mg/dL', referenceMin: 0, referenceMax: 150, status: 'normal', trend: 'stable' },
      { id: 'r13', marker: 'Fasting Glucose', value: 92, unit: 'mg/dL', referenceMin: 70, referenceMax: 99, status: 'normal', trend: 'stable' },
      { id: 'r14', marker: 'HbA1c', value: 5.2, unit: '%', referenceMin: 0, referenceMax: 5.7, status: 'normal', trend: 'stable' },
    ],
  },
  {
    id: 'lab-003',
    panelName: 'Baseline Hormone Panel',
    orderedDate: 'Jan 15, 2026',
    resultDate: 'Jan 18, 2026',
    orderedBy: 'Dr. Sarah Chen, MD',
    status: 'resulted',
    results: [
      { id: 'r15', marker: 'Total Testosterone', value: 420, unit: 'ng/dL', referenceMin: 300, referenceMax: 1000, optimalMin: 600, optimalMax: 900, status: 'low', trend: 'stable' },
      { id: 'r16', marker: 'Free Testosterone', value: 11.2, unit: 'pg/mL', referenceMin: 9, referenceMax: 30, optimalMin: 15, optimalMax: 25, status: 'low', trend: 'stable' },
      { id: 'r17', marker: 'Estradiol (E2)', value: 29, unit: 'pg/mL', referenceMin: 10, referenceMax: 40, status: 'normal', trend: 'stable' },
      { id: 'r18', marker: 'SHBG', value: 48, unit: 'nmol/L', referenceMin: 10, referenceMax: 57, status: 'normal', trend: 'stable' },
    ],
  },
];

// ─── Sub-components ───────────────────────────────────────────────────────────

function StatusDot({ status }: { status: LabResult['status'] }) {
  const map: Record<string, string> = {
    optimal: '#3A7A3E',
    normal: '#5A8A5E',
    low: '#A07820',
    high: '#A07820',
    critical: '#B43C3C',
  };
  return <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: map[status] ?? '#8A8A8A', display: 'inline-block', flexShrink: 0 }} />;
}

function TrendArrow({ trend, previousValue, currentValue, unit }: { trend?: 'up' | 'down' | 'stable'; previousValue?: number; currentValue: number; unit: string }) {
  if (!trend || trend === 'stable') {
    return <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: '#8A8A8A' }}>—</span>;
  }
  const diff = previousValue !== undefined ? Math.abs(currentValue - previousValue) : null;
  const color = trend === 'up' ? '#3A7A3E' : '#A07820';
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: '3px', fontFamily: 'JetBrains Mono, monospace', fontSize: '11px', color }}>
      {trend === 'up' ? '↑' : '↓'}
      {diff !== null && <span>{diff.toFixed(diff < 1 ? 1 : 0)} {unit}</span>}
    </span>
  );
}

function ResultBar({ result }: { result: LabResult }) {
  const range = result.referenceMax - result.referenceMin;
  const clampedValue = Math.max(result.referenceMin, Math.min(result.referenceMax, result.value));
  const position = ((clampedValue - result.referenceMin) / range) * 100;

  const optimalStart = result.optimalMin !== undefined ? ((result.optimalMin - result.referenceMin) / range) * 100 : null;
  const optimalWidth = result.optimalMin !== undefined && result.optimalMax !== undefined
    ? ((result.optimalMax - result.optimalMin) / range) * 100
    : null;

  const statusColor: Record<string, string> = {
    optimal: '#3A7A3E',
    normal: '#5A8A5E',
    low: '#A07820',
    high: '#A07820',
    critical: '#B43C3C',
  };
  const dotColor = statusColor[result.status] ?? '#8A8A8A';

  return (
    <div style={{ position: 'relative', height: '6px', background: 'rgba(0,0,0,0.06)', borderRadius: '3px', margin: '8px 0 4px' }}>
      {optimalStart !== null && optimalWidth !== null && (
        <div style={{ position: 'absolute', left: `${optimalStart}%`, width: `${optimalWidth}%`, height: '100%', background: 'rgba(90,138,94,0.2)', borderRadius: '3px' }} />
      )}
      <div style={{ position: 'absolute', left: `${Math.min(96, Math.max(2, position))}%`, top: '50%', transform: 'translate(-50%, -50%)', width: '12px', height: '12px', borderRadius: '50%', background: dotColor, border: '2px solid #FFFFFF', boxShadow: '0 1px 3px rgba(0,0,0,0.2)', transition: 'left 0.3s ease' }} />
    </div>
  );
}

function LabResultRow({ result }: { result: LabResult }) {
  const statusLabel: Record<string, string> = {
    optimal: 'Optimal',
    normal: 'Normal',
    low: 'Below Optimal',
    high: 'Above Optimal',
    critical: 'Critical',
  };
  const statusColor: Record<string, string> = {
    optimal: '#3A7A3E',
    normal: '#5A8A5E',
    low: '#A07820',
    high: '#A07820',
    critical: '#B43C3C',
  };

  return (
    <div style={{ padding: '14px 0', borderBottom: '1px solid rgba(0,0,0,0.04)' }}>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr auto auto auto', gap: '12px', alignItems: 'center' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <StatusDot status={result.status} />
          <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#2A2A2A', fontWeight: 500 }}>{result.marker}</span>
        </div>
        <span style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '13px', color: '#1A1A1A', fontWeight: 600 }}>
          {result.value} <span style={{ fontSize: '11px', color: '#8A8A8A', fontWeight: 400 }}>{result.unit}</span>
        </span>
        <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '11px', color: statusColor[result.status] ?? '#8A8A8A', fontWeight: 600 }}>
          {statusLabel[result.status] ?? result.status}
        </span>
        <TrendArrow trend={result.trend} previousValue={result.previousValue} currentValue={result.value} unit={result.unit} />
      </div>
      <ResultBar result={result} />
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#AAAAAA' }}>
          Ref: {result.referenceMin}–{result.referenceMax} {result.unit}
        </span>
        {result.optimalMin !== undefined && (
          <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#5A8A5E' }}>
            Optimal: {result.optimalMin}–{result.optimalMax} {result.unit}
          </span>
        )}
      </div>
    </div>
  );
}

function PanelCard({ panel }: { panel: LabPanel }) {
  const [expanded, setExpanded] = useState(false);
  const optimalCount = panel.results.filter((r) => r.status === 'optimal').length;
  const flaggedCount = panel.results.filter((r) => r.status === 'low' || r.status === 'high' || r.status === 'critical').length;

  return (
    <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', overflow: 'hidden' }}>
      <button
        onClick={() => setExpanded(!expanded)}
        style={{ width: '100%', background: 'none', border: 'none', cursor: 'pointer', padding: '20px 24px', textAlign: 'left' as const }}
      >
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '12px', flexWrap: 'wrap' as const }}>
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '6px' }}>
              <span style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', color: '#C9A84C', letterSpacing: '0.08em', textTransform: 'uppercase' as const }}>
                {panel.resultDate}
              </span>
              {panel.status === 'resulted' ? (
                <span style={{ background: 'rgba(90,138,94,0.1)', color: '#3A7A3E', fontSize: '10px', fontWeight: 600, padding: '2px 8px', borderRadius: '20px', fontFamily: 'DM Sans, system-ui, sans-serif', letterSpacing: '0.04em', textTransform: 'uppercase' as const }}>Resulted</span>
              ) : (
                <span style={{ background: 'rgba(201,168,76,0.1)', color: '#A07820', fontSize: '10px', fontWeight: 600, padding: '2px 8px', borderRadius: '20px', fontFamily: 'DM Sans, system-ui, sans-serif', letterSpacing: '0.04em', textTransform: 'uppercase' as const }}>Pending</span>
              )}
            </div>
            <h3 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '20px', fontWeight: 600, color: '#1A1A1A', margin: '0 0 4px', letterSpacing: '-0.01em' }}>
              {panel.panelName}
            </h3>
            <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#8A8A8A', margin: 0 }}>
              Ordered by {panel.orderedBy} · {panel.results.length} markers
            </p>
          </div>
          <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
            <div style={{ textAlign: 'center' as const }}>
              <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '16px', fontWeight: 700, color: '#3A7A3E', margin: 0 }}>{optimalCount}</p>
              <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', margin: 0 }}>Optimal</p>
            </div>
            {flaggedCount > 0 && (
              <div style={{ textAlign: 'center' as const }}>
                <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '16px', fontWeight: 700, color: '#A07820', margin: 0 }}>{flaggedCount}</p>
                <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', margin: 0 }}>Flagged</p>
              </div>
            )}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8A8A8A" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ transform: expanded ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s ease', marginLeft: '4px' }}>
              <path d="m6 9 6 6 6-6"/>
            </svg>
          </div>
        </div>
      </button>

      {expanded && (
        <div style={{ borderTop: '1px solid rgba(0,0,0,0.05)', padding: '0 24px 16px' }}>
          {panel.results.map((result) => (
            <LabResultRow key={result.id} result={result} />
          ))}
          <div style={{ marginTop: '16px', paddingTop: '16px', borderTop: '1px solid rgba(0,0,0,0.05)', display: 'flex', gap: '10px' }}>
            <button style={{ background: 'none', border: '1px solid rgba(0,0,0,0.1)', borderRadius: '8px', padding: '8px 16px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 500, color: '#4A4A4A', cursor: 'pointer' }}>
              Download PDF Report
            </button>
            <button style={{ background: 'none', border: '1px solid rgba(0,0,0,0.1)', borderRadius: '8px', padding: '8px 16px', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', fontWeight: 500, color: '#4A4A4A', cursor: 'pointer' }}>
              Discuss with Physician
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
      {[1, 2].map((i) => (
        <div key={i} style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '20px 24px', height: '110px', animation: 'pulse 1.5s ease-in-out infinite' }} />
      ))}
      <style>{`@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }`}</style>
    </div>
  );
}

// ─── Main Component ───────────────────────────────────────────────────────────

export default function LabResultsHistory({ loading: parentLoading }: { loading: boolean }) {
  const [panels, setPanels] = useState<LabPanel[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchLabs() {
      try {
        const supabase = createClient();
        const { data: { user } } = await supabase.auth.getUser();
        if (!user) { setPanels(DEMO_PANELS); setLoading(false); return; }

        const { data: labPanels } = await supabase
          .from('patient_lab_panels')
          .select('*, patient_lab_results(*)')
          .eq('user_id', user.id)
          .order('result_date', { ascending: false });

        if (labPanels && labPanels.length > 0) {
          setPanels(labPanels.map((p: any) => ({
            id: p.id,
            panelName: p.panel_name,
            orderedDate: p.ordered_date,
            resultDate: p.result_date,
            orderedBy: p.ordered_by,
            status: p.status,
            results: (p.patient_lab_results || []).map((r: any) => ({
              id: r.id,
              marker: r.marker,
              value: Number(r.value),
              unit: r.unit,
              referenceMin: Number(r.reference_min),
              referenceMax: Number(r.reference_max),
              optimalMin: r.optimal_min ? Number(r.optimal_min) : undefined,
              optimalMax: r.optimal_max ? Number(r.optimal_max) : undefined,
              status: r.status,
              trend: r.trend ?? undefined,
              previousValue: r.previous_value ? Number(r.previous_value) : undefined,
            })),
          })));
        } else {
          setPanels(DEMO_PANELS);
        }
      } catch {
        setPanels(DEMO_PANELS);
      }
      setLoading(false);
    }
    fetchLabs();
  }, []);

  const latestPanel = panels[0];
  const latestOptimal = latestPanel?.results.filter((r) => r.status === 'optimal').length ?? 0;
  const latestTotal = latestPanel?.results.length ?? 0;

  return (
    <section>
      <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: '24px', flexWrap: 'wrap', gap: '12px' }}>
        <div>
          <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', color: '#C9A84C', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '4px' }}>Diagnostics</p>
          <h2 style={{ fontFamily: 'Cormorant Garamond, Georgia, serif', fontSize: '30px', fontWeight: 600, color: '#1A1A1A', letterSpacing: '-0.02em', lineHeight: 1.2, margin: 0 }}>
            Lab Results & History
          </h2>
        </div>
        {!loading && latestPanel && (
          <div style={{ display: 'flex', gap: '10px' }}>
            <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '10px', padding: '8px 16px', textAlign: 'center' }}>
              <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '18px', fontWeight: 700, color: '#3A7A3E', margin: 0 }}>{latestOptimal}/{latestTotal}</p>
              <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', margin: 0, marginTop: '1px' }}>Latest Optimal</p>
            </div>
            <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '10px', padding: '8px 16px', textAlign: 'center' }}>
              <p style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: '18px', fontWeight: 700, color: '#C9A84C', margin: 0 }}>{panels.length}</p>
              <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '10px', color: '#8A8A8A', margin: 0, marginTop: '1px' }}>Panels on File</p>
            </div>
          </div>
        )}
      </div>

      {/* Legend */}
      <div style={{ display: 'flex', gap: '16px', marginBottom: '20px', flexWrap: 'wrap' }}>
        {[
          { color: '#3A7A3E', label: 'Optimal' },
          { color: '#5A8A5E', label: 'Normal' },
          { color: '#A07820', label: 'Below/Above Optimal' },
          { color: '#B43C3C', label: 'Critical' },
        ].map(({ color, label }) => (
          <div key={label} style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: color, display: 'inline-block' }} />
            <span style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px', color: '#6A6A6A' }}>{label}</span>
          </div>
        ))}
      </div>

      {loading || parentLoading ? (
        <LoadingSkeleton />
      ) : panels.length === 0 ? (
        <div style={{ background: '#FFFFFF', border: '1px solid rgba(0,0,0,0.07)', borderRadius: '16px', padding: '48px 24px', textAlign: 'center' }}>
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#CCCCCC" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ margin: '0 auto 12px' }}>
            <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v11m0 0H5a2 2 0 0 1-2-2V9m6 5h10a2 2 0 0 0 2-2V9m0 0H3"/>
          </svg>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '15px', color: '#4A4A4A', fontWeight: 500, marginBottom: '6px' }}>No lab results on file</p>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#8A8A8A' }}>Your lab results will appear here once your physician orders and receives your panel.</p>
        </div>
      ) : (
        <div style={{ display: 'grid', gap: '14px' }}>
          {panels.map((panel) => (
            <PanelCard key={panel.id} panel={panel} />
          ))}
        </div>
      )}
    </section>
  );
}
