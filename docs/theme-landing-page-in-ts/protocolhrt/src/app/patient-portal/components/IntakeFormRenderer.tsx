'use client';
import React, { useState, useEffect } from 'react';
import type {
  IntakeFormDefinition,
  FormQuestion,
  FormSection,
} from '@/lib/intakeForms/formDefinitions';

// ─── Types ────────────────────────────────────────────────────────────────────

interface IntakeFormRendererProps {
  form: IntakeFormDefinition;
  onSubmit: (answers: Record<string, unknown>) => Promise<void>;
  onCancel?: () => void;
  onSaveDraft?: (answers: Record<string, unknown>, sectionIdx: number) => Promise<void>;
  initialAnswers?: Record<string, unknown>;
  initialSectionIdx?: number;
  submitting?: boolean;
}

// ─── Styles ───────────────────────────────────────────────────────────────────

const inputBase: React.CSSProperties = {
  width: '100%',
  padding: '10px 14px',
  borderRadius: '10px',
  border: '1px solid rgba(0,0,0,0.12)',
  background: '#FAFAF8',
  fontFamily: 'DM Sans, system-ui, sans-serif',
  fontSize: '14px',
  color: '#1A1A1A',
  outline: 'none',
  boxSizing: 'border-box',
};

const inputError: React.CSSProperties = {
  ...inputBase,
  border: '1.5px solid rgba(180,60,60,0.5)',
  background: 'rgba(180,60,60,0.03)',
};

const labelStyle: React.CSSProperties = {
  fontFamily: 'DM Sans, system-ui, sans-serif',
  fontSize: '13px',
  fontWeight: 600,
  color: '#2A2A2A',
  marginBottom: '6px',
  display: 'block',
  lineHeight: 1.4,
};

const descStyle: React.CSSProperties = {
  fontFamily: 'DM Sans, system-ui, sans-serif',
  fontSize: '12px',
  color: '#6A6A6A',
  marginBottom: '4px',
  lineHeight: 1.5,
};

const errorMsgStyle: React.CSSProperties = {
  fontFamily: 'DM Sans, system-ui, sans-serif',
  fontSize: '11px',
  color: '#B43C3C',
  marginTop: '5px',
  display: 'flex',
  alignItems: 'center',
  gap: '4px',
};

// ─── Question Renderers ───────────────────────────────────────────────────────

function YesNoField({
  question,
  value,
  onChange,
  hasError,
}: {
  question: FormQuestion;
  value: string | undefined;
  onChange: (v: string) => void;
  hasError?: boolean;
}) {
  return (
    <div>
      <div style={{ display: 'flex', gap: '10px' }}>
        {['Yes', 'No'].map((opt) => (
          <button
            key={opt}
            type="button"
            onClick={() => onChange(opt)}
            style={{
              flex: 1,
              padding: '9px 0',
              borderRadius: '10px',
              border: `1.5px solid ${
                value === opt
                  ? opt === 'Yes' ? '#C9A84C' : '#5A8A5E' : hasError && !value ?'rgba(180,60,60,0.4)' : 'rgba(0,0,0,0.12)'
              }`,
              background:
                value === opt
                  ? opt === 'Yes' ? 'rgba(201,168,76,0.08)' : 'rgba(90,138,94,0.08)'
                  : '#FAFAF8',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '14px',
              fontWeight: value === opt ? 600 : 400,
              color:
                value === opt
                  ? opt === 'Yes' ? '#A07820' : '#3A7A3E' :'#4A4A4A',
              cursor: 'pointer',
              transition: 'all 0.15s ease',
            }}
          >
            {opt}
          </button>
        ))}
      </div>
      {hasError && !value && (
        <p style={errorMsgStyle}>
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Please select Yes or No
        </p>
      )}
    </div>
  );
}

