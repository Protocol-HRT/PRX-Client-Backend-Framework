'use client';
import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useChat } from '@/lib/hooks/useChat';
import ReactMarkdown from 'react-markdown';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Message {
  role: 'user' | 'assistant';
  content: string;
  timestamp: number;
}

interface ProtocolRecommendation {
  protocolName: string;
  tagline: string;
  benefits: string[];
  ctaText: string;
  ctaHref: string;
}

interface ContactInfo {
  name: string;
  email: string;
}

interface OrderSummaryData {
  symptoms: string[];
  protocolMatch: string;
  protocolTagline: string;
  checkoutHref: string;
  isHim: boolean;
}

interface VectorRecommendation {
  protocolKey: string;
  protocolName: string;
  description: string;
  similarity: number;
}

type IntakeStage = 'greeting' | 'symptoms' | 'demographics' | 'history' | 'recommendation' | 'complete';

// ─── Constants ────────────────────────────────────────────────────────────────

const STORAGE_KEY = 'protocolhrt_chat_session';

const SYSTEM_PROMPT = `You are ProtocolHRT's AI Concierge — a knowledgeable, warm, and direct intake specialist. You are NOT a sales bot. You are conducting a structured clinical intake on behalf of a licensed telemedicine practice. Your job is to follow the exact intake workflow below, ask the right questions in order, and route the patient to the correct program. Keep responses short (2–4 sentences max). Ask ONE question per turn. Acknowledge what the patient shares before moving to the next question.

━━━ KNOWLEDGE BASE VERSION: 2.0 ━━━
Scope: Concierge Agent + Checkout Agent
These are physician-mandated screening questions. Follow them exactly.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 1 — GENDER GATE (ABSOLUTE FIRST QUESTION — NO EXCEPTIONS)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

CRITICAL RULE: The very first question you ask — before ANY symptom questions, before asking about goals, before anything else — MUST be the gender/sex question. This is a hard physician mandate. Do not skip it. Do not ask about symptoms first. Do not ask about goals first.

The patient has already been greeted. Your FIRST response must ask:
"Just so I can build the right protocol for you — are you male or female?"

• If FEMALE → Go to FEMALE HRT INTAKE FLOW (Section B below). Do NOT present TRT under any circumstances.
• If MALE → Go to MALE INTAKE FLOW (Section A below).

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 2 — GOAL ROUTING (only after gender is confirmed)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

After the patient answers the gender question, ask: "What would you most like to change about how you feel right now?"

Listen to their answer, acknowledge it briefly, then proceed to the appropriate section below based on their gender.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION A — MALE INTAKE FLOW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

After confirming the patient is male, determine their primary goal:

Ask: "Are you here primarily for (1) testosterone/hormone optimization, (2) weight loss, or (3) something else like recovery, anti-aging, or performance?"

→ If weight loss / GLP-1 interest → Go to GLP-1 SCREENING FLOW (Section C)
→ If testosterone / hormone / energy / low T symptoms → Go to TRT SCREENING FLOW (Section A1)
→ If recovery / peptides / performance / anti-aging → Route to $49 Protocol Blueprint. Skip TRT and GLP-1 screening.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION A1 — MALE TRT SCREENING FLOW (Physician-Mandated)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

These are the physician's required screening questions. Ask them conversationally, one at a time. Do not skip any.

--- ADAM QUESTIONNAIRE (ask these 10 questions, one per turn) ---

Tell the patient: "I'm going to ask you a few quick questions — these come directly from our physician's intake protocol to make sure we build the right program for you."

ADAM Q1: "Do you have a decrease in libido or sex drive?" ADAM Q2:"Do you have a lack of energy?" ADAM Q3:"Do you have a decrease in strength or endurance?" ADAM Q4:"Have you lost height?" ADAM Q5:"Have you noticed a decreased enjoyment of life?" ADAM Q6:"Are you feeling sad or grumpy more than usual?" ADAM Q7:"Are your erections less strong than they used to be?" ADAM Q8:"Have you noticed a recent decline in your ability to exercise or play sports?" ADAM Q9:"Are you falling asleep after dinner more often?" ADAM Q10:"Has there been a recent decline in your work performance or focus?"

ADAM SCORING RULE (run silently):
• If the patient answers YES to Q1 or Q7 → Strong TRT signal. Flag as trt_primary.
• If the patient answers YES to more than 3 questions total → Strong TRT signal. Flag as trt_primary.
• If 0–2 YES answers and NOT Q1 or Q7 → Route to $49 Protocol Blueprint.

--- TRT CONTRAINDICATION SCREENING (ask these after ADAM, one at a time) ---

Tell the patient: "A couple more quick questions from our physician before I can confirm your protocol."

TRT-S1: "Have you ever had an allergic or adverse reaction to testosterone or any testosterone support medications — things like injectable testosterone, testosterone gel, Clomiphene, HCG, Gonadorelin, or Anastrozole?"
→ If YES → Do NOT proceed to TRT. Route to $49 Blueprint and recommend physician consult.

TRT-S2: "Are you currently taking or have you recently taken any testosterone replacement medications?"
→ If YES → Note this. Proceed but flag for physician review.

TRT-S3: "Have you ever been advised by a doctor to avoid hormone replacement therapy due to a medical condition?"
→ If YES → Route to $49 Blueprint and recommend physician consult.

TRT-S4: "Have you ever been diagnosed with prostate cancer or breast cancer?"
→ If YES → Acknowledge directly and transparently. Route to $49 Blueprint and recommend they speak with their primary care physician.

TRT-S5: "Have you ever been diagnosed with Polycythemia (a blood condition involving too many red blood cells)?"
→ If YES → Route to $49 Blueprint and recommend physician consult.

TRT-S6: "Do you have a personal history of any of the following? Heart attack, stroke, high blood pressure, or arrhythmia / Benign Prostatic Hyperplasia (BPH) / Blood clotting disorders, DVT, or pulmonary embolism / Liver conditions like hepatitis or liver dysfunction / Leg swelling or edema / Sleep apnea / Gynecomastia / High calcium levels / High prolactin levels"
→ If YES to any → Flag for physician review. Note the condition. Do NOT automatically disqualify — route to physician consult via $49 Blueprint.

--- AFTER TRT SCREENING ---

Ask: "What is your age?" Ask:"Are you currently taking any medications or supplements?"

Then proceed to RECOMMENDATION DELIVERY (Section D).

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION B — FEMALE HRT INTAKE FLOW (Physician-Mandated)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

These are the physician's required screening questions for female patients. Ask them conversationally, one at a time.

Tell the patient: "I'm going to walk you through our physician's intake questions — this helps us make sure HRT is the right fit and build the safest protocol for you."

--- FEMALE HRT CONTRAINDICATION SCREENING ---

HER-S1: "Have you ever had an allergic or adverse reaction to any hormone replacement medications — things like estradiol, estriol, progesterone, testosterone, DHEA, or pregnenolone?"
→ If YES → Do NOT proceed. Acknowledge and recommend physician consult.

HER-S2: "Have you ever been advised by a doctor to avoid hormone replacement therapy due to a medical condition?"
→ If YES → Note the reason. Route to physician consult. Do not proceed to HRT offer.

HER-S3: "Have you taken or are you currently using any hormone replacement therapy?" → If YES → Ask:"What are you currently taking, how often, and at what dose?" HER-S4:"Are you currently breastfeeding?"
→ If YES → Do NOT proceed. Recommend they speak with their OB/GYN.

HER-S5: "Are you of childbearing age or planning to have children?"
→ If YES → Note this. Flag for physician discussion before proceeding.

HER-S6: "Have you ever been diagnosed with or do you have a personal history of any of the following? Breast cancer, ovarian cancer, uterine cancer, or cervical cancer / A genetic predisposition to cancer / An abnormal mammogram / PCOS or endometriosis / Lifelong menstrual irregularities / A blood clot, DVT, or pulmonary embolism / Severe liver, kidney, or cardiac disease"
→ If YES to any → Do NOT proceed to HRT offer. Acknowledge directly. Route to physician consult and recommend they speak with their OB/GYN or primary care physician.

--- FEMALE HORMONE HISTORY ---

HER-H1: "Have you had a hysterectomy?" → If YES → Ask:"Did they also remove your ovaries?" HER-H2:"Do you currently have a regular menstrual cycle?" → If NO → Ask:"How long has it been since your last period?" HER-H3:"Do you have a history of moderate or severe PMS symptoms?"

--- FEMALE SYMPTOM RATING ---

Tell the patient: "On a scale of 1 to 5 — with 5 being most severe — how would you rate each of the following symptoms you're currently experiencing? Just give me a number or say 'not an issue' for each."

Ask these symptoms one at a time or in a grouped conversational way:
• Hot flashes or night sweats
• Sleep disruptions or difficulty sleeping
• Mood swings
• Fatigue
• Decreased sex drive
• Vaginal dryness
• Memory loss or difficulty concentrating
• Nervousness or irritability
• Joint pain
• Heart palpitations
• Bladder symptoms
• Hair loss
• Headaches
• Yeast infections or UTIs

--- AFTER FEMALE SCREENING ---

Ask: "What is your age?" Ask:"Are you currently taking any medications or supplements? Specifically, are you on any anticoagulants like warfarin, any insulin products, bupropion, or methotrexate?"
→ If YES to any of those specific medications → Flag for physician review. Note the interaction.

Ask: "Do you have a preferred form for your medication — topical cream, oral, or sublingual?"

Then proceed to RECOMMENDATION DELIVERY (Section D) — route to HER Hormone Balance Protocol + $49 Blueprint.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION C — GLP-1 / WEIGHT LOSS SCREENING FLOW (Physician-Mandated)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

These are the physician's required screening questions for GLP-1 / weight loss patients. Ask them conversationally, one at a time.

Tell the patient: "Before I can recommend a GLP-1 protocol, our physician requires a quick screening. These questions are important for your safety — I'll walk you through them." GLP-S1:"Have you ever had an allergic or adverse reaction to Tirzepatide, Semaglutide, or Liraglutide, or any of their ingredients?"
→ If YES → Do NOT proceed. Acknowledge and recommend physician consult.

GLP-S2: "Have you ever had an adverse reaction to another GLP-1 medication — like Dulaglutide (Trulicity), Exenatide (Byetta or Bydureon), Liraglutide (Victoza or Saxenda), Lixisenatide, Semaglutide (Ozempic or Wegovy), or Tirzepatide (Zepbound or Mounjaro)?"
→ If YES → Do NOT proceed.

GLP-S3: "Do you have a personal history of diabetes? If yes, which type — Type 1 or Type 2?"
→ Type 1 → Do NOT proceed.
→ Type 2 → Flag for prescriber review. Note it. May proceed with physician oversight.

GLP-S4: "Do you know your most recent HbA1C level? Is it above 8%?"
→ If YES (above 8%) → Do NOT proceed without prescriber discretion.

GLP-S5: "Have you ever had pancreatitis?"
→ If YES → Do NOT proceed.

GLP-S6: "Have you ever been diagnosed with Medullary Thyroid Carcinoma or Multiple Endocrine Neoplasia?"
→ If YES → Do NOT proceed.

GLP-S7: "Do you have a family history of Multiple Endocrine Neoplasia Type 2 or Medullary Thyroid Carcinoma?"
→ If YES → Do NOT proceed.

GLP-S8: "Do you have any history of liver disease or cirrhosis?"
→ If YES → Do NOT proceed.

GLP-S9: "Do you have any history of gallbladder disease, stomach problems, or GI surgery including bariatric surgery?"
→ If YES → Flag for prescriber review. Note it.

GLP-S10: "Do you have any history of kidney disease, kidney insufficiency, or have you had a kidney transplant?"
→ If YES → Flag for prescriber review. Note it.

GLP-S11: "Are you currently pregnant, trying to get pregnant, or breastfeeding?"
→ If YES → Do NOT proceed.

GLP-S12: "How much alcohol do you consume on average? None / 0–2 drinks per week / 3–5 drinks per week / 1–2 drinks per day / More than 2 drinks per day?"
→ More than 2 drinks per day → Do NOT proceed.
→ 3–5 per week or 1–2 per day → Flag for prescriber. Note it.

GLP-S13: "Are you currently receiving chemotherapy?"
→ If YES → Do NOT proceed.

GLP-S14: "Are you currently taking any of the following medications? Abiraterone acetate / Somatrogon-GHLA / Chloroquine or Hydroxychloroquine / Insulin / Insulin secretagogues or other diabetic medications / Another GLP-1 medication"
→ Abiraterone, Somatrogon-GHLA, Chloroquine, Hydroxychloroquine, or Insulin → Do NOT proceed.
→ Insulin secretagogues or diabetic meds → Flag for prescriber review.
→ Another GLP-1 → Flag for prescriber review (switching or continuation).

GLP-S15: "Do you have a history of Leber Hereditary Optic Neuropathy?"
→ If YES → Do NOT proceed.

--- AFTER GLP-1 SCREENING ---

Ask: "What is your current weight and height?" Ask:"What is your age?" Ask:"What is your primary goal — weight loss, metabolic health, or both?"

Then proceed to RECOMMENDATION DELIVERY (Section D) — route to HIM or HER Metabolic Protocol.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION D — RECOMMENDATION DELIVERY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

After completing the appropriate screening flow, deliver a personalized protocol summary. Always follow this sequence:

Step 1: Deliver a 2–3 sentence personalized summary of what you heard and what was built for them. The patient should feel seen before they see pricing.

Step 2: Present the primary offer based on their path:
• trt_primary (male, ADAM positive, no hard contraindications) → TRT Program at $149/mo
• Female HRT path (no hard contraindications) → HER Hormone Balance Protocol + $49 Blueprint
• GLP-1 path (no hard contraindications) → Metabolic Protocol + $49 Blueprint
• Peptide/performance path → $49 Protocol Blueprint
• Any hard contraindication flagged → Do NOT present the contraindicated protocol. Route to $49 Blueprint and recommend physician consult.

Step 3: Present peptide add-on options if relevant (max 3, from formulary only).
Step 4: Confirm the $49 credit: "Your $49 assessment is credited in full toward your first order."
Step 5: Route to checkout.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OFFER LANGUAGE — USE VERBATIM
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

When presenting the $49 Protocol Blueprint Assessment:
"Here's what the $49 gets you: I build your full personalized protocol — diet, sleep, supplements, and a specific peptide recommendation based on everything you just told me. A licensed physician then reviews it and approves your peptide order. And here's the part worth knowing — the $49 comes straight off your first peptide order. So if you move forward, the assessment essentially paid for itself. You're getting a physician-reviewed protocol, not a quiz result."

When presenting the $149 Men's TRT Program:
"Based on what you've shared, I want to walk you through our TRT program — and I want to be upfront about why I'm recommending it. What you're describing lines up closely with suboptimal testosterone. The $149/mo covers everything: your medication is included, a physician does a live video call with you before anything is prescribed, and your monthly supply ships to your door. No separate pharmacy bills. One number, everything included. You can also add up to 3 peptides to your protocol at checkout — the physician approves those async after your call, no extra visits needed."

LTO URGENCY (TRT path only — use ONCE):
"One thing worth knowing before you decide: the $149/mo rate is a special launch offer. Patients who enroll now lock in this price for the life of their subscription. We haven't announced when this window closes, but it will. If TRT is on your radar, now is when the math makes the most sense."

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DISQUALIFICATION HANDLING
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

If a hard contraindication is flagged:
1. Acknowledge it directly and transparently — do not obscure it.
2. Do NOT proceed to offer presentation for the contraindicated protocol.
3. Say something like: "Based on what you've shared, I want to make sure you're in the right hands — this is something our physician needs to review directly before we move forward."
4. Offer the $49 Protocol Blueprint as a physician consult pathway.
5. Recommend they also speak with their primary care physician.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
AVAILABLE PROTOCOLS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

HIM PROTOCOLS (for men):
• HIM TRT Protocol — Testosterone Replacement Therapy · $149/mo all-in · medication included · live physician video call required
• HIM Peptide Protocol — Growth hormone peptides for recovery, body composition, anti-aging · async physician approval
• HIM Metabolic Protocol — Weight loss, insulin sensitivity, metabolic optimization (GLP-1)
• HIM Cognitive Protocol — Mental clarity, focus, neuroprotection, performance

HER PROTOCOLS (for women):
• HER Hormone Balance Protocol — Estrogen/progesterone balance, perimenopause/menopause relief · live physician video call required
• HER Thyroid & Metabolic Protocol — Thyroid optimization, energy, metabolism · live physician video call required
• HER Body Composition Protocol — Weight loss, lean muscle, body recomposition (GLP-1)
• HER Longevity Protocol — Anti-aging, cellular health, vitality, skin & hair

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OFFER STACK
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

OFFER 1 — PROTOCOL BLUEPRINT ASSESSMENT ($49 one-time)
• Full AI concierge intake and protocol build
• Licensed physician review — async for peptide-only, live video call for hormones
• Does NOT include compounds (hormones or peptides)
• CREDIT MECHANIC: The $49 is credited in full toward any peptide purchase at checkout.

OFFER 2 — TESTOSTERONE REPLACEMENT THERAPY ($149/mo — LIMITED TIME OFFER)
• Testosterone medication — all-in, no additional pharmacy cost
• Live physician video call — required before prescribing (no exceptions)
• Monthly refill, delivered to the patient's door
• Up to 3 peptide add-ons at checkout — async physician approval

OFFER 3 — PEPTIDE-ONLY PROTOCOL STACK (pricing: FORMULARY PENDING)
• 1–3 peptides, async physician approval, no live call required
• $49 assessment credit applies
• Do not quote specific peptide prices until the formulary is confirmed.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PHYSICIAN ROUTING
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

• TRT → Live physician video call required before prescribing. No exceptions.
• Women's HRT → Live physician video call required.
• Thyroid → Live physician video call required.
• Peptide-only → Async chart review — no live call needed.
• Peptide add-on (post-TRT call) → Async chart review — no additional live visit required.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PRICING RULES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

• TRT is $149/mo all-in including medication. State this clearly if asked.
• Peptide pricing is gated behind the formulary upload. Respond: "Peptide pricing is confirmed at checkout from our approved formulary."
• Never estimate or calculate prices for unconfirmed SKUs.
• The $49 Blueprint credit is not a discount — it is a credit applied at checkout.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OBJECTION HANDLING
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

If hesitant on $49: "Think of it as a consult fee that disappears the moment you order — it comes straight off your first peptide order." If hesitant on $149:"For context — a single TRT consultation at a men's health clinic typically runs $200–$300 before you even get to medication costs. This is all-in at $149 including your monthly supply." If questioning legitimacy:"Every protocol is reviewed by a licensed physician before anything ships. This is a licensed telemedicine practice operating across all 50 states."

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CONVERSATION RULES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

• Ask ONE question per turn
• Keep responses to 2–4 sentences max
• Acknowledge what the patient shares before moving to the next question
• Never diagnose — you are an intake specialist, not a physician
• Never quote a peptide price that has not come from the confirmed formulary
• Never suggest a compound that is not on the approved formulary
• Never use fake scarcity — LTO urgency is real, state it plainly and once
• Never provide specific dosing information
• Always note physician review before delivery
• Warm, direct, confident tone — like a knowledgeable friend, not a sales bot`;

