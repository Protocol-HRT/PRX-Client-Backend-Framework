'use client';
import React, { useState } from 'react';

interface Question {
  id: string;
  text: string;
  options: { label: string; value: string; him: number; her: number }[];
}

const questions: Question[] = [
  {
    id: 'gender',
    text: 'How do you identify?',
    options: [
      { label: 'Male', value: 'male', him: 3, her: 0 },
      { label: 'Female', value: 'female', him: 0, her: 3 },
      { label: 'Prefer not to say', value: 'other', him: 1, her: 1 },
    ],
  },
  {
    id: 'energy',
    text: 'How would you describe your energy levels?',
    options: [
      { label: 'Constantly exhausted', value: 'low', him: 2, her: 2 },
      { label: 'Inconsistent — crashes mid-day', value: 'mid', him: 1, her: 2 },
      { label: 'Decent but not what it used to be', value: 'ok', him: 1, her: 1 },
      { label: 'Strong and steady', value: 'high', him: 0, her: 0 },
    ],
  },
  {
    id: 'body',
    text: 'What body changes have you noticed?',
    options: [
      { label: 'Gaining fat, losing muscle', value: 'fat', him: 2, her: 1 },
      { label: 'Weight gain around hips/belly', value: 'hips', him: 0, her: 2 },
      { label: 'Difficulty building muscle', value: 'muscle', him: 2, her: 0 },
      { label: 'Bloating and water retention', value: 'bloat', him: 0, her: 2 },
    ],
  },
  {
    id: 'mood',
    text: 'How is your mood and mental clarity?',
    options: [
      { label: 'Brain fog, hard to focus', value: 'fog', him: 1, her: 2 },
      { label: 'Irritable or short-tempered', value: 'irritable', him: 2, her: 1 },
      { label: 'Anxious or emotionally flat', value: 'anxious', him: 0, her: 2 },
      { label: 'Low motivation and drive', value: 'drive', him: 2, her: 1 },
    ],
  },
  {
    id: 'sleep',
    text: 'How is your sleep quality?',
    options: [
      { label: 'Wake up exhausted no matter what', value: 'exhausted', him: 2, her: 2 },
      { label: 'Night sweats or hot flashes', value: 'sweats', him: 0, her: 3 },
      { label: 'Trouble falling or staying asleep', value: 'insomnia', him: 1, her: 2 },
      { label: 'Sleep is fine', value: 'fine', him: 0, her: 0 },
    ],
  },
];

type Result = 'him' | 'her' | null;

