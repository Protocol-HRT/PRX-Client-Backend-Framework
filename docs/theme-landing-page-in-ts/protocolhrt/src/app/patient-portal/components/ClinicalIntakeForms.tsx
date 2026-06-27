'use client';
import React, { useState, useEffect, useCallback } from 'react';
import { createClient } from '@/lib/supabase/client';
import { triggerIntakeFormSubmitted } from '@/lib/n8n/webhooks';
import type { ServiceType, FormType } from '@/lib/intakeForms/formDefinitions';
import {
  FORM_REGISTRY,
  REASSESSMENT_TIMING_DAYS,
  getAlreadyAnsweredKeys,
  filterDuplicateQuestions,
} from '@/lib/intakeForms/formDefinitions';
import IntakeFormRenderer from './IntakeFormRenderer';

// ─── EMR API Placeholder ──────────────────────────────────────────────────────
// Replace this function body with your actual EMR API integration when ready.

async function syncToEMR(payload: {
  userId: string;
  serviceType: ServiceType;
  formType: FormType;
  answers: Record<string, unknown>;
  flaggedQuestions: string[];
  submissionId: string;
}): Promise<{ success: boolean; emrRecordId?: string }> {
  // TODO: Replace with actual EMR API call
  // Example: POST https://your-emr-api.com/api/v1/intake-submissions
  console.log('[EMR API Hook] Submission ready for sync:', {
    submissionId: payload.submissionId,
    serviceType: payload.serviceType,
    formType: payload.formType,
    flaggedCount: payload.flaggedQuestions.length,
  });
  // Simulate async EMR call
  await new Promise((resolve) => setTimeout(resolve, 100));
  return { success: true, emrRecordId: `EMR-PENDING-${payload.submissionId}` };
}

// ─── Types ────────────────────────────────────────────────────────────────────

interface PendingForm {
  serviceType: ServiceType;
  formType: FormType;
  label: string;
  badge: string;
  badgeColor: string;
  description: string;
  dueDate?: string;
  isOverdue?: boolean;
  hasDraft?: boolean;
}