function YesNoExplainField({
  question,
  value,
  explainValue,
  onChange,
  onExplainChange,
  hasError,
}: {
  question: FormQuestion;
  value: string | undefined;
  explainValue: string | undefined;
  onChange: (v: string) => void;
  onExplainChange: (v: string) => void;
  hasError?: boolean;
}) {
  return (
    <div>
      <YesNoField question={question} value={value} onChange={onChange} hasError={hasError} />
      {value === 'Yes' && (
        <div style={{ marginTop: '8px' }}>
          <textarea
            placeholder="Please explain..."
            value={explainValue ?? ''}
            onChange={(e) => onExplainChange(e.target.value)}
            rows={2}
            style={{
              ...inputBase,
              resize: 'vertical',
              minHeight: '60px',
            }}
          />
        </div>
      )}
    </div>
  );
}

function ScaleField({
  value,
  onChange,
  hasError,
}: {
  value: number | undefined;
  onChange: (v: number) => void;
  hasError?: boolean;
}) {
  return (
    <div>
      <div style={{ display: 'flex', gap: '8px' }}>
        {[1, 2, 3, 4, 5].map((n) => (
          <button
            key={n}
            type="button"
            onClick={() => onChange(n)}
            style={{
              flex: 1,
              padding: '8px 0',
              borderRadius: '8px',
              border: `1.5px solid ${
                value === n ? '#5A8A5E' : hasError && !value ? 'rgba(180,60,60,0.4)' : 'rgba(0,0,0,0.1)'
              }`,
              background: value === n ? '#5A8A5E' : '#FAFAF8',
              fontFamily: 'JetBrains Mono, monospace',
              fontSize: '13px',
              fontWeight: 600,
              color: value === n ? '#FFFFFF' : '#6A6A6A',
              cursor: 'pointer',
              transition: 'all 0.15s ease',
            }}
          >
            {n}
          </button>
        ))}
      </div>
      {hasError && !value && (
        <p style={errorMsgStyle}>
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Please select a rating
        </p>
      )}
    </div>
  );
}

function SelectField({
  question,
  value,
  onChange,
  hasError,
}: {
  question: FormQuestion;
  value: string | undefined;
  onChange: (v: string) => void;
  hasError?: boolean;
}) {
  return (
    <div>
      <select
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        style={hasError && !value ? { ...inputError, cursor: 'pointer' } : { ...inputBase, cursor: 'pointer' }}
      >
        <option value="">Select an option...</option>
        {question.options?.map((opt) => (
          <option key={opt} value={opt}>
            {opt}
          </option>
        ))}
      </select>
      {hasError && !value && (
        <p style={errorMsgStyle}>
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Please select an option
        </p>
      )}
    </div>
  );
}

// ─── Single Question ──────────────────────────────────────────────────────────