export default function SymptomQuiz() {
  const [step, setStep] = useState<'intro' | 'quiz' | 'result'>('intro');
  const [currentQ, setCurrentQ] = useState(0);
  const [scores, setScores] = useState({ him: 0, her: 0 });
  const [result, setResult] = useState<Result>(null);
  const [selected, setSelected] = useState<string | null>(null);

  const handleStart = () => setStep('quiz');

  const handleSelect = (option: Question['options'][0]) => {
    setSelected(option.value);
    const newScores = { him: scores.him + option.him, her: scores.her + option.her };
    setTimeout(() => {
      if (currentQ < questions.length - 1) {
        setScores(newScores);
        setCurrentQ(currentQ + 1);
        setSelected(null);
      } else {
        const finalResult: Result = newScores.him >= newScores.her ? 'him' : 'her';
        setResult(finalResult);
        setStep('result');
      }
    }, 320);
  };

  const handleReset = () => {
    setStep('intro');
    setCurrentQ(0);
    setScores({ him: 0, her: 0 });
    setResult(null);
    setSelected(null);
  };

  const progress = ((currentQ) / questions.length) * 100;

  return (
    <section
      id="symptom-quiz"
      className="py-20 px-4 sm:px-6 lg:px-8"
      style={{ background: '#0D0D0D', borderTop: '1px solid rgba(255,255,255,0.05)' }}
    >
      <div className="max-w-2xl mx-auto">
        {/* Header */}
        <div className="text-center mb-10">
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
            Personalized Protocol Finder
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
            Which protocol is{' '}
            <em style={{ color: '#C9A84C', fontStyle: 'italic' }}>right for you?</em>
          </h2>
          <p
            style={{
              color: 'rgba(255,255,255,0.45)',
              fontSize: '15px',
              marginTop: '12px',
              fontFamily: 'DM Sans, system-ui, sans-serif',
              lineHeight: 1.6,
            }}
          >
            Answer 5 quick questions and we&apos;ll match you to the HIM or HER protocol.
          </p>
        </div>

        {/* Card */}
        <div
          style={{
            background: 'rgba(255,255,255,0.03)',
            border: '1px solid rgba(201,168,76,0.18)',
            borderRadius: '24px',
            padding: '36px 32px',
          }}
        >
          {/* INTRO */}
          {step === 'intro' && (
            <div className="text-center">
              <div
                className="w-20 h-20 mx-auto mb-6 rounded-full flex items-center justify-center"
                style={{ background: 'rgba(201,168,76,0.1)', border: '1px solid rgba(201,168,76,0.25)' }}
              >
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
                  <path d="M9 12l2 2 4-4" />
                  <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" />
                </svg>
              </div>
              <h3
                style={{
                  fontFamily: 'Cormorant Garamond, serif',
                  fontSize: '26px',
                  fontWeight: 700,
                  color: '#FFFFFF',
                  marginBottom: '12px',
                }}
              >
                Find Your Protocol in 60 Seconds
              </h3>
              <p style={{ color: 'rgba(255,255,255,0.45)', fontSize: '14px', lineHeight: 1.7, marginBottom: '28px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
                Tell us about your symptoms and we&apos;ll recommend the protocol designed specifically for your biology.
              </p>
              <button
                onClick={handleStart}
                style={{
                  background: '#C9A84C',
                  color: '#0D0D0D',
                  fontFamily: 'DM Sans, system-ui, sans-serif',
                  fontWeight: 600,
                  fontSize: '14px',
                  letterSpacing: '0.06em',
                  textTransform: 'uppercase',
                  padding: '14px 36px',
                  borderRadius: '100px',
                  border: 'none',
                  cursor: 'pointer',
                  transition: 'opacity 0.2s',
                }}
                onMouseEnter={(e) => (e.currentTarget.style.opacity = '0.85')}
                onMouseLeave={(e) => (e.currentTarget.style.opacity = '1')}
              >
                Start the Quiz →
              </button>
            </div>
          )}

          {/* QUIZ */}
          {step === 'quiz' && (
            <div>
              {/* Progress bar */}
              <div className="mb-6">
                <div className="flex justify-between mb-2">
                  <span style={{ color: 'rgba(255,255,255,0.3)', fontSize: '12px', fontFamily: 'JetBrains Mono, monospace' }}>
                    Question {currentQ + 1} of {questions.length}
                  </span>
                  <span style={{ color: '#C9A84C', fontSize: '12px', fontFamily: 'JetBrains Mono, monospace' }}>
                    {Math.round(((currentQ + 1) / questions.length) * 100)}%
                  </span>
                </div>
                <div style={{ height: '3px', background: 'rgba(255,255,255,0.08)', borderRadius: '2px' }}>
                  <div
                    style={{
                      height: '100%',
                      width: `${((currentQ + 1) / questions.length) * 100}%`,
                      background: '#C9A84C',
                      borderRadius: '2px',
                      transition: 'width 0.4s ease',
                    }}
                  />
                </div>
              </div>

              <h3
                style={{
                  fontFamily: 'Cormorant Garamond, serif',
                  fontSize: 'clamp(20px, 3vw, 26px)',
                  fontWeight: 700,
                  color: '#FFFFFF',
                  marginBottom: '20px',
                  lineHeight: 1.2,
                }}
              >
                {questions[currentQ].text}
              </h3>

              <div className="flex flex-col gap-3">
                {questions[currentQ].options.map((opt) => (
                  <button
                    key={opt.value}
                    onClick={() => handleSelect(opt)}
                    style={{
                      background: selected === opt.value ? 'rgba(201,168,76,0.15)' : 'rgba(255,255,255,0.03)',
                      border: selected === opt.value ? '1px solid rgba(201,168,76,0.6)' : '1px solid rgba(255,255,255,0.1)',
                      borderRadius: '12px',
                      padding: '14px 18px',
                      textAlign: 'left',
                      color: selected === opt.value ? '#C9A84C' : 'rgba(255,255,255,0.75)',
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      fontSize: '15px',
                      cursor: 'pointer',
                      transition: 'all 0.2s',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '12px',
                    }}
                    onMouseEnter={(e) => {
                      if (selected !== opt.value) {
                        e.currentTarget.style.borderColor = 'rgba(201,168,76,0.35)';
                        e.currentTarget.style.background = 'rgba(201,168,76,0.06)';
                      }
                    }}
                    onMouseLeave={(e) => {
                      if (selected !== opt.value) {
                        e.currentTarget.style.borderColor = 'rgba(255,255,255,0.1)';
                        e.currentTarget.style.background = 'rgba(255,255,255,0.03)';
                      }
                    }}
                  >
                    <span
                      style={{
                        width: '20px',
                        height: '20px',
                        borderRadius: '50%',
                        border: selected === opt.value ? '2px solid #C9A84C' : '2px solid rgba(255,255,255,0.2)',
                        flexShrink: 0,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: selected === opt.value ? '#C9A84C' : 'transparent',
                        transition: 'all 0.2s',
                      }}
                    >
                      {selected === opt.value && (
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                          <path d="M2 5l2 2 4-4" stroke="#0D0D0D" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                      )}
                    </span>
                    {opt.label}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* RESULT */}
          {step === 'result' && result && (
            <div className="text-center">
              <div
                className="w-16 h-16 mx-auto mb-5 rounded-full flex items-center justify-center"
                style={{
                  background: result === 'him' ? 'rgba(90,122,138,0.15)' : 'rgba(201,168,76,0.12)',
                  border: result === 'him' ? '1px solid rgba(90,122,138,0.4)' : '1px solid rgba(201,168,76,0.35)',
                }}
              >
                {result === 'him' ? (
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#5A7A8A" strokeWidth="1.5">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
                  </svg>
                ) : (
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="1.5">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
                  </svg>
                )}
              </div>

              <span
                style={{
                  fontFamily: 'JetBrains Mono, monospace',
                  fontSize: '11px',
                  letterSpacing: '0.12em',
                  textTransform: 'uppercase',
                  color: result === 'him' ? '#5A7A8A' : '#C9A84C',
                  display: 'block',
                  marginBottom: '8px',
                }}
              >
                Your Match
              </span>

              <h3
                style={{
                  fontFamily: 'Cormorant Garamond, serif',
                  fontSize: 'clamp(28px, 4vw, 40px)',
                  fontWeight: 700,
                  color: '#FFFFFF',
                  marginBottom: '12px',
                  lineHeight: 1.1,
                }}
              >
                The{' '}
                <em style={{ color: result === 'him' ? '#5A7A8A' : '#C9A84C', fontStyle: 'italic' }}>
                  {result === 'him' ? 'HIM Protocol' : 'HER Protocol'}
                </em>
              </h3>

              <p style={{ color: 'rgba(255,255,255,0.5)', fontSize: '15px', lineHeight: 1.7, marginBottom: '24px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>
                {result === 'him' ?'Based on your symptoms, the HIM Protocol — testosterone optimization, peptide support, and metabolic reset — is your ideal starting point.' :'Based on your symptoms, the HER Protocol — hormone balance, peptide therapy, and metabolic optimization — is designed for exactly what you described.'}
              </p>

              <div className="flex flex-col sm:flex-row gap-3 justify-center">
                <a
                  href="/checkout"
                  style={{
                    background: '#C9A84C',
                    color: '#0D0D0D',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontWeight: 600,
                    fontSize: '14px',
                    letterSpacing: '0.06em',
                    textTransform: 'uppercase',
                    padding: '14px 28px',
                    borderRadius: '100px',
                    textDecoration: 'none',
                    display: 'inline-block',
                    transition: 'opacity 0.2s',
                  }}
                  onMouseEnter={(e) => (e.currentTarget.style.opacity = '0.85')}
                  onMouseLeave={(e) => (e.currentTarget.style.opacity = '1')}
                >
                  Build My {result === 'him' ? 'HIM' : 'HER'} Protocol →
                </a>
                <button
                  onClick={handleReset}
                  style={{
                    background: 'transparent',
                    color: 'rgba(255,255,255,0.4)',
                    fontFamily: 'DM Sans, system-ui, sans-serif',
                    fontSize: '13px',
                    padding: '14px 20px',
                    borderRadius: '100px',
                    border: '1px solid rgba(255,255,255,0.1)',
                    cursor: 'pointer',
                    transition: 'color 0.2s',
                  }}
                  onMouseEnter={(e) => (e.currentTarget.style.color = 'rgba(255,255,255,0.7)')}
                  onMouseLeave={(e) => (e.currentTarget.style.color = 'rgba(255,255,255,0.4)')}
                >
                  Retake Quiz
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