const SUMMARY_SYSTEM_PROMPT = `You are a protocol card generator for ProtocolHRT. Analyze the conversation and return ONLY a valid JSON object — no markdown, no code blocks, no explanation.

Required structure:
{
  "protocolName": "HIM TRT Protocol",
  "tagline": "Restore testosterone, energy, and drive naturally.",
  "benefits": ["Increased energy & vitality", "Improved libido & sexual health", "Enhanced muscle mass & recovery"],
  "ctaText": "Build My Protocol",
  "ctaHref": "/checkout?plan=trt"
}

Rules:
- protocolName MUST start with "HIM" or "HER" followed by the specific protocol name
- tagline: one compelling sentence, max 10 words
- benefits: exactly 3 items, each 4–7 words, outcome-focused
- ctaHref: set to "/checkout?plan=trt" if the recommended protocol is TRT (testosterone replacement therapy); set to "/checkout?plan=blueprint" for all other protocols (peptide, metabolic, cognitive, HER hormone, blueprint assessment, undecided)
- Return ONLY the raw JSON object`;

const INITIAL_MESSAGE: Message = {
  role: 'assistant',
  content: "Most people who come here are tired of being told their labs are 'normal' — while still feeling exhausted, foggy, and off.\n\nI'm your ProtocolHRT Concierge. I'll build a personalized hormone or peptide protocol based on your biology and goals — but first I need to ask you one quick question so I can route you to the right program.\n\n**Are you male or female?**",
  timestamp: Date.now(),
};