function QuestionField({
  question,
  answers,
  onAnswer,
  validationErrors,
}: {
  question: FormQuestion;
  answers: Record<string, unknown>;
  onAnswer: (key: string, value: unknown) => void;
  validationErrors: Set<string>;
}) {
  const value = answers[question.key] as string | undefined;
  const explainValue = answers[`${question.key}_explain`] as string | undefined;
  const hasError = validationErrors.has(question.key);

  const flagColor =
    question.stopIfYes
      ? 'rgba(180,60,60,0.08)'
      : question.consultIfYes
      ? 'rgba(201,168,76,0.06)'
      : 'transparent';

  const flagBorder =
    question.stopIfYes
      ? '1px solid rgba(180,60,60,0.15)'
      : question.consultIfYes
      ? '1px solid rgba(201,168,76,0.15)'
      : hasError
      ? '1px solid rgba(180,60,60,0.2)'
      : '1px solid transparent';

  return (
    <div
      style={{
        padding: '14px 16px',
        borderRadius: '12px',
        background: flagColor,
        border: flagBorder,
        marginBottom: '10px',
      }}
    >
      <label style={labelStyle}>
        {question.label}
        {question.required && (
          <span style={{ color: '#C9A84C', marginLeft: '4px' }}>*</span>
        )}
      </label>
      {question.description && (
        <p style={descStyle}>{question.description}</p>
      )}
      {question.stopIfYes && (
        <p style={{ ...descStyle, color: '#B43C3C', fontWeight: 500, marginBottom: '8px' }}>
          ⚠️ A YES answer requires immediate physician consultation — do not proceed.
        </p>
      )}
      {question.consultIfYes && (
        <p style={{ ...descStyle, color: '#A07820', fontWeight: 500, marginBottom: '8px' }}>
          ℹ️ A YES answer will flag this for prescriber review.
        </p>
      )}

      {question.type === 'yes_no' && (
        <YesNoField
          question={question}
          value={value}
          onChange={(v) => onAnswer(question.key, v)}
          hasError={hasError}
        />
      )}
      {question.type === 'yes_no_explain' && (
        <YesNoExplainField
          question={question}
          value={value}
          explainValue={explainValue}
          onChange={(v) => onAnswer(question.key, v)}
          onExplainChange={(v) => onAnswer(`${question.key}_explain`, v)}
          hasError={hasError}
        />
      )}
      {question.type === 'scale_1_5' && (
        <div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '6px' }}>
            <span style={{ fontFamily: 'DM Sans, sans-serif', fontSize: '11px', color: '#8A8A8A' }}>None / No improvement</span>
            <span style={{ fontFamily: 'DM Sans, sans-serif', fontSize: '11px', color: '#8A8A8A' }}>Significant improvement</span>
          </div>
          <ScaleField
            value={value ? Number(value) : undefined}
            onChange={(v) => onAnswer(question.key, String(v))}
            hasError={hasError}
          />
        </div>
      )}
      {question.type === 'text' && (
        <div>
          <input
            type="text"
            value={value ?? ''}
            placeholder={question.placeholder}
            onChange={(e) => onAnswer(question.key, e.target.value)}
            style={hasError && !value ? inputError : inputBase}
          />
          {hasError && !value && (
            <p style={errorMsgStyle}>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              This field is required
            </p>
          )}
        </div>
      )}
      {question.type === 'number' && (
        <div>
          <input
            type="number"
            value={value ?? ''}
            placeholder={question.placeholder}
            onChange={(e) => onAnswer(question.key, e.target.value)}
            style={hasError && !value ? inputError : inputBase}
          />
          {hasError && !value && (
            <p style={errorMsgStyle}>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              This field is required
            </p>
          )}
        </div>
      )}
      {question.type === 'date' && (
        <div>
          <input
            type="date"
            value={value ?? ''}
            onChange={(e) => onAnswer(question.key, e.target.value)}
            style={hasError && !value ? inputError : inputBase}
          />
          {hasError && !value && (
            <p style={errorMsgStyle}>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              This field is required
            </p>
          )}
        </div>
      )}
      {question.type === 'select' && (
        <SelectField
          question={question}
          value={value}
          onChange={(v) => onAnswer(question.key, v)}
          hasError={hasError}
        />
      )}
    </div>
  );
}

// ─── Section ──────────────────────────────────────────────────────────────────

function FormSectionBlock({
  section,
  answers,
  onAnswer,
  validationErrors,
}: {
  section: FormSection;
  answers: Record<string, unknown>;
  onAnswer: (key: string, value: unknown) => void;
  validationErrors: Set<string>;
}) {
  if (section.questions.length === 0) return null;

  return (
    <div style={{ marginBottom: '28px' }}>
      <div style={{ marginBottom: '14px' }}>
        <h3
          style={{
            fontFamily: 'Cormorant Garamond, Georgia, serif',
            fontSize: '20px',
            fontWeight: 600,
            color: '#1A1A1A',
            letterSpacing: '-0.01em',
            marginBottom: section.description ? '4px' : '0',
          }}
        >
          {section.title}
        </h3>
        {section.description && (
          <p
            style={{
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '13px',
              color: '#6A6A6A',
              lineHeight: 1.5,
            }}
          >
            {section.description}
          </p>
        )}
      </div>
      {section.questions.map((q) => (
        <QuestionField
          key={q.key}
          question={q}
          answers={answers}
          onAnswer={onAnswer}
          validationErrors={validationErrors}
        />
      ))}
    </div>
  );
}

// ─── Progress Bar ─────────────────────────────────────────────────────────────