interface SubmittedForm {
  id: string;
  serviceType: ServiceType;
  formType: FormType;
  submittedAt: string;
  status: string;
  flaggedCount: number;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const SERVICE_LABELS: Record<ServiceType, string> = {
  TRT: 'Testosterone Replacement Therapy',
  GLP1: 'GLP-1 Receptor Agonist',
  FEMALE_HRT: 'Female Hormone Replacement Therapy',
};

const SERVICE_BADGE_COLORS: Record<ServiceType, { bg: string; color: string }> = {
  TRT: { bg: 'rgba(201,168,76,0.12)', color: '#A07820' },
  GLP1: { bg: 'rgba(90,138,94,0.12)', color: '#3A7A3E' },
  FEMALE_HRT: { bg: 'rgba(90,90,138,0.1)', color: '#5A5A8A' },
};

function formatDate(iso: string) {
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function ClinicalIntakeForms() {
  const [userId, setUserId] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [pendingForms, setPendingForms] = useState<PendingForm[]>([]);
  const [submittedForms, setSubmittedForms] = useState<SubmittedForm[]>([]);
  const [activeForm, setActiveForm] = useState<{
    serviceType: ServiceType;
    formType: FormType;
  } | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [draftData, setDraftData] = useState<{
    answers: Record<string, unknown>;
    sectionIdx: number;
    draftId?: string;
  } | null>(null);

  // ─── Load Data ──────────────────────────────────────────────────────────────

  const loadForms = useCallback(async () => {
    const supabase = createClient();
    const {
      data: { user },
    } = await supabase.auth.getUser();
    if (!user) return;

    setUserId(user.id);

    // Fetch purchased services
    const { data: purchases } = await supabase
      .from('patient_service_purchases')
      .select('*')
      .eq('user_id', user.id);

    // Fetch existing submissions (including drafts)
    const { data: submissions } = await supabase
      .from('patient_intake_submissions')
      .select('*')
      .eq('user_id', user.id)
      .order('submitted_at', { ascending: false });

    // Fetch re-assessment schedule
    const { data: schedules } = await supabase
      .from('patient_reassessment_schedule')
      .select('*')
      .eq('user_id', user.id);

    const now = new Date();
    const pending: PendingForm[] = [];
    const submitted: SubmittedForm[] = [];

    // Map submitted forms (exclude drafts from history)
    if (submissions) {
      for (const sub of submissions) {
        if (sub.submitted_at && sub.status !== 'draft') {
          submitted.push({
            id: sub.id,
            serviceType: sub.service_type as ServiceType,
            formType: sub.form_type as FormType,
            submittedAt: sub.submitted_at,
            status: sub.status,
            flaggedCount: Array.isArray(sub.flagged_questions)
              ? sub.flagged_questions.length
              : 0,
          });
        }
      }
    }

    setSubmittedForms(submitted);

    // Determine pending forms based on purchases
    if (purchases) {
      for (const purchase of purchases) {
        const svc = purchase.service_type as ServiceType;
        const badgeColors = SERVICE_BADGE_COLORS[svc];

        // Check if screening already submitted (not draft)
        const screeningSubmitted = submissions?.some(
          (s) =>
            s.service_type === svc &&
            s.form_type === 'SCREENING' &&
            s.submitted_at &&
            s.status !== 'draft'
        );

        // Check for existing draft
        const screeningDraft = submissions?.find(
          (s) =>
            s.service_type === svc &&
            s.form_type === 'SCREENING' &&
            s.status === 'draft'
        );

        if (!screeningSubmitted) {
          pending.push({
            serviceType: svc,
            formType: 'SCREENING',
            label: `${svc === 'GLP1' ? 'GLP-1' : svc === 'FEMALE_HRT' ? 'Female HRT' : svc} Screening`,
            badge: svc === 'GLP1' ? 'GLP-1' : svc === 'FEMALE_HRT' ? 'Female HRT' : svc,
            badgeColor: badgeColors.color,
            description: `Initial eligibility screening for ${SERVICE_LABELS[svc]}. Required before your physician consultation.`,
            hasDraft: !!screeningDraft,
          });
        } else {
          // Screening done — check re-assessment timing
          const screeningSub = submissions?.find(
            (s) =>
              s.service_type === svc &&
              s.form_type === 'SCREENING' &&
              s.submitted_at &&
              s.status !== 'draft'
          );

          const reassessmentSubmitted = submissions?.some(
            (s) =>
              s.service_type === svc &&
              s.form_type === 'REASSESSMENT' &&
              s.submitted_at &&
              s.status !== 'draft'
          );

          const reassessmentDraft = submissions?.find(
            (s) =>
              s.service_type === svc &&
              s.form_type === 'REASSESSMENT' &&
              s.status === 'draft'
          );

          if (!reassessmentSubmitted && screeningSub?.submitted_at) {
            const screeningDate = new Date(screeningSub.submitted_at);
            const daysRequired = REASSESSMENT_TIMING_DAYS[svc];
            const dueDate = new Date(screeningDate);
            dueDate.setDate(dueDate.getDate() + daysRequired);

            const schedule = schedules?.find((s) => s.service_type === svc);
            const effectiveDueDate = schedule
              ? new Date(schedule.reassessment_due_at)
              : dueDate;

            if (now >= effectiveDueDate) {
              const isOverdue =
                now.getTime() - effectiveDueDate.getTime() >
                7 * 24 * 60 * 60 * 1000;

              const timingLabel =
                svc === 'TRT' ? '10-Week'
                  : svc === 'GLP1' ? '1–3 Month' : '3–6 Month';

              pending.push({
                serviceType: svc,
                formType: 'REASSESSMENT',
                label: `${svc === 'GLP1' ? 'GLP-1' : svc === 'FEMALE_HRT' ? 'Female HRT' : svc} Re-Assessment`,
                badge: `${timingLabel} Follow-Up`,
                badgeColor: badgeColors.color,
                description: `Your ${timingLabel.toLowerCase()} re-assessment for ${SERVICE_LABELS[svc]} is now available.`,
                dueDate: effectiveDueDate.toISOString(),
                isOverdue,
                hasDraft: !!reassessmentDraft,
              });
            }
          }
        }
      }
    }

    setPendingForms(pending);
    setLoading(false);
  }, []);

  useEffect(() => {
    loadForms();
  }, [loadForms]);

  // ─── Load Draft ─────────────────────────────────────────────────────────────

  const loadDraft = useCallback(
    async (serviceType: ServiceType, formType: FormType) => {
      if (!userId) return null;
      const supabase = createClient();
      const { data } = await supabase
        .from('patient_intake_submissions')
        .select('id, answers, draft_section_idx')
        .eq('user_id', userId)
        .eq('service_type', serviceType)
        .eq('form_type', formType)
        .eq('status', 'draft')
        .order('updated_at', { ascending: false })
        .limit(1)
        .maybeSingle();

      if (data) {
        return {
          answers: (data.answers as Record<string, unknown>) ?? {},
          sectionIdx: (data.draft_section_idx as number) ?? 0,
          draftId: data.id as string,
        };
      }
      return null;
    },
    [userId]
  );

  // ─── Open Form (with draft restore) ────────────────────────────────────────

  const handleOpenForm = useCallback(
    async (serviceType: ServiceType, formType: FormType) => {
      const draft = await loadDraft(serviceType, formType);
      setDraftData(draft);
      setActiveForm({ serviceType, formType });
    },
    [loadDraft]
  );

  // ─── Save Draft Handler ─────────────────────────────────────────────────────

  const handleSaveDraft = async (
    answers: Record<string, unknown>,
    sectionIdx: number
  ) => {
    if (!userId || !activeForm) return;
    const supabase = createClient();

    if (draftData?.draftId) {
      // Update existing draft
      await supabase
        .from('patient_intake_submissions')
        .update({
          answers,
          draft_section_idx: sectionIdx,
          updated_at: new Date().toISOString(),
        })
        .eq('id', draftData.draftId);
    } else {
      // Create new draft
      const { data: newDraft } = await supabase
        .from('patient_intake_submissions')
        .insert({
          user_id: userId,
          service_type: activeForm.serviceType,
          form_type: activeForm.formType,
          status: 'draft',
          answers,
          draft_section_idx: sectionIdx,
          flagged_questions: [],
        })
        .select('id')
        .single();

      if (newDraft) {
        setDraftData((prev) => ({
          answers,
          sectionIdx,
          draftId: newDraft.id as string,
        }));
      }
    }
  };

  // ─── Submit Handler ─────────────────────────────────────────────────────────

  const handleSubmit = async (answers: Record<string, unknown>) => {
    if (!userId || !activeForm) return;
    setSubmitting(true);

    const supabase = createClient();

    // Collect flagged questions
    const formKey = `${activeForm.serviceType}_${activeForm.formType}`;
    const formDef = FORM_REGISTRY[formKey];
    const flaggedQuestions = formDef
      ? formDef.sections
          .flatMap((s) => s.questions)
          .filter(
            (q) =>
              (q.stopIfYes || q.consultIfYes) && answers[q.key] === 'Yes'
          )
          .map((q) => q.key)
      : [];

    try {
      let submission;

      if (draftData?.draftId) {
        // Promote draft to submitted
        const { data, error } = await supabase
          .from('patient_intake_submissions')
          .update({
            status: flaggedQuestions.length > 0 ? 'flagged' : 'submitted',
            answers,
            flagged_questions: flaggedQuestions,
            submitted_at: new Date().toISOString(),
            draft_section_idx: null,
          })
          .eq('id', draftData.draftId)
          .select()
          .single();
        if (error) throw error;
        submission = data;
      } else {
        // Fresh submission
        const { data, error } = await supabase
          .from('patient_intake_submissions')
          .insert({
            user_id: userId,
            service_type: activeForm.serviceType,
            form_type: activeForm.formType,
            status: flaggedQuestions.length > 0 ? 'flagged' : 'submitted',
            answers,
            flagged_questions: flaggedQuestions,
            submitted_at: new Date().toISOString(),
          })
          .select()
          .single();
        if (error) throw error;
        submission = data;
      }

      // If this is a screening, create re-assessment schedule
      if (activeForm.formType === 'SCREENING' && submission) {
        const daysRequired = REASSESSMENT_TIMING_DAYS[activeForm.serviceType];
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + daysRequired);

        await supabase
          .from('patient_reassessment_schedule')
          .upsert(
            {
              user_id: userId,
              service_type: activeForm.serviceType,
              screening_submitted_at: new Date().toISOString(),
              reassessment_due_at: dueDate.toISOString(),
            },
            { onConflict: 'user_id,service_type' }
          );
      }

      // EMR API placeholder sync
      if (submission) {
        const emrResult = await syncToEMR({
          userId,
          serviceType: activeForm.serviceType,
          formType: activeForm.formType,
          answers,
          flaggedQuestions,
          submissionId: submission.id,
        });

        await supabase
          .from('patient_intake_submissions')
          .update({
            emr_sync_status: emrResult.success ? 'synced' : 'failed',
            emr_synced_at: emrResult.success ? new Date().toISOString() : null,
          })
          .eq('id', submission.id);

        // Fire n8n webhook — intake form submitted
        await triggerIntakeFormSubmitted({
          userId,
          serviceType: activeForm.serviceType,
          formType: activeForm.formType,
          submissionId: submission.id,
          flaggedQuestions,
          isFlagged: flaggedQuestions.length > 0,
        });
      }

      setSuccessMessage(
        flaggedQuestions.length > 0
          ? 'Form submitted. Your responses have been flagged for physician review — a provider will contact you shortly.'
          : 'Form submitted successfully. Your physician will review your responses before your consultation.'
      );
      setActiveForm(null);
      setDraftData(null);
      await loadForms();
    } catch (err) {
      console.error('Intake form submission error:', err);
    } finally {
      setSubmitting(false);
    }
  };

  // ─── Active Form View ───────────────────────────────────────────────────────

  if (activeForm) {
    const formKey = `${activeForm.serviceType}_${activeForm.formType}`;
    const formDef = FORM_REGISTRY[formKey];

    if (!formDef) {
      return (
        <div style={{ padding: '24px', textAlign: 'center' }}>
          <p style={{ fontFamily: 'DM Sans, sans-serif', color: '#8A8A8A' }}>
            Form not found.
          </p>
        </div>
      );
    }

    // Deduplication: get already-answered keys from other submitted forms
    const otherSubmissions: Record<string, Record<string, unknown>> = {};
    submittedForms
      .filter(
        (s) =>
          !(
            s.serviceType === activeForm.serviceType &&
            s.formType === activeForm.formType
          )
      )
      .forEach((s) => {
        otherSubmissions[`${s.serviceType}_${s.formType}`] = {};
      });

    const alreadyAnswered = getAlreadyAnsweredKeys(otherSubmissions);
    const dedupedForm = filterDuplicateQuestions(formDef, alreadyAnswered);

    return (
      <div>
        <IntakeFormRenderer
          form={dedupedForm}
          onSubmit={handleSubmit}
          onCancel={() => { setActiveForm(null); setDraftData(null); }}
          onSaveDraft={handleSaveDraft}
          initialAnswers={draftData?.answers}
          initialSectionIdx={draftData?.sectionIdx ?? 0}
          submitting={submitting}
        />
      </div>
    );
  }

  // ─── Dashboard View ─────────────────────────────────────────────────────────

  return (
    <section>
      <div style={{ marginBottom: '24px' }}>
        <p
          style={{
            fontFamily: 'DM Sans, system-ui, sans-serif',
            fontSize: '11px',
            color: '#8A8A8A',
            textTransform: 'uppercase',
            letterSpacing: '0.08em',
            marginBottom: '4px',
          }}
        >
          Clinical Intake
        </p>
        <h2
          style={{
            fontFamily: 'Cormorant Garamond, Georgia, serif',
            fontSize: '26px',
            fontWeight: 600,
            color: '#1A1A1A',
            letterSpacing: '-0.02em',
            lineHeight: 1.2,
          }}
        >
          Screening & Intake Forms
        </h2>
        <p
          style={{
            fontFamily: 'DM Sans, system-ui, sans-serif',
            fontSize: '13px',
            color: '#6A6A6A',
            marginTop: '4px',
          }}
        >
          Complete your clinical intake forms to begin your protocol. Re-assessment forms appear automatically at the appropriate follow-up interval.
        </p>
      </div>

      {/* Success Message */}
      {successMessage && (
        <div
          style={{
            background: 'rgba(90,138,94,0.08)',
            border: '1px solid rgba(90,138,94,0.2)',
            borderRadius: '12px',
            padding: '14px 18px',
            marginBottom: '20px',
            display: 'flex',
            alignItems: 'flex-start',
            gap: '10px',
          }}
        >
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="#5A8A5E"
            strokeWidth="2.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            style={{ flexShrink: 0, marginTop: '1px' }}
          >
            <path d="M20 6 9 17l-5-5" />
          </svg>
          <p
            style={{
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '13px',
              color: '#2A5A2E',
              lineHeight: 1.5,
            }}
          >
            {successMessage}
          </p>
        </div>
      )}

      {loading ? (
        <div style={{ display: 'grid', gap: '12px' }}>
          {[1, 2].map((i) => (
            <div
              key={i}
              style={{
                background: '#FFFFFF',
                border: '1px solid rgba(0,0,0,0.07)',
                borderRadius: '16px',
                height: '100px',
                animation: 'pulse 1.5s ease-in-out infinite',
              }}
            />
          ))}
          <style>{`@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }`}</style>
        </div>
      ) : (
        <>
          {/* Pending Forms */}
          {pendingForms.length > 0 && (
            <div style={{ marginBottom: '32px' }}>
              <p
                style={{
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: '#4A4A4A',
                  textTransform: 'uppercase',
                  letterSpacing: '0.06em',
                  marginBottom: '12px',
                }}
              >
                Action Required ({pendingForms.length})
              </p>
              <div style={{ display: 'grid', gap: '12px' }}>
                {pendingForms.map((form) => (
                  <div
                    key={`${form.serviceType}_${form.formType}`}
                    style={{
                      background: '#FFFFFF',
                      border: form.isOverdue
                        ? '1px solid rgba(180,60,60,0.25)'
                        : '1px solid rgba(201,168,76,0.25)',
                      borderRadius: '16px',
                      padding: '20px 24px',
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      gap: '16px',
                      flexWrap: 'wrap',
                    }}
                  >
                    <div style={{ flex: 1, minWidth: '200px' }}>
                      <div
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          gap: '8px',
                          marginBottom: '6px',
                          flexWrap: 'wrap',
                        }}
                      >
                        <span
                          style={{
                            padding: '2px 10px',
                            borderRadius: '20px',
                            background:
                              SERVICE_BADGE_COLORS[form.serviceType].bg,
                            color: form.badgeColor,
                            fontFamily: 'JetBrains Mono, monospace',
                            fontSize: '10px',
                            fontWeight: 700,
                            letterSpacing: '0.06em',
                            textTransform: 'uppercase',
                          }}
                        >
                          {form.badge}
                        </span>
                        {form.hasDraft && (
                          <span
                            style={{
                              padding: '2px 10px',
                              borderRadius: '20px',
                              background: 'rgba(90,90,138,0.1)',
                              color: '#5A5A8A',
                              fontFamily: 'DM Sans, sans-serif',
                              fontSize: '10px',
                              fontWeight: 600,
                              textTransform: 'uppercase',
                              letterSpacing: '0.04em',
                              display: 'flex',
                              alignItems: 'center',
                              gap: '4px',
                            }}
                          >
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            </svg>
                            Draft Saved
                          </span>
                        )}
                        {form.isOverdue && (
                          <span
                            style={{
                              padding: '2px 10px',
                              borderRadius: '20px',
                              background: 'rgba(180,60,60,0.1)',
                              color: '#B43C3C',
                              fontFamily: 'DM Sans, sans-serif',
                              fontSize: '10px',
                              fontWeight: 600,
                              textTransform: 'uppercase',
                              letterSpacing: '0.04em',
                            }}
                          >
                            Overdue
                          </span>
                        )}
                        {!form.isOverdue && form.dueDate && (
                          <span
                            style={{
                              padding: '2px 10px',
                              borderRadius: '20px',
                              background: 'rgba(201,168,76,0.1)',
                              color: '#A07820',
                              fontFamily: 'DM Sans, sans-serif',
                              fontSize: '10px',
                              fontWeight: 600,
                              textTransform: 'uppercase',
                              letterSpacing: '0.04em',
                            }}
                          >
                            Due {formatDate(form.dueDate)}
                          </span>
                        )}
                        {!form.dueDate && !form.hasDraft && (
                          <span
                            style={{
                              padding: '2px 10px',
                              borderRadius: '20px',
                              background: 'rgba(201,168,76,0.1)',
                              color: '#A07820',
                              fontFamily: 'DM Sans, sans-serif',
                              fontSize: '10px',
                              fontWeight: 600,
                              textTransform: 'uppercase',
                              letterSpacing: '0.04em',
                            }}
                          >
                            Required
                          </span>
                        )}
                      </div>
                      <h3
                        style={{
                          fontFamily: 'DM Sans, system-ui, sans-serif',
                          fontSize: '16px',
                          fontWeight: 600,
                          color: '#1A1A1A',
                          marginBottom: '4px',
                        }}
                      >
                        {form.label}
                      </h3>
                      <p
                        style={{
                          fontFamily: 'DM Sans, system-ui, sans-serif',
                          fontSize: '13px',
                          color: '#6A6A6A',
                          lineHeight: 1.4,
                        }}
                      >
                        {form.description}
                      </p>
                    </div>
                    <button
                      onClick={() =>
                        handleOpenForm(form.serviceType, form.formType)
                      }
                      style={{
                        padding: '10px 22px',
                        borderRadius: '10px',
                        border: form.hasDraft ? '1px solid rgba(90,90,138,0.3)' : 'none',
                        background: form.hasDraft ? 'rgba(90,90,138,0.08)' : '#1A1A1A',
                        fontFamily: 'DM Sans, system-ui, sans-serif',
                        fontSize: '13px',
                        fontWeight: 600,
                        color: form.hasDraft ? '#5A5A8A' : '#FFFFFF',
                        cursor: 'pointer',
                        whiteSpace: 'nowrap',
                        flexShrink: 0,
                        display: 'flex',
                        alignItems: 'center',
                        gap: '6px',
                      }}
                    >
                      {form.hasDraft ? (
                        <>
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                            <polygon points="5 3 19 12 5 21 5 3"/>
                          </svg>
                          Resume Draft
                        </>
                      ) : (
                        'Start Form →'
                      )}
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* No pending forms */}
          {pendingForms.length === 0 && submittedForms.length === 0 && (
            <div
              style={{
                background: '#FFFFFF',
                border: '1px solid rgba(0,0,0,0.07)',
                borderRadius: '16px',
                padding: '40px 32px',
                textAlign: 'center',
              }}
            >
              <div
                style={{
                  width: 56,
                  height: 56,
                  borderRadius: '50%',
                  background: 'rgba(201,168,76,0.08)',
                  border: '1px solid rgba(201,168,76,0.2)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  margin: '0 auto 16px',
                }}
              >
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M9 11l3 3L22 4"/>
                  <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
              </div>
              <h3
                style={{
                  fontFamily: 'Cormorant Garamond, Georgia, serif',
                  fontSize: '20px',
                  fontWeight: 600,
                  color: '#1A1A1A',
                  marginBottom: '8px',
                  letterSpacing: '-0.02em',
                }}
              >
                No intake forms yet
              </h3>
              <p
                style={{
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontSize: '14px',
                  color: '#6A6A6A',
                  lineHeight: 1.6,
                  maxWidth: '360px',
                  margin: '0 auto 20px',
                }}
              >
                Your clinical intake forms will appear here once your service purchase is confirmed. Complete checkout to unlock your personalized protocol forms.
              </p>
              <a
                href="/checkout?plan=blueprint"
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '6px',
                  background: '#1A1A1A',
                  color: '#FFFFFF',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontSize: '13px',
                  fontWeight: 600,
                  padding: '10px 22px',
                  borderRadius: '10px',
                  textDecoration: 'none',
                }}
              >
                Get Started — $49 Blueprint →
              </a>
            </div>
          )}

          {pendingForms.length === 0 && submittedForms.length > 0 && (
            <div
              style={{
                background: 'rgba(90,138,94,0.06)',
                border: '1px solid rgba(90,138,94,0.15)',
                borderRadius: '12px',
                padding: '14px 18px',
                marginBottom: '24px',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
              }}
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="#5A8A5E"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M20 6 9 17l-5-5" />
              </svg>
              <p
                style={{
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontSize: '13px',
                  color: '#2A5A2E',
                }}
              >
                All current forms completed. Re-assessment forms will appear automatically at your follow-up interval.
              </p>
            </div>
          )}

          {/* Submitted Forms History */}
          {submittedForms.length > 0 && (
            <div>
              <p
                style={{
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: '#4A4A4A',
                  textTransform: 'uppercase',
                  letterSpacing: '0.06em',
                  marginBottom: '12px',
                }}
              >
                Completed Forms ({submittedForms.length})
              </p>
              <div
                style={{
                  background: '#FFFFFF',
                  border: '1px solid rgba(0,0,0,0.07)',
                  borderRadius: '16px',
                  overflow: 'hidden',
                }}
              >
                {submittedForms.map((sub, i) => (
                  <div key={sub.id}>
                    {i > 0 && (
                      <div
                        style={{
                          height: '1px',
                          background: 'rgba(0,0,0,0.06)',
                        }}
                      />
                    )}
                    <div
                      style={{
                        padding: '16px 22px',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        flexWrap: 'wrap',
                        gap: '10px',
                      }}
                    >
                      <div>
                        <div
                          style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            marginBottom: '4px',
                          }}
                        >
                          <span
                            style={{
                              padding: '2px 8px',
                              borderRadius: '4px',
                              background:
                                SERVICE_BADGE_COLORS[sub.serviceType].bg,
                              color:
                                SERVICE_BADGE_COLORS[sub.serviceType].color,
                              fontFamily: 'JetBrains Mono, monospace',
                              fontSize: '9px',
                              fontWeight: 700,
                              letterSpacing: '0.06em',
                              textTransform: 'uppercase',
                            }}
                          >
                            {sub.serviceType === 'GLP1' ? 'GLP-1'
                              : sub.serviceType === 'FEMALE_HRT' ? 'Female HRT'
                              : sub.serviceType}
                          </span>
                          <span
                            style={{
                              fontFamily: 'DM Sans, sans-serif',
                              fontSize: '11px',
                              color: '#8A8A8A',
                            }}
                          >
                            {sub.formType === 'SCREENING' ? 'Screening' : 'Re-Assessment'}
                          </span>
                        </div>
                        <p
                          style={{
                            fontFamily: 'DM Sans, system-ui, sans-serif',
                            fontSize: '13px',
                            color: '#4A4A4A',
                          }}
                        >
                          Submitted {formatDate(sub.submittedAt)}
                        </p>
                      </div>
                      <div
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          gap: '10px',
                        }}
                      >
                        {sub.flaggedCount > 0 && (
                          <span
                            style={{
                              padding: '3px 10px',
                              borderRadius: '20px',
                              background: 'rgba(201,168,76,0.1)',
                              color: '#A07820',
                              fontFamily: 'DM Sans, sans-serif',
                              fontSize: '11px',
                              fontWeight: 600,
                            }}
                          >
                            {sub.flaggedCount} flagged
                          </span>
                        )}
                        <span
                          style={{
                            padding: '3px 10px',
                            borderRadius: '20px',
                            background:
                              sub.status === 'approved' ? 'rgba(90,138,94,0.12)'
                                : sub.status === 'flagged' ? 'rgba(201,168,76,0.12)' : 'rgba(0,0,0,0.06)',
                            color:
                              sub.status === 'approved' ? '#3A7A3E'
                                : sub.status === 'flagged' ? '#A07820' : '#6A6A6A',
                            fontFamily: 'DM Sans, sans-serif',
                            fontSize: '11px',
                            fontWeight: 600,
                            textTransform: 'capitalize',
                          }}
                        >
                          {sub.status}
                        </span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </>
      )}
    </section>
  );
}