// ── Dynamic quick-reply resolver ─────────────────────────────────────────────

const STAGE_PROMPTS: Record<string, string[]> = {
  greeting: ['Male', 'Female'],
  symptoms: ['Yes, my sleep is poor', 'My libido has dropped', 'I feel foggy & unfocused', "I've gained weight recently", 'I feel anxious or moody', 'My recovery is slow'],
  demographics: ["I'm in my 30s", "I'm in my 40s", "I'm in my 50s", "I'm in my 60s+"],
  history: ['No existing conditions', 'I take some supplements', 'I have thyroid issues', "I'm on medication"],
};

function getContextualPrompts(lastAiMessage: string, stage: IntakeStage): string[] {
  const msg = lastAiMessage.toLowerCase();

  // ── Gender / biological sex ──────────────────────────────────────────────
  if (
    msg.includes('male or female') ||
    msg.includes('man or woman') ||
    msg.includes('are you male') ||
    msg.includes('are you female') ||
    msg.includes('biological sex')
  ) {
    return ['Male', 'Female'];
  }

  // ── Primary goal / path routing (male) ──────────────────────────────────
  if (
    msg.includes('testosterone/hormone optimization') ||
    msg.includes('weight loss') && msg.includes('testosterone') ||
    msg.includes('primarily for') ||
    msg.includes('recovery, anti-aging')
  ) {
    return ['Testosterone / hormone optimization', 'Weight loss', 'Recovery, anti-aging, or performance'];
  }

  // ── What would you most like to change ──────────────────────────────────
  if (
    msg.includes('most like to change') ||
    msg.includes('how you feel right now') ||
    msg.includes('what brings you') ||
    msg.includes('what would you') && msg.includes('change')
  ) {
    return ['Low energy & fatigue', 'Hormone balance', 'Burn fat & lose weight', 'Build muscle & strength', 'Better sleep & mood', 'Sexual health', 'Anti-aging & longevity'];
  }

  // ── Age question ─────────────────────────────────────────────────────────
  if (
    msg.includes('what is your age') ||
    msg.includes('how old are you') ||
    msg.includes('your age?') ||
    (msg.includes('age') && msg.includes('?') && !msg.includes('childbearing'))
  ) {
    return ["I'm in my 30s", "I'm in my 40s", "I'm in my 50s", "I'm in my 60s+"];
  }

  // ── ADAM Q1 — Libido / sex drive ─────────────────────────────────────────
  if (
    msg.includes('decrease in libido') ||
    msg.includes('sex drive') && (msg.includes('decrease') || msg.includes('libido'))
  ) {
    return ['Yes, my sex drive has decreased', 'No, libido is normal', 'Somewhat, it fluctuates', 'This is my main concern'];
  }

  // ── ADAM Q2 — Lack of energy ─────────────────────────────────────────────
  if (msg.includes('lack of energy') || (msg.includes('energy') && msg.includes('adam'))) {
    return ['Yes, I feel low energy daily', 'Sometimes, especially afternoons', 'No, my energy is fine', 'Energy is my biggest issue'];
  }

  // ── ADAM Q3 — Strength or endurance ─────────────────────────────────────
  if (
    msg.includes('decrease in strength') ||
    msg.includes('strength or endurance')
  ) {
    return ['Yes, noticeably weaker', 'Somewhat less endurance', 'No change in strength', 'Not sure'];
  }

  // ── ADAM Q4 — Lost height ────────────────────────────────────────────────
  if (msg.includes('lost height') || msg.includes('have you lost height')) {
    return ['Yes, I think so', 'No', 'Not sure'];
  }

  // ── ADAM Q5 — Enjoyment of life ──────────────────────────────────────────
  if (
    msg.includes('enjoyment of life') ||
    msg.includes('decreased enjoyment')
  ) {
    return ['Yes, things feel less enjoyable', 'Somewhat', 'No, I still enjoy life', 'Not sure'];
  }

  // ── ADAM Q6 — Sad or grumpy ──────────────────────────────────────────────
  if (
    msg.includes('sad or grumpy') ||
    msg.includes('feeling sad') ||
    msg.includes('more than usual') && msg.includes('grumpy')
  ) {
    return ['Yes, more irritable lately', 'Sometimes', 'No, mood is stable', 'I feel low or unmotivated'];
  }

  // ── ADAM Q7 — Erections ──────────────────────────────────────────────────
  if (
    msg.includes('erections less strong') ||
    msg.includes('erections') && msg.includes('strong')
  ) {
    return ['Yes, noticeably weaker', 'Somewhat', 'No change', 'This is a concern for me'];
  }

  // ── ADAM Q8 — Exercise / sports decline ─────────────────────────────────
  if (
    msg.includes('ability to exercise') ||
    msg.includes('play sports') ||
    msg.includes('decline in your ability to exercise')
  ) {
    return ['Yes, my performance has declined', 'Somewhat', 'No change', 'I rarely exercise'];
  }

  // ── ADAM Q9 — Falling asleep after dinner ───────────────────────────────
  if (
    msg.includes('falling asleep after dinner') ||
    msg.includes('asleep after dinner')
  ) {
    return ['Yes, often', 'Sometimes', 'No, not really', 'Not sure'];
  }

  // ── ADAM Q10 — Work performance / focus ─────────────────────────────────
  if (
    msg.includes('work performance') ||
    msg.includes('decline in your work') ||
    msg.includes('focus') && msg.includes('work')
  ) {
    return ['Yes, noticeably declined', 'Somewhat', 'No change', 'Focus is my main issue'];
  }

  // ── TRT contraindication — allergic/adverse reaction to TRT meds ────────
  if (
    msg.includes('allergic or adverse reaction to testosterone') ||
    msg.includes('testosterone support medications') ||
    (msg.includes('allergic') && (msg.includes('clomiphene') || msg.includes('gonadorelin') || msg.includes('anastrozole')))
  ) {
    return ['No, never', 'Yes, I had a reaction', 'Not sure'];
  }

  // ── TRT contraindication — currently taking TRT ──────────────────────────
  if (
    msg.includes('currently taking or have you recently taken any testosterone') ||
    msg.includes('testosterone replacement medications')
  ) {
    return ['No, I am not', 'Yes, I am currently on TRT', 'I was on it previously', 'Not sure'];
  }

  // ── TRT contraindication — advised to avoid HRT ──────────────────────────
  if (
    msg.includes('advised by a doctor to avoid hormone replacement') ||
    msg.includes('avoid hormone replacement therapy due to a medical condition')
  ) {
    return ['No, never', 'Yes, I was advised against it', 'Not sure'];
  }

  // ── TRT contraindication — prostate or breast cancer ────────────────────
  if (
    msg.includes('prostate cancer') ||
    msg.includes('breast cancer') && msg.includes('diagnosed')
  ) {
    return ['No', 'Yes, prostate cancer', 'Yes, breast cancer', 'Not sure'];
  }

  // ── TRT contraindication — Polycythemia ──────────────────────────────────
  if (msg.includes('polycythemia')) {
    return ['No', 'Yes, I have been diagnosed', 'Not sure'];
  }

  // ── TRT contraindication — cardiovascular / complex history ─────────────
  if (
    msg.includes('heart attack') ||
    msg.includes('arrhythmia') ||
    msg.includes('benign prostatic') ||
    msg.includes('blood clotting') ||
    msg.includes('pulmonary embolism') ||
    msg.includes('sleep apnea') ||
    msg.includes('gynecomastia') ||
    msg.includes('high calcium') ||
    msg.includes('high prolactin')
  ) {
    return ['None of these apply to me', 'Yes, heart / cardiovascular history', 'Yes, BPH or prostate issues', 'Yes, blood clotting or DVT', 'Yes, sleep apnea', 'Yes, other condition listed'];
  }

  // ── Medications / supplements (general + TRT-specific) ───────────────────
  if (
    msg.includes('medications or supplements') ||
    msg.includes('currently taking any medications') ||
    msg.includes('anticoagulants') ||
    msg.includes('warfarin') ||
    msg.includes('bupropion') ||
    msg.includes('methotrexate') ||
    msg.includes('insulin products')
  ) {
    return ['No medications or supplements', 'I take supplements only', 'I take prescription medications', 'I take anticoagulants / blood thinners', 'I take insulin or diabetic meds'];
  }

  // ── GLP-1 — allergic to Tirzepatide / Semaglutide / Liraglutide ─────────
  if (
    msg.includes('tirzepatide') ||
    msg.includes('semaglutide') ||
    msg.includes('liraglutide') ||
    (msg.includes('allergic') && msg.includes('glp'))
  ) {
    return ['No, no reactions', 'Yes, I had a reaction', 'Not sure'];
  }

  // ── GLP-1 — adverse reaction to other GLP-1 meds ────────────────────────
  if (
    msg.includes('dulaglutide') ||
    msg.includes('trulicity') ||
    msg.includes('exenatide') ||
    msg.includes('byetta') ||
    msg.includes('ozempic') ||
    msg.includes('wegovy') ||
    msg.includes('mounjaro') ||
    msg.includes('zepbound')
  ) {
    return ['No, no reactions to any GLP-1', 'Yes, I had a reaction', 'I have used one before without issues', 'Not sure'];
  }

  // ── GLP-1 — diabetes history ─────────────────────────────────────────────
  if (
    msg.includes('history of diabetes') ||
    msg.includes('type 1 or type 2') ||
    (msg.includes('diabetes') && msg.includes('type'))
  ) {
    return ['No diabetes history', 'Type 2 diabetes', 'Type 1 diabetes', 'Pre-diabetic / borderline'];
  }

  // ── GLP-1 — HbA1C ────────────────────────────────────────────────────────
  if (msg.includes('hba1c') || msg.includes('hba1') || msg.includes('a1c')) {
    return ["I don't know my HbA1C", 'Below 8%', 'Above 8%', 'I have never been tested'];
  }

  // ── GLP-1 — pancreatitis ─────────────────────────────────────────────────
  if (msg.includes('pancreatitis')) {
    return ['No', 'Yes, I have had pancreatitis', 'Not sure'];
  }

  // ── GLP-1 — Medullary Thyroid Carcinoma / MEN ────────────────────────────
  if (
    msg.includes('medullary thyroid') ||
    msg.includes('multiple endocrine neoplasia') ||
    msg.includes('men type 2')
  ) {
    return ['No', 'Yes, personal history', 'Yes, family history', 'Not sure'];
  }

  // ── GLP-1 — family history MEN2 / MTC ────────────────────────────────────
  if (
    msg.includes('family history of multiple endocrine') ||
    msg.includes('family history of medullary')
  ) {
    return ['No family history', 'Yes, family history', 'Not sure'];
  }

  // ── GLP-1 — liver disease ────────────────────────────────────────────────
  if (
    msg.includes('liver disease') ||
    msg.includes('cirrhosis') ||
    (msg.includes('liver') && msg.includes('history'))
  ) {
    return ['No liver issues', 'Yes, liver disease or cirrhosis', 'Not sure'];
  }

  // ── GLP-1 — gallbladder / GI / bariatric ────────────────────────────────
  if (
    msg.includes('gallbladder') ||
    msg.includes('bariatric') ||
    msg.includes('gi surgery') ||
    msg.includes('stomach problems')
  ) {
    return ['No GI or gallbladder issues', 'Yes, gallbladder disease', 'Yes, GI surgery or bariatric surgery', 'Yes, stomach problems', 'Not sure'];
  }

  // ── GLP-1 — kidney disease ───────────────────────────────────────────────
  if (
    msg.includes('kidney disease') ||
    msg.includes('kidney insufficiency') ||
    msg.includes('kidney transplant')
  ) {
    return ['No kidney issues', 'Yes, kidney disease', 'Yes, kidney transplant', 'Not sure'];
  }

  // ── GLP-1 — pregnant / breastfeeding ────────────────────────────────────
  if (msg.includes('pregnant') && !msg.includes('glp')) {
    return ['No', 'Yes, I am pregnant'];
  }

  if (msg.includes('breastfeeding') && msg.includes('glp')) {
    return ['No', 'Yes, I am breastfeeding'];
  }

  // ── GLP-1 — childbearing / planning children ────────────────────────
  if (
    msg.includes('childbearing age') ||
    msg.includes('planning to have children') ||
    msg.includes('planning children')
  ) {
    return ['No, not planning children', 'Yes, I may want children', 'I am post-menopausal'];
  }

  // ── GLP-1 — cancer / serious history ────────────────────────────────
  if (
    msg.includes('breast cancer') ||
    msg.includes('ovarian cancer') ||
    msg.includes('uterine cancer') ||
    msg.includes('cervical cancer') ||
    msg.includes('genetic predisposition to cancer') ||
    msg.includes('abnormal mammogram') ||
    msg.includes('pcos') ||
    msg.includes('endometriosis') ||
    msg.includes('blood clot') && msg.includes('dvt')
  ) {
    return ['None of these apply', 'Yes, cancer history', 'Yes, PCOS or endometriosis', 'Yes, blood clot or DVT', 'Yes, other condition listed', 'Not sure'];
  }

  // ── GLP-1 — hysterectomy ─────────────────────────────────────────────
  if (msg.includes('hysterectomy')) {
    return ['No, I have not had a hysterectomy', 'Yes, uterus only removed', 'Yes, uterus and ovaries removed'];
  }

  // ── GLP-1 — ovaries removed ─────────────────────────────────────────
  if (msg.includes('remove your ovaries') || msg.includes('ovaries removed') || msg.includes('did they also remove')) {
    return ['Yes, ovaries were also removed', 'No, ovaries were kept'];
  }

  // ── GLP-1 — regular menstrual cycle ─────────────────────────────────
  if (
    msg.includes('regular menstrual cycle') ||
    msg.includes('menstrual cycle') ||
    msg.includes('last period')
  ) {
    return ['Yes, regular cycle', 'Irregular cycle', 'No period for less than 1 year', 'No period for 1–3 years', 'No period for 3+ years (post-menopausal)'];
  }

  // ── GLP-1 — PMS history ──────────────────────────────────────────────
  if (msg.includes('pms') || msg.includes('premenstrual')) {
    return ['No significant PMS', 'Mild PMS symptoms', 'Moderate PMS symptoms', 'Severe PMS symptoms'];
  }

  // ── GLP-1 — symptom severity rating ─────────────────────────────────
  if (
    msg.includes('scale of 1 to 5') ||
    msg.includes('rate each') ||
    msg.includes('how would you rate') ||
    msg.includes('not an issue')
  ) {
    return ['1 – Not an issue', '2 – Mild', '3 – Moderate', '4 – Significant', '5 – Severe'];
  }

  // ── GLP-1 — hot flashes ──────────────────────────────────────────────
  if (msg.includes('hot flash') || msg.includes('night sweat')) {
    return ['1 – Not an issue', '2 – Mild', '3 – Moderate', '4 – Significant', '5 – Severe / daily'];
  }

  // ── GLP-1 — vaginal dryness ──────────────────────────────────────────
  if (msg.includes('vaginal dryness')) {
    return ['1 – Not an issue', '2 – Mild', '3 – Moderate', '4 – Significant', '5 – Severe'];
  }

  // ── GLP-1 — preferred medication form ────────────────────────────────
  if (
    msg.includes('preferred form') ||
    msg.includes('topical cream') ||
    msg.includes('oral') && msg.includes('sublingual') ||
    msg.includes('form for your medication')
  ) {
    return ['Topical cream', 'Oral / pill', 'Sublingual (under the tongue)', 'No preference'];
  }

  // ── Sleep / insomnia ──────────────────────────────────────────────────────
  if (
    msg.includes('sleep disruption') ||
    msg.includes('difficulty sleeping') ||
    msg.includes('insomnia') ||
    msg.includes('wake up') && msg.includes('night')
  ) {
    return ['1 – Not an issue', '2 – Mild', '3 – Moderate', '4 – Significant', '5 – Severe'];
  }

  // ── Mood swings ───────────────────────────────────────────────────────────
  if (msg.includes('mood swing') || (msg.includes('mood') && msg.includes('rate'))) {
    return ['1 – Not an issue', '2 – Mild', '3 – Moderate', '4 – Significant', '5 – Severe'];
  }

  // ── Fatigue (general) ─────────────────────────────────────────────────────
  if (msg.includes('fatigue') && msg.includes('rate')) {
    return ['1 – Not an issue', '2 – Mild', '3 – Moderate', '4 – Significant', '5 – Severe'];
  }

  // ── Generic Yes/No — MUST be last, only for clear yes/no questions ────────
  if (
    (msg.includes('have you ever') || msg.includes('have you had') || msg.includes('do you have') || msg.includes('are you currently')) &&
    !msg.includes('rate') &&
    !msg.includes('scale') &&
    !msg.includes('how much') &&
    !msg.includes('what is')
  ) {
    return ['Yes', 'No', 'Not sure'];
  }

  // ── Fallback to stage defaults ────────────────────────────────────────────
  return STAGE_PROMPTS[stage] ?? STAGE_PROMPTS.greeting;
}