function ProgressBar({
  current,
  total,
}: {
  current: number;
  total: number;
}) {
  const pct = Math.round((current / total) * 100);
  return (
    <div style={{ marginBottom: '24px' }}>
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          marginBottom: '6px',
        }}
      >
        <span
          style={{
            fontFamily: 'DM Sans, system-ui, sans-serif',
            fontSize: '12px',
            color: '#6A6A6A',
          }}
        >
          Section {current} of {total}
        </span>
        <span
          style={{
            fontFamily: 'JetBrains Mono, monospace',
            fontSize: '12px',
            color: '#5A8A5E',
            fontWeight: 600,
          }}
        >
          {pct}%
        </span>
      </div>
      <div
        style={{
          height: '6px',
          background: 'rgba(0,0,0,0.07)',
          borderRadius: '6px',
          overflow: 'hidden',
        }}
      >
        <div
          style={{
            height: '100%',
            width: `${pct}%`,
            background: 'linear-gradient(90deg, #5A8A5E, #C9A84C)',
            borderRadius: '6px',
            transition: 'width 0.4s ease',
          }}
        />
      </div>
      {/* Step dots */}
      <div style={{ display: 'flex', gap: '6px', marginTop: '10px', flexWrap: 'wrap' }}>
        {Array.from({ length: total }).map((_, i) => (
          <div
            key={i}
            style={{
              width: '8px',
              height: '8px',
              borderRadius: '50%',
              background: i < current ? '#5A8A5E' : i === current - 1 ? '#C9A84C' : 'rgba(0,0,0,0.1)',
              transition: 'background 0.3s ease',
              flexShrink: 0,
            }}
          />
        ))}
      </div>
    </div>
  );
}

// ─── Validation Helper ────────────────────────────────────────────────────────

function validateSection(
  section: FormSection,
  answers: Record<string, unknown>
): Set<string> {
  const errors = new Set<string>();
  for (const q of section.questions) {
    if (q.type === 'section_header') continue;
    // Required fields must have a value
    if (q.required) {
      const val = answers[q.key];
      if (val === undefined || val === null || val === '') {
        errors.add(q.key);
      }
    }
    // yes_no and yes_no_explain are always required (clinical forms)
    if (q.type === 'yes_no' || q.type === 'yes_no_explain') {
      const val = answers[q.key];
      if (val === undefined || val === null || val === '') {
        errors.add(q.key);
      }
    }
  }
  return errors;
}

// ─── Main Renderer ────────────────────────────────────────────────────────────

export default function IntakeFormRenderer({
  form,
  onSubmit,
  onCancel,
  onSaveDraft,
  initialAnswers,
  initialSectionIdx = 0,
  submitting = false,
}: IntakeFormRendererProps) {
  const [currentSectionIdx, setCurrentSectionIdx] = useState(initialSectionIdx);
  const [answers, setAnswers] = useState<Record<string, unknown>>(initialAnswers ?? {});
  const [stopFlag, setStopFlag] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Set<string>>(new Set());
  const [savingDraft, setSavingDraft] = useState(false);
  const [draftSaved, setDraftSaved] = useState(false);
  const [showDraftRestoredBanner, setShowDraftRestoredBanner] = useState(
    !!initialAnswers && Object.keys(initialAnswers).length > 0
  );

  const activeSections = form.sections.filter((s) => s.questions.length > 0);
  const currentSection = activeSections[currentSectionIdx];
  const isLastSection = currentSectionIdx === activeSections.length - 1;

  // Auto-dismiss draft restored banner
  useEffect(() => {
    if (showDraftRestoredBanner) {
      const t = setTimeout(() => setShowDraftRestoredBanner(false), 5000);
      return () => clearTimeout(t);
    }
  }, [showDraftRestoredBanner]);

  const handleAnswer = (key: string, value: unknown) => {
    setAnswers((prev) => ({ ...prev, [key]: value }));
    // Clear validation error for this field when answered
    setValidationErrors((prev) => {
      const next = new Set(prev);
      next.delete(key);
      return next;
    });
    // Reset draft saved indicator on new input
    setDraftSaved(false);

    // Check for stop flags
    const question = activeSections
      .flatMap((s) => s.questions)
      .find((q) => q.key === key);

    if (question?.stopIfYes && value === 'Yes') {
      setStopFlag(question.label);
    } else if (question?.stopIfYes && value === 'No') {
      const otherStopFlags = activeSections
        .flatMap((s) => s.questions)
        .filter((q) => q.stopIfYes && q.key !== key)
        .some((q) => answers[q.key] === 'Yes');
      if (!otherStopFlags) setStopFlag(null);
    }
  };

  const handleNext = () => {
    if (!currentSection) return;
    const errors = validateSection(currentSection, answers);
    if (errors.size > 0) {
      setValidationErrors(errors);
      // Scroll to first error
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    setValidationErrors(new Set());
    if (currentSectionIdx < activeSections.length - 1) {
      setCurrentSectionIdx((prev) => prev + 1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const handleBack = () => {
    setValidationErrors(new Set());
    if (currentSectionIdx > 0) {
      setCurrentSectionIdx((prev) => prev - 1);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const handleSaveDraft = async () => {
    if (!onSaveDraft) return;
    setSavingDraft(true);
    try {
      await onSaveDraft(answers, currentSectionIdx);
      setDraftSaved(true);
    } finally {
      setSavingDraft(false);
    }
  };

  const handleSubmit = async () => {
    if (!currentSection) return;
    const errors = validateSection(currentSection, answers);
    if (errors.size > 0) {
      setValidationErrors(errors);
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    setValidationErrors(new Set());
    await onSubmit(answers);
  };

  const errorCount = validationErrors.size;

  return (
    <div>
      {/* Form Header */}
      <div style={{ marginBottom: '24px' }}>
        <div
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: '6px',
            padding: '4px 12px',
            borderRadius: '20px',
            background: 'rgba(201,168,76,0.1)',
            border: '1px solid rgba(201,168,76,0.2)',
            marginBottom: '10px',
          }}
        >
          <span
            style={{
              fontFamily: 'JetBrains Mono, monospace',
              fontSize: '10px',
              fontWeight: 600,
              color: '#A07820',
              letterSpacing: '0.08em',
              textTransform: 'uppercase',
            }}
          >
            {form.serviceType} · {form.formType === 'SCREENING' ? 'Initial Screening' : 'Re-Assessment'}
          </span>
        </div>
        <h2
          style={{
            fontFamily: 'Cormorant Garamond, Georgia, serif',
            fontSize: '28px',
            fontWeight: 600,
            color: '#1A1A1A',
            letterSpacing: '-0.02em',
            lineHeight: 1.2,
            marginBottom: '4px',
          }}
        >
          {form.title}
        </h2>
        <p
          style={{
            fontFamily: 'DM Sans, system-ui, sans-serif',
            fontSize: '14px',
            color: '#6A6A6A',
          }}
        >
          {form.subtitle}
        </p>
      </div>

      {/* Draft Restored Banner */}
      {showDraftRestoredBanner && (
        <div
          style={{
            background: 'rgba(90,90,138,0.07)',
            border: '1px solid rgba(90,90,138,0.2)',
            borderRadius: '12px',
            padding: '12px 16px',
            marginBottom: '16px',
            display: 'flex',
            alignItems: 'center',
            gap: '10px',
          }}
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5A5A8A" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
          </svg>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#3A3A6A', flex: 1 }}>
            <strong>Draft restored.</strong> Your previous progress has been loaded — continue where you left off.
          </p>
          <button
            type="button"
            onClick={() => setShowDraftRestoredBanner(false)}
            style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#8A8A8A', padding: '2px', lineHeight: 1 }}
          >
            ✕
          </button>
        </div>
      )}

      {/* Progress */}
      <ProgressBar
        current={currentSectionIdx + 1}
        total={activeSections.length}
      />

      {/* Validation Error Summary */}
      {errorCount > 0 && (
        <div
          style={{
            background: 'rgba(180,60,60,0.06)',
            border: '1px solid rgba(180,60,60,0.2)',
            borderRadius: '12px',
            padding: '12px 16px',
            marginBottom: '16px',
            display: 'flex',
            alignItems: 'center',
            gap: '10px',
          }}
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#B43C3C" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <p style={{ fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', color: '#8A2020' }}>
            Please complete {errorCount} required {errorCount === 1 ? 'field' : 'fields'} before continuing.
          </p>
        </div>
      )}

      {/* Stop Flag Warning */}
      {stopFlag && (
        <div
          style={{
            background: 'rgba(180,60,60,0.06)',
            border: '1px solid rgba(180,60,60,0.2)',
            borderRadius: '12px',
            padding: '14px 16px',
            marginBottom: '20px',
          }}
        >
          <p
            style={{
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '14px',
              fontWeight: 600,
              color: '#B43C3C',
              marginBottom: '4px',
            }}
          >
            ⚠️ Physician Consultation Required
          </p>
          <p
            style={{
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '13px',
              color: '#6A2A2A',
              lineHeight: 1.5,
            }}
          >
            Based on your answer, a physician must review your case before proceeding. You may still complete this form — it will be flagged for immediate physician review.
          </p>
        </div>
      )}

      {/* Current Section */}
      {currentSection && (
        <FormSectionBlock
          section={currentSection}
          answers={answers}
          onAnswer={handleAnswer}
          validationErrors={validationErrors}
        />
      )}

      {/* Navigation */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          paddingTop: '16px',
          borderTop: '1px solid rgba(0,0,0,0.07)',
          gap: '12px',
          flexWrap: 'wrap',
        }}
      >
        <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap', alignItems: 'center' }}>
          {onCancel && (
            <button
              type="button"
              onClick={onCancel}
              style={{
                padding: '10px 18px',
                borderRadius: '10px',
                border: '1px solid rgba(0,0,0,0.12)',
                background: 'transparent',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontSize: '13px',
                color: '#6A6A6A',
                cursor: 'pointer',
              }}
            >
              Cancel
            </button>
          )}
          {currentSectionIdx > 0 && (
            <button
              type="button"
              onClick={handleBack}
              style={{
                padding: '10px 18px',
                borderRadius: '10px',
                border: '1px solid rgba(0,0,0,0.12)',
                background: 'transparent',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontSize: '13px',
                color: '#4A4A4A',
                cursor: 'pointer',
              }}
            >
              ← Back
            </button>
          )}
          {/* Save Draft Button */}
          {onSaveDraft && (
            <button
              type="button"
              onClick={handleSaveDraft}
              disabled={savingDraft}
              style={{
                padding: '10px 18px',
                borderRadius: '10px',
                border: `1px solid ${draftSaved ? 'rgba(90,138,94,0.4)' : 'rgba(90,90,138,0.3)'}`,
                background: draftSaved ? 'rgba(90,138,94,0.06)' : 'rgba(90,90,138,0.05)',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontSize: '13px',
                fontWeight: 500,
                color: draftSaved ? '#3A7A3E' : '#5A5A8A',
                cursor: savingDraft ? 'not-allowed' : 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '6px',
                transition: 'all 0.2s ease',
                opacity: savingDraft ? 0.7 : 1,
              }}
            >
              {savingDraft ? (
                <>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ animation: 'spin 1s linear infinite' }}>
                    <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                  </svg>
                  Saving…
                </>
              ) : draftSaved ? (
                <>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M20 6 9 17l-5-5" />
                  </svg>
                  Draft Saved
                </>
              ) : (
                <>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                  </svg>
                  Save Draft
                </>
              )}
            </button>
          )}
        </div>

        {isLastSection ? (
          <button
            type="button"
            onClick={handleSubmit}
            disabled={submitting}
            style={{
              padding: '11px 28px',
              borderRadius: '10px',
              border: 'none',
              background: submitting ? 'rgba(90,138,94,0.5)' : '#5A8A5E',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '14px',
              fontWeight: 600,
              color: '#FFFFFF',
              cursor: submitting ? 'not-allowed' : 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '8px',
              transition: 'background 0.2s ease',
            }}
          >
            {submitting ? (
              <>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ animation: 'spin 1s linear infinite' }}>
                  <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                </svg>
                Submitting…
              </>
            ) : (
              <>
                Submit Form
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M20 6 9 17l-5-5" />
                </svg>
              </>
            )}
          </button>
        ) : (
          <button
            type="button"
            onClick={handleNext}
            style={{
              padding: '11px 28px',
              borderRadius: '10px',
              border: 'none',
              background: '#1A1A1A',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              fontSize: '14px',
              fontWeight: 600,
              color: '#FFFFFF',
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '8px',
            }}
          >
            Next →
          </button>
        )}
      </div>

      <style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>
    </div>
  );
}