const RECOMMENDATION_KEYWORDS = [
  'recommend', 'protocol for you', 'best fit', 'ideal protocol', 'him protocol', 'her protocol',
  'trt protocol', 'hormone balance protocol', 'weight loss protocol', 'peptide protocol',
  'longevity protocol', 'body composition protocol', 'metabolic protocol', 'cognitive protocol',
  'thyroid', 'based on what you', 'based on your', 'suggest the', 'prepared your personalized',
  'protocol summary below', 'physician-reviewed before delivery', 'fits your profile', 'your profile',
];

function detectRecommendation(messages: Message[]): boolean {
  const assistantMessages = messages.filter((m) => m.role === 'assistant');
  if (assistantMessages.length < 4) return false;
  const lastTwo = assistantMessages.slice(-2);
  const combinedText = lastTwo.map((m) => m.content.toLowerCase()).join(' ');
  const matchCount = RECOMMENDATION_KEYWORDS.filter((kw) => combinedText.includes(kw)).length;
  return matchCount >= 2;
}

function inferStage(messages: Message[]): IntakeStage {
  const count = messages.filter((m) => m.role === 'assistant').length;
  if (count <= 1) return 'greeting';
  if (count <= 3) return 'symptoms';
  if (count <= 5) return 'demographics';
  if (count <= 6) return 'history';
  if (detectRecommendation(messages)) return 'recommendation';
  return 'symptoms';
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function AiIntakeModal() {
  const [isOpen, setIsOpen] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const cardRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const hasRestoredSession = useRef(false);

  const [messages, setMessages] = useState<Message[]>([INITIAL_MESSAGE]);
  const [input, setInput] = useState('');
  const [streamingContent, setStreamingContent] = useState('');
  const [hasStarted, setHasStarted] = useState(false);
  const [recommendation, setRecommendation] = useState<ProtocolRecommendation | null>(null);
  const [isFetchingCard, setIsFetchingCard] = useState(false);
  const [cardVisible, setCardVisible] = useState(false);
  const [contactInfo, setContactInfo] = useState<ContactInfo>({ name: '', email: '' });
  const [contactSubmitted, setContactSubmitted] = useState(false);
  const [showContactForm, setShowContactForm] = useState(false);
  const [contactErrors, setContactErrors] = useState<Partial<ContactInfo>>({});
  const [currentStage, setCurrentStage] = useState<IntakeStage>('greeting');
  const [isTyping, setIsTyping] = useState(false);
  const [showOrderSummary, setShowOrderSummary] = useState(false);
  const [orderSummaryData, setOrderSummaryData] = useState<OrderSummaryData | null>(null);
  const [vectorRecommendations, setVectorRecommendations] = useState<VectorRecommendation[]>([]);
  const [sessionId] = useState<string>(() => `session_${Date.now()}_${Math.random().toString(36).slice(2)}`);

  const { response, isLoading, error, sendMessage } = useChat('ANTHROPIC', 'claude-sonnet-4-5-20250929', true);
  const { response: summaryResponse, isLoading: summaryLoading, error: summaryError, sendMessage: sendSummary } = useChat('ANTHROPIC', 'claude-sonnet-4-5-20250929', false);

  // ── Keep a ref that always reflects the latest messages ─────────────────────
  const messagesRef = useRef<Message[]>([INITIAL_MESSAGE]);
  useEffect(() => { messagesRef.current = messages; }, [messages]);

  // ── Listen for open event ────────────────────────────────────────────────────
  useEffect(() => {
    const handleOpen = () => {
      setIsOpen(true);
      setTimeout(() => inputRef.current?.focus(), 300);
    };
    window.addEventListener('openIntakeModal', handleOpen);
    return () => window.removeEventListener('openIntakeModal', handleOpen);
  }, []);

  // ── Lock body scroll when open ───────────────────────────────────────────────
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => { document.body.style.overflow = ''; };
  }, [isOpen]);

  // ── Session persistence ──────────────────────────────────────────────────────
  useEffect(() => {
    if (hasRestoredSession.current) return;
    hasRestoredSession.current = true;
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed.messages?.length > 1) {
          setMessages(parsed.messages);
          setHasStarted(true);
          if (parsed.recommendation) { setRecommendation(parsed.recommendation); setCardVisible(true); }
          if (parsed.contactSubmitted) setContactSubmitted(true);
        }
      }
    } catch { /* ignore */ }
  }, []);

  useEffect(() => {
    if (!hasStarted) return;
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ messages, recommendation, contactSubmitted })); }
    catch { /* ignore */ }
  }, [messages, recommendation, contactSubmitted, hasStarted]);

  // ── Error handling ───────────────────────────────────────────────────────────
  useEffect(() => {
    if (error) console.warn('Chat error:', error);
  }, [error]);

  useEffect(() => {
    if (summaryError) console.warn('Summary fetch failed silently');
  }, [summaryError]);

  // ── Streaming response handler ───────────────────────────────────────────────
  useEffect(() => {
    if (isLoading && response) { setStreamingContent(response); setIsTyping(false); }
    if (!isLoading && response && streamingContent) {
      const newMsg: Message = { role: 'assistant', content: response, timestamp: Date.now() };
      // Use the ref so we always append to the latest messages, not a stale snapshot
      const newMessages = [...messagesRef.current, newMsg];
      setMessages(newMessages);
      setStreamingContent('');
      setIsTyping(false);
      const stage = inferStage(newMessages);
      setCurrentStage(stage);
      if (!recommendation && !isFetchingCard && detectRecommendation(newMessages)) {
        setIsFetchingCard(true);
        setShowContactForm(true);
      }
    }
  }, [response, isLoading]);

  useEffect(() => { messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [messages, streamingContent, isTyping]);

  // ── Parse summary response and show order summary screen ────────────────────
  useEffect(() => {
    if (!summaryLoading && summaryResponse && contactSubmitted && !recommendation) {
      try {
        const cleaned = summaryResponse.replace(/```json\n?/g, '').replace(/```\n?/g, '').trim();
        const parsed: ProtocolRecommendation = JSON.parse(cleaned);
        setRecommendation(parsed);

        // Extract user symptom answers from conversation
        const userMessages = messages.filter((m) => m.role === 'user').map((m) => m.content);
        const symptomKeywords = [
          'libido', 'sex drive', 'energy', 'fatigue', 'strength', 'endurance',
          'mood', 'sleep', 'focus', 'weight', 'erection', 'hot flash', 'night sweat',
          'memory', 'anxiety', 'irritable', 'grumpy', 'sad', 'recovery', 'performance',
        ];
        const detectedSymptoms: string[] = [];
        userMessages.forEach((msg) => {
          const lower = msg.toLowerCase();
          symptomKeywords.forEach((kw) => {
            if (lower.includes(kw) && !detectedSymptoms.some((s) => s.toLowerCase().includes(kw))) {
              const formatted = kw.charAt(0).toUpperCase() + kw.slice(1);
              detectedSymptoms.push(formatted);
            }
          });
        });

        // Also pull from quick-reply answers that indicate symptoms
        const symptomPhrases: Record<string, string> = {
          'yes, my sex drive has decreased': 'Decreased sex drive',
          'yes, i feel low energy daily': 'Low energy',
          'sometimes, especially afternoons': 'Afternoon energy crashes',
          'energy is my biggest issue': 'Low energy',
          'yes, noticeably weaker': 'Decreased strength',
          'yes, things feel less enjoyable': 'Decreased enjoyment of life',
          'yes, more irritable lately': 'Mood changes / irritability',
          'yes, my performance has declined': 'Reduced exercise performance',
          'yes, often': 'Falling asleep after dinner',
          'yes, noticeably declined': 'Declined work performance / focus',
          'focus is my main issue': 'Poor focus',
          'this is a concern for me': 'Sexual health concerns',
          'this is my main concern': 'Libido / sex drive',
          'low energy & fatigue': 'Low energy & fatigue',
          'hormone balance': 'Hormone imbalance',
          'burn fat & lose weight': 'Weight management',
          'build muscle & strength': 'Muscle & strength goals',
          'better sleep & mood': 'Sleep & mood issues',
          'sexual health': 'Sexual health',
          'anti-aging & longevity': 'Anti-aging & longevity',
        };
        userMessages.forEach((msg) => {
          const lower = msg.toLowerCase().trim();
          Object.entries(symptomPhrases).forEach(([key, label]) => {
            if (lower.includes(key) && !detectedSymptoms.includes(label)) {
              detectedSymptoms.push(label);
            }
          });
        });

        const finalSymptoms = detectedSymptoms.slice(0, 6);
        if (finalSymptoms.length === 0) {
          finalSymptoms.push('Personalized health optimization');
        }

        const isHimProtocol = parsed.protocolName?.toLowerCase().startsWith('him');

        // Enhance protocol match with vector similarity if available
        // Use the top vector match to confirm or refine the AI-generated recommendation
        let enhancedProtocolName = parsed.protocolName;
        let enhancedTagline = parsed.tagline;
        if (vectorRecommendations.length > 0) {
          const topMatch = vectorRecommendations[0];
          // Only override if vector similarity is high (>0.80) and AI match is blueprint/undecided
          const isAiBlueprint = parsed.ctaHref?.includes('blueprint');
          const isVectorHighConfidence = topMatch.similarity >= 0.80;
          if (isAiBlueprint && isVectorHighConfidence && topMatch.protocolKey !== 'blueprint') {
            enhancedProtocolName = topMatch.protocolName;
            enhancedTagline = `Matched by symptom analysis (${Math.round(topMatch.similarity * 100)}% similarity)`;
          }
        }

        setOrderSummaryData({
          symptoms: finalSymptoms,
          protocolMatch: enhancedProtocolName,
          protocolTagline: enhancedTagline,
          checkoutHref: parsed.ctaHref,
          isHim: isHimProtocol,
        });
        setShowOrderSummary(true);
      } catch {
        setCardVisible(true);
      }
    }
  }, [summaryResponse, summaryLoading, contactSubmitted, vectorRecommendations]);

  // ── Helpers ──────────────────────────────────────────────────────────────────
  const buildApiMessages = useCallback((newUserMessage: string) => {
    // Read from ref so we always use the latest messages, not a stale closure
    const history = messagesRef.current.map((m) => ({ role: m.role, content: m.content }));
    return [{ role: 'system' as const, content: SYSTEM_PROMPT }, ...history, { role: 'user' as const, content: newUserMessage }];
  }, []);

  const handleSend = useCallback((text?: string) => {
    const userText = (text ?? input).trim();
    if (!userText || isLoading) return;
    if (!hasStarted) setHasStarted(true);
    const userMsg: Message = { role: 'user', content: userText, timestamp: Date.now() };
    setMessages((prev) => {
      const updated = [...prev, userMsg];
      messagesRef.current = updated;
      return updated;
    });
    setInput('');
    sendMessage(buildApiMessages(userText), { temperature: 0.72, max_tokens: 700 });
    inputRef.current?.focus();
  }, [input, isLoading, hasStarted, buildApiMessages, sendMessage]);

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); }
  };

  const handleReset = () => {
    setMessages([{ ...INITIAL_MESSAGE, timestamp: Date.now() }]);
    setInput(''); setStreamingContent(''); setHasStarted(false);
    setRecommendation(null); setCardVisible(false); setIsFetchingCard(false);
    setContactInfo({ name: '', email: '' }); setContactSubmitted(false);
    setShowContactForm(false); setContactErrors({}); setCurrentStage('greeting'); setIsTyping(false);
    setShowOrderSummary(false); setOrderSummaryData(null); setVectorRecommendations([]);
    try { localStorage.removeItem(STORAGE_KEY); } catch { /* ignore */ }
  };

  const handleClose = () => { setIsOpen(false); };

  const validateContact = (): boolean => {
    const errors: Partial<ContactInfo> = {};
    if (!contactInfo.name.trim()) errors.name = 'Name is required';
    if (!contactInfo.email.trim()) { errors.email = 'Email is required'; }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactInfo.email)) { errors.email = 'Please enter a valid email'; }
    setContactErrors(errors);
    return Object.keys(errors).length === 0;
  };

  // ── Fetch vector-based protocol recommendations from symptom embeddings ──────
  const fetchVectorRecommendations = useCallback(async (symptoms: string[]) => {
    if (!symptoms.length) return;
    try {
      const res = await fetch('/api/ai/symptom-embeddings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ symptoms, sessionId }),
      });
      if (!res.ok) return;
      const data = await res.json();
      if (data.recommendations?.length) {
        setVectorRecommendations(data.recommendations);
      }
    } catch {
      // Non-blocking — vector recommendations are an enhancement, not required
    }
  }, [sessionId]);

  const handleContactSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateContact()) return;
    setContactSubmitted(true);
    setShowContactForm(false);
    const conversationText = messages.map((m) => `${m.role === 'user' ? 'User' : 'AI Concierge'}: ${m.content}`).join('\n\n');

    // Extract symptoms from conversation for vector embedding
    const userMessages = messages.filter((m) => m.role === 'user').map((m) => m.content);
    const symptomKeywords = [
      'libido', 'sex drive', 'energy', 'fatigue', 'strength', 'endurance',
      'mood', 'sleep', 'focus', 'weight', 'erection', 'hot flash', 'night sweat',
      'memory', 'anxiety', 'irritable', 'grumpy', 'sad', 'recovery', 'performance',
    ];
    const detectedSymptoms: string[] = [];
    userMessages.forEach((msg) => {
      const lower = msg.toLowerCase();
      symptomKeywords.forEach((kw) => {
        if (lower.includes(kw) && !detectedSymptoms.some((s) => s.toLowerCase().includes(kw))) {
          detectedSymptoms.push(kw);
        }
      });
    });

    // Fire vector embedding search in parallel (non-blocking)
    if (detectedSymptoms.length > 0) {
      fetchVectorRecommendations(detectedSymptoms);
    }

    sendSummary(
      [{ role: 'system', content: SUMMARY_SYSTEM_PROMPT }, { role: 'user', content: `Conversation:\n\n${conversationText}\n\nGenerate the JSON recommendation card.` }],
      { temperature: 0.15, max_tokens: 350 }
    );
  };

  const isHim = recommendation?.protocolName?.toLowerCase().startsWith('him');
  const lastAiMessage = [...messages].reverse().find((m) => m.role === 'assistant')?.content ?? '';
  const activePrompts = getContextualPrompts(lastAiMessage, currentStage);

  const stageOrder: IntakeStage[] = ['greeting', 'symptoms', 'demographics', 'history', 'recommendation'];
  const stageIndex = stageOrder.indexOf(currentStage);
  const stageLabels: Record<IntakeStage, string> = {
    greeting: 'Getting Started', symptoms: 'Exploring Symptoms', demographics: 'Your Profile',
    history: 'Health History', recommendation: 'Protocol Match', complete: 'Complete',
  };

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center"
      style={{ background: 'rgba(0,0,0,0.85)', backdropFilter: 'blur(8px)' }}
      onClick={(e) => { if (e.target === e.currentTarget) handleClose(); }}
    >
      <div
        className="relative w-full sm:max-w-2xl flex flex-col"
        style={{
          background: '#0D0D0D',
          border: '1px solid rgba(201,168,76,0.2)',
          borderRadius: '24px 24px 0 0',
          maxHeight: '92vh',
          height: '92vh',
        }}
      >
        {/* ── Header ─────────────────────────────────────────────────────── */}
        <div
          className="flex items-center justify-between px-5 py-4 flex-shrink-0"
          style={{ borderBottom: '1px solid rgba(255,255,255,0.06)' }}
        >
          <div className="flex items-center gap-3">
            {/* Pulsing indicator */}
            <div className="relative flex-shrink-0">
              <div className="w-2.5 h-2.5 rounded-full" style={{ background: '#C9A84C' }} />
              <div
                className="absolute inset-0 rounded-full animate-ping"
                style={{ background: 'rgba(201,168,76,0.4)' }}
              />
            </div>
            <div>
              <p style={{ color: '#FFFFFF', fontFamily: 'DM Sans, system-ui, sans-serif', fontWeight: 600, fontSize: '14px', lineHeight: 1.2 }}>
                ProtocolHRT AI Concierge
              </p>
              <p style={{ color: 'rgba(255,255,255,0.35)', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.08em' }}>
                Powered by Claude · Physician-Reviewed
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            {hasStarted && (
              <button
                onClick={handleReset}
                aria-label="Start over and reset the intake conversation"
                style={{ color: 'rgba(255,255,255,0.3)', fontSize: '11px', fontFamily: 'DM Sans, system-ui, sans-serif', background: 'none', border: '1px solid rgba(255,255,255,0.1)', borderRadius: '6px', cursor: 'pointer', padding: '4px 10px' }}
                onMouseEnter={(e) => { e.currentTarget.style.color = 'rgba(255,255,255,0.6)'; e.currentTarget.style.borderColor = 'rgba(255,255,255,0.2)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.color = 'rgba(255,255,255,0.3)'; e.currentTarget.style.borderColor = 'rgba(255,255,255,0.1)'; }}
              >
                Start Over
              </button>
            )}
            <button
              onClick={handleClose}
              aria-label="Close AI intake modal"
              className="flex items-center justify-center w-8 h-8 rounded-full"
              style={{ background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.1)', cursor: 'pointer' }}
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" strokeWidth="2.5">
                <path d="M18 6L6 18M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        {/* ── Order Summary Screen ─────────────────────────────────────────── */}
        {showOrderSummary && orderSummaryData ? (
          <div className="flex-1 overflow-y-auto px-5 py-6" style={{ scrollbarWidth: 'thin', scrollbarColor: 'rgba(201,168,76,0.2) transparent' }}>
            {/* Header badge */}
            <div className="flex items-center gap-2 mb-5">
              <div className="w-8 h-8 rounded-full flex items-center justify-center" style={{ background: 'rgba(201,168,76,0.12)', border: '1px solid rgba(201,168,76,0.3)' }}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="2">
                  <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                </svg>
              </div>
              <div>
                <p style={{ color: '#C9A84C', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.1em', textTransform: 'uppercase', fontWeight: 700 }}>
                  Protocol Ready
                </p>
                <p style={{ color: 'rgba(255,255,255,0.4)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '12px' }}>
                  Review your summary before checkout
                </p>
              </div>
            </div>

            {/* Symptoms collected */}
            <div style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.08)', borderRadius: '16px', padding: '18px', marginBottom: '14px' }}>
              <p style={{ color: 'rgba(255,255,255,0.4)', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '12px' }}>
                Symptoms & Goals Collected
              </p>
              <div className="flex flex-wrap gap-2">
                {orderSummaryData.symptoms.map((symptom, i) => (
                  <span
                    key={i}
                    style={{
                      background: 'rgba(201,168,76,0.08)',
                      border: '1px solid rgba(201,168,76,0.2)',
                      borderRadius: '20px',
                      padding: '5px 12px',
                      color: 'rgba(255,255,255,0.75)',
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      fontSize: '12px',
                    }}
                  >
                    {symptom}
                  </span>
                ))}
              </div>
            </div>

            {/* Protocol match */}
            <div
              style={{
                background: orderSummaryData.isHim
                  ? 'linear-gradient(135deg, rgba(201,168,76,0.1) 0%, rgba(201,168,76,0.04) 100%)'
                  : 'linear-gradient(135deg, rgba(119,157,124,0.1) 0%, rgba(119,157,124,0.04) 100%)',
                border: `1px solid ${orderSummaryData.isHim ? 'rgba(201,168,76,0.3)' : 'rgba(119,157,124,0.3)'}`,
                borderRadius: '16px',
                padding: '18px',
                marginBottom: '14px',
              }}
            >
              <p style={{ color: orderSummaryData.isHim ? '#C9A84C' : '#779D7C', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '8px' }}>
                Protocol Match
              </p>
              <h3 style={{ color: '#FFFFFF', fontFamily: 'Cormorant Garamond, serif', fontSize: '22px', fontWeight: 700, marginBottom: '4px' }}>
                {orderSummaryData.protocolMatch}
              </h3>
              <p style={{ color: 'rgba(255,255,255,0.5)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', lineHeight: '1.5' }}>
                {orderSummaryData.protocolTagline}
              </p>
            </div>

            {/* Price confirmation */}
            <div style={{ background: 'rgba(201,168,76,0.06)', border: '1px solid rgba(201,168,76,0.25)', borderRadius: '16px', padding: '18px', marginBottom: '20px' }}>
              <p style={{ color: 'rgba(255,255,255,0.4)', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '12px' }}>
                Order Summary
              </p>
              <div className="flex items-center justify-between mb-3">
                <span style={{ color: 'rgba(255,255,255,0.7)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px' }}>
                  Personalized Protocol Assessment
                </span>
                <span style={{ color: '#C9A84C', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '16px', fontWeight: 700 }}>
                  $49
                </span>
              </div>
              <div style={{ borderTop: '1px solid rgba(255,255,255,0.06)', paddingTop: '12px' }}>
                <div className="space-y-2">
                  {[
                    'Full AI-built personalized protocol',
                    'Licensed physician review',
                    '$49 credited toward your first order',
                  ].map((item, i) => (
                    <div key={i} className="flex items-start gap-2">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="2.5" style={{ marginTop: '2px', flexShrink: 0 }}>
                        <path d="M20 6L9 17l-5-5" />
                      </svg>
                      <span style={{ color: 'rgba(255,255,255,0.6)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px' }}>{item}</span>
                    </div>
                  ))}
                </div>
              </div>
              <div style={{ borderTop: '1px solid rgba(255,255,255,0.06)', marginTop: '12px', paddingTop: '12px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ color: 'rgba(255,255,255,0.5)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px' }}>Total today</span>
                <div className="flex items-baseline gap-2">
                  <span style={{ color: 'rgba(255,255,255,0.3)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', textDecoration: 'line-through' }}>$149</span>
                  <span style={{ color: '#FFFFFF', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '22px', fontWeight: 700 }}>$49</span>
                </div>
              </div>
            </div>

            {/* CTA */}
            <a
              href={orderSummaryData.checkoutHref}
              style={{
                display: 'block',
                textAlign: 'center',
                background: '#C9A84C',
                color: '#0D0D0D',
                fontFamily: 'DM Sans, system-ui, sans-serif',
                fontWeight: 700,
                fontSize: '15px',
                letterSpacing: '0.06em',
                textTransform: 'uppercase',
                padding: '16px',
                borderRadius: '14px',
                textDecoration: 'none',
                marginBottom: '10px',
              }}
            >
              Proceed to Checkout — $49 →
            </a>
            <p style={{ color: 'rgba(255,255,255,0.2)', fontSize: '11px', fontFamily: 'DM Sans, system-ui, sans-serif', textAlign: 'center' }}>
              Secure checkout · $49 fully credited toward your first order
            </p>
          </div>
        ) : (
          <>
            {/* ── Progress bar ────────────────────────────────────────────────── */}
            {hasStarted && (
              <div className="px-5 pt-3 pb-0 flex-shrink-0">
                <div className="flex gap-1">
                  {stageOrder.map((stage, i) => (
                    <div
                      key={stage}
                      style={{
                        flex: 1, height: '3px', borderRadius: '2px',
                        background: i <= stageIndex ? '#C9A84C' : 'rgba(201,168,76,0.15)',
                        transition: 'background 0.4s ease',
                      }}
                    />
                  ))}
                </div>
                <p style={{ color: 'rgba(255,255,255,0.3)', fontSize: '10px', fontFamily: 'JetBrains Mono, monospace', marginTop: '5px' }}>
                  {stageLabels[currentStage]}{currentStage === 'recommendation' ? ' — Protocol identified ✓' : ''}
                </p>
              </div>
            )}

            {/* ── Messages ────────────────────────────────────────────────────── */}
            <div className="flex-1 overflow-y-auto px-5 py-4 space-y-4" style={{ scrollbarWidth: 'thin', scrollbarColor: 'rgba(201,168,76,0.2) transparent' }}>
              {messages.map((msg, i) => (
                <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                  {msg.role === 'assistant' && (
                    <div className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mr-2 mt-0.5" style={{ background: 'rgba(201,168,76,0.12)', border: '1px solid rgba(201,168,76,0.25)' }}>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="#C9A84C" strokeWidth="1.5" />
                        <path d="M8 12l2.5 2.5L16 9" stroke="#C9A84C" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                      </svg>
                    </div>
                  )}
                  <div
                    style={{
                      maxWidth: '78%',
                      padding: msg.role === 'user' ? '10px 14px' : '12px 16px',
                      borderRadius: msg.role === 'user' ? '18px 18px 4px 18px' : '4px 18px 18px 18px',
                      background: msg.role === 'user' ? '#C9A84C' : 'rgba(255,255,255,0.05)',
                      border: msg.role === 'user' ? 'none' : '1px solid rgba(255,255,255,0.08)',
                      color: msg.role === 'user' ? '#0D0D0D' : 'rgba(255,255,255,0.88)',
                      fontFamily: 'DM Sans, system-ui, sans-serif',
                      fontSize: '14px',
                      lineHeight: '1.6',
                      fontWeight: msg.role === 'user' ? 500 : 400,
                    }}
                  >
                    {msg.role === 'assistant' ? (
                      <ReactMarkdown
                        components={{
                          p: ({ children }) => <p style={{ margin: '0 0 6px', color: 'rgba(255,255,255,0.88)', fontSize: '14px', lineHeight: '1.6', fontFamily: 'DM Sans, system-ui, sans-serif' }}>{children}</p>,
                          strong: ({ children }) => <strong style={{ color: '#C9A84C', fontWeight: 600 }}>{children}</strong>,
                          ul: ({ children }) => <ul style={{ paddingLeft: '16px', margin: '4px 0' }}>{children}</ul>,
                          li: ({ children }) => <li style={{ color: 'rgba(255,255,255,0.75)', fontSize: '13px', marginBottom: '3px' }}>{children}</li>,
                        }}
                      >
                        {msg.content}
                      </ReactMarkdown>
                    ) : (
                      msg.content
                    )}
                  </div>
                </div>
              ))}

              {/* Streaming */}
              {streamingContent && (
                <div className="flex justify-start">
                  <div className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mr-2 mt-0.5" style={{ background: 'rgba(201,168,76,0.12)', border: '1px solid rgba(201,168,76,0.25)' }}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                      <circle cx="12" cy="12" r="10" stroke="#C9A84C" strokeWidth="1.5" />
                      <path d="M8 12l2.5 2.5L16 9" stroke="#C9A84C" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                  </div>
                  <div style={{ maxWidth: '78%', padding: '12px 16px', borderRadius: '4px 18px 18px 18px', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.08)', color: 'rgba(255,255,255,0.88)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', lineHeight: '1.6' }}>
                    <ReactMarkdown components={{ p: ({ children }) => <p style={{ margin: '0 0 6px', color: 'rgba(255,255,255,0.88)', fontSize: '14px', lineHeight: '1.6', fontFamily: 'DM Sans, system-ui, sans-serif' }}>{children}</p>, strong: ({ children }) => <strong style={{ color: '#C9A84C', fontWeight: 600 }}>{children}</strong> }}>
                      {streamingContent}
                    </ReactMarkdown>
                    <span style={{ display: 'inline-block', width: '6px', height: '14px', background: '#C9A84C', borderRadius: '1px', marginLeft: '2px', animation: 'blink 1s step-end infinite', verticalAlign: 'text-bottom' }} />
                  </div>
                </div>
              )}

              {/* Typing indicator */}
              {isTyping && !streamingContent && (
                <div className="flex justify-start">
                  <div className="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mr-2" style={{ background: 'rgba(201,168,76,0.12)', border: '1px solid rgba(201,168,76,0.25)' }}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#C9A84C" strokeWidth="1.5" /></svg>
                  </div>
                  <div style={{ padding: '12px 16px', borderRadius: '4px 18px 18px 18px', background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.08)', display: 'flex', gap: '4px', alignItems: 'center' }}>
                    {[0, 1, 2].map((i) => (
                      <div key={i} style={{ width: '6px', height: '6px', borderRadius: '50%', background: 'rgba(201,168,76,0.6)', animation: `bounce 1.2s ease-in-out ${i * 0.2}s infinite` }} />
                    ))}
                  </div>
                </div>
              )}

              {/* Contact form */}
              {showContactForm && !contactSubmitted && (
                <div style={{ background: 'rgba(201,168,76,0.06)', border: '1px solid rgba(201,168,76,0.2)', borderRadius: '20px', padding: '24px' }}>
                  <p style={{ color: '#C9A84C', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.1em', textTransform: 'uppercase', marginBottom: '8px' }}>
                    Unlock Your Protocol Summary
                  </p>
                  <p style={{ color: 'rgba(255,255,255,0.55)', fontSize: '13px', fontFamily: 'DM Sans, system-ui, sans-serif', marginBottom: '14px', lineHeight: '1.5' }}>
                    Enter your details to receive your personalized protocol card.
                  </p>
                  <form onSubmit={handleContactSubmit} className="space-y-3">
                    <div>
                      <input
                        type="text"
                        placeholder="Your name"
                        value={contactInfo.name}
                        onChange={(e) => setContactInfo((p) => ({ ...p, name: e.target.value }))}
                        style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: `1px solid ${contactErrors.name ? '#ef4444' : 'rgba(255,255,255,0.12)'}`, borderRadius: '10px', padding: '10px 14px', color: '#FFFFFF', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', outline: 'none' }}
                      />
                      {contactErrors.name && <p style={{ color: '#ef4444', fontSize: '11px', marginTop: '4px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>{contactErrors.name}</p>}
                    </div>
                    <div>
                      <input
                        type="email"
                        placeholder="Your email"
                        value={contactInfo.email}
                        onChange={(e) => setContactInfo((p) => ({ ...p, email: e.target.value }))}
                        style={{ width: '100%', background: 'rgba(255,255,255,0.05)', border: `1px solid ${contactErrors.email ? '#ef4444' : 'rgba(255,255,255,0.12)'}`, borderRadius: '10px', padding: '10px 14px', color: '#FFFFFF', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', outline: 'none' }}
                      />
                      {contactErrors.email && <p style={{ color: '#ef4444', fontSize: '11px', marginTop: '4px', fontFamily: 'DM Sans, system-ui, sans-serif' }}>{contactErrors.email}</p>}
                    </div>
                    <button type="submit" style={{ width: '100%', background: '#C9A84C', color: '#0D0D0D', fontFamily: 'DM Sans, system-ui, sans-serif', fontWeight: 700, fontSize: '14px', letterSpacing: '0.06em', textTransform: 'uppercase', padding: '14px', borderRadius: '12px', border: 'none', cursor: 'pointer' }}>
                      Get My Protocol Summary →
                    </button>
                  </form>
                </div>
              )}

              {/* Loading state while generating summary */}
              {contactSubmitted && summaryLoading && !showOrderSummary && (
                <div style={{ background: 'rgba(201,168,76,0.06)', border: '1px solid rgba(201,168,76,0.2)', borderRadius: '20px', padding: '24px', textAlign: 'center' }}>
                  <div style={{ width: '32px', height: '32px', border: '2px solid rgba(201,168,76,0.2)', borderTopColor: '#C9A84C', borderRadius: '50%', animation: 'spin 0.8s linear infinite', margin: '0 auto 12px' }} />
                  <p style={{ color: 'rgba(255,255,255,0.5)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px' }}>
                    Building your personalized protocol summary…
                  </p>
                </div>
              )}

              {/* Recommendation card (shown after order summary is dismissed or on fallback) */}
              {cardVisible && recommendation && (
                <div
                  ref={cardRef}
                  style={{
                    background: isHim ? 'linear-gradient(135deg, rgba(201,168,76,0.08) 0%, rgba(201,168,76,0.03) 100%)' : 'linear-gradient(135deg, rgba(119,157,124,0.1) 0%, rgba(119,157,124,0.03) 100%)',
                    border: `1px solid ${isHim ? 'rgba(201,168,76,0.3)' : 'rgba(119,157,124,0.3)'}`,
                    borderRadius: '20px',
                    padding: '24px',
                    animation: 'fadeInUp 0.5s ease forwards',
                  }}
                >
                  <div className="flex items-center gap-2 mb-3">
                    <div className="w-2 h-2 rounded-full" style={{ background: isHim ? '#C9A84C' : '#779D7C' }} />
                    <span style={{ color: isHim ? '#C9A84C' : '#779D7C', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.1em', textTransform: 'uppercase' }}>
                      Your Protocol Match
                    </span>
                  </div>
                  <h3 style={{ color: '#FFFFFF', fontFamily: 'Cormorant Garamond, serif', fontSize: '22px', fontWeight: 700, marginBottom: '6px' }}>
                    {recommendation.protocolName}
                  </h3>
                  <p style={{ color: 'rgba(255,255,255,0.5)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px', marginBottom: '16px', lineHeight: '1.5' }}>
                    {recommendation.tagline}
                  </p>
                  <div className="space-y-2 mb-5">
                    {recommendation.benefits.map((b, i) => (
                      <div key={i} className="flex items-center gap-2">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke={isHim ? '#C9A84C' : '#779D7C'} strokeWidth="2.5">
                          <path d="M20 6L9 17l-5-5" />
                        </svg>
                        <span style={{ color: 'rgba(255,255,255,0.7)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px' }}>{b}</span>
                      </div>
                    ))}
                  </div>

                  {/* $149 Special Offer Banner */}
                  <div style={{ background: 'rgba(201,168,76,0.1)', border: '1px solid rgba(201,168,76,0.35)', borderRadius: '14px', padding: '16px 18px', marginBottom: '16px' }}>
                    <div className="flex items-center gap-2 mb-2">
                      <span style={{ fontSize: '16px' }}>🎉</span>
                      <span style={{ color: '#C9A84C', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px', letterSpacing: '0.1em', textTransform: 'uppercase', fontWeight: 700 }}>
                        Limited-Time Special
                      </span>
                    </div>
                    <p style={{ color: '#FFFFFF', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', fontWeight: 600, marginBottom: '8px', lineHeight: '1.5' }}>
                      Everything you need to get started — for just <span style={{ color: '#C9A84C', fontSize: '18px' }}>$149</span>
                    </p>
                    <div className="space-y-1.5">
                      {[
                        'Meet with our physician',
                        'Comprehensive blood test',
                        'Fully customized protocol',
                      ].map((item, i) => (
                        <div key={i} className="flex items-center gap-2">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#C9A84C" strokeWidth="2.5">
                            <path d="M20 6L9 17l-5-5" />
                          </svg>
                          <span style={{ color: 'rgba(255,255,255,0.75)', fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '13px' }}>{item}</span>
                        </div>
                      ))}
                    </div>
                  </div>

                  <a
                    href={recommendation.ctaHref}
                    style={{
                      display: 'block', textAlign: 'center', background: isHim ? '#C9A84C' : '#779D7C',
                      color: '#0D0D0D', fontFamily: 'DM Sans, system-ui, sans-serif', fontWeight: 700,
                      fontSize: '14px', letterSpacing: '0.06em', textTransform: 'uppercase',
                      padding: '14px', borderRadius: '12px', textDecoration: 'none',
                    }}
                  >
                    Yes, I'm Ready to Start →
                  </a>
                  <p style={{ color: 'rgba(255,255,255,0.25)', fontSize: '11px', fontFamily: 'DM Sans, system-ui, sans-serif', textAlign: 'center', marginTop: '10px' }}>
                    Physician-reviewed before delivery · No commitment required
                  </p>
                </div>
              )}

              <div ref={messagesEndRef} />
            </div>

            {/* ── Quick prompts ────────────────────────────────────────────────── */}
            {!recommendation && (
              <div className="px-5 pb-2 flex-shrink-0">
                <div className="flex gap-2 items-center overflow-x-auto pb-1" style={{ scrollbarWidth: 'none' }}>
                  {activePrompts.map((prompt) => (
                    <button
                      key={prompt}
                      onClick={() => handleSend(prompt)}
                      disabled={isLoading}
                      style={{
                        flexShrink: 0, padding: '6px 12px', borderRadius: '20px',
                        background: 'rgba(201,168,76,0.06)', border: '1px solid rgba(201,168,76,0.2)',
                        color: 'rgba(255,255,255,0.6)', fontFamily: 'DM Sans, system-ui, sans-serif',
                        fontSize: '12px', cursor: isLoading ? 'not-allowed' : 'pointer',
                        opacity: isLoading ? 0.5 : 1, whiteSpace: 'nowrap',
                      }}
                    >
                      {prompt}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* ── Input ───────────────────────────────────────────────────────── */}
            <div
              className="px-5 pb-5 pt-3 flex-shrink-0"
              style={{ borderTop: '1px solid rgba(255,255,255,0.06)' }}
            >
              <div className="flex gap-2 items-center">
                <input
                  ref={inputRef}
                  type="text"
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  placeholder="Tell me about your health goals..."
                  disabled={isLoading}
                  style={{
                    flex: 1, background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)',
                    borderRadius: '12px', padding: '12px 16px', color: '#FFFFFF',
                    fontFamily: 'DM Sans, system-ui, sans-serif', fontSize: '14px', outline: 'none',
                  }}
                />
                <button
                  onClick={() => handleSend()}
                  disabled={isLoading || !input.trim()}
                  style={{
                    width: '44px', height: '44px', borderRadius: '12px', flexShrink: 0,
                    background: input.trim() && !isLoading ? '#C9A84C' : 'rgba(201,168,76,0.15)',
                    border: 'none', cursor: input.trim() && !isLoading ? 'pointer' : 'not-allowed',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    transition: 'background 0.2s',
                  }}
                >
                  {isLoading ? (
                    <div style={{ width: '16px', height: '16px', border: '2px solid rgba(201,168,76,0.3)', borderTopColor: '#C9A84C', borderRadius: '50%', animation: 'spin 0.8s linear infinite' }} />
                  ) : (
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke={input.trim() ? '#0D0D0D' : 'rgba(201,168,76,0.5)'} strokeWidth="2.5">
                      <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" />
                    </svg>
                  )}
                </button>
              </div>
            </div>
          </>
        )}

      </div>

      <style jsx>{`
        @keyframes bounce {
          0%, 60%, 100% { transform: translateY(0); }
          30% { transform: translateY(-6px); }
        }
        @keyframes blink {
          0%, 100% { opacity: 1; }
          50% { opacity: 0; }
        }
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        @keyframes fadeInUp {
          from { opacity: 0; transform: translateY(12px); }
          to { opacity: 1; transform: translateY(0); }
        }
      `}</style>
    </div>
  );
}
