// ─── Clinical Intake Form Definitions ────────────────────────────────────────
// All questions directly from treating physician documents.
// Shared questions are defined once and referenced by key to prevent duplication.

export type ServiceType = 'TRT' | 'GLP1' | 'FEMALE_HRT';
export type FormType = 'SCREENING' | 'REASSESSMENT';

export type QuestionType =
  | 'yes_no' |'yes_no_explain' |'scale_1_5' |'text' |'number' |'select' |'multiselect' |'date' |'section_header';

export interface FormQuestion {
  key: string;
  type: QuestionType;
  label: string;
  description?: string;
  options?: string[];
  required?: boolean;
  stopIfYes?: boolean;   // Red flag — requires immediate action
  consultIfYes?: boolean; // Yellow flag — requires prescriber consult
  placeholder?: string;
}

export interface FormSection {
  id: string;
  title: string;
  description?: string;
  questions: FormQuestion[];
}

export interface IntakeFormDefinition {
  serviceType: ServiceType;
  formType: FormType;
  title: string;
  subtitle: string;
  sections: FormSection[];
}

// ─── Shared Questions (asked once for multi-condition patients) ───────────────

export const SHARED_QUESTIONS: Record<string, FormQuestion> = {
  // Demographics / biometrics
  dob: {
    key: 'dob',
    type: 'date',
    label: 'Date of Birth',
    required: true,
  },
  allergies: {
    key: 'allergies',
    type: 'text',
    label: 'Known Allergies',
    placeholder: 'List any known drug or medication allergies',
    required: true,
  },
  current_medications: {
    key: 'current_medications',
    type: 'text',
    label: 'Current Medications',
    placeholder: 'List all current medications, dosages, and frequency',
    required: true,
  },
  height: {
    key: 'height',
    type: 'text',
    label: 'Height',
    placeholder: 'e.g. 5\'10"',
    required: true,
  },
  weight: {
    key: 'weight',
    type: 'number',
    label: 'Starting / Current Weight (lbs)',
    required: true,
  },
  // Shared contraindications
  pregnant_or_breastfeeding: {
    key: 'pregnant_or_breastfeeding',
    type: 'yes_no',
    label: 'Are you currently pregnant, trying to get pregnant, or breastfeeding?',
    stopIfYes: true,
  },
  cancer_history: {
    key: 'cancer_history',
    type: 'yes_no_explain',
    label: 'Do you have a personal history of cancer?',
    description: 'Including any hormone-sensitive cancers',
    consultIfYes: true,
  },
  liver_disease: {
    key: 'liver_disease',
    type: 'yes_no',
    label: 'Do you have liver disease or cirrhosis?',
    stopIfYes: true,
  },
  kidney_disease: {
    key: 'kidney_disease',
    type: 'yes_no_explain',
    label: 'Do you have kidney disease or significant kidney problems?',
    consultIfYes: true,
  },
  cardiac_disease: {
    key: 'cardiac_disease',
    type: 'yes_no_explain',
    label: 'Do you have severe heart disease or cardiac conditions?',
    consultIfYes: true,
  },
  thromboembolic_history: {
    key: 'thromboembolic_history',
    type: 'yes_no',
    label: 'Have you had an acute thromboembolic event (blood clot, DVT, PE)?',
    stopIfYes: true,
  },
  alcohol_use: {
    key: 'alcohol_use',
    type: 'select',
    label: 'Alcohol Consumption',
    options: [
      'None',
      '1–2 drinks per week',
      '3–5 drinks per week',
      '1–2 drinks per day',
      'More than 2 drinks per day',
    ],
    required: true,
  },
  smoking: {
    key: 'smoking',
    type: 'select',
    label: 'Smoking Status',
    options: ['Non-smoker', 'Former smoker', 'Current smoker'],
    required: true,
  },
  exercise_frequency: {
    key: 'exercise_frequency',
    type: 'select',
    label: 'Exercise Frequency',
    options: ['Sedentary', '1–2x per week', '3–4x per week', '5+ times per week'],
    required: true,
  },
  // Shared re-assessment questions
  medical_history_changes: {
    key: 'medical_history_changes',
    type: 'yes_no_explain',
    label: 'Have there been any changes to your medical history since your last consultation?',
    consultIfYes: true,
  },
  medication_changes: {
    key: 'medication_changes',
    type: 'yes_no_explain',
    label: 'Have there been any changes to your medications since your last consultation?',
    consultIfYes: true,
  },
  medication_change_request: {
    key: 'medication_change_request',
    type: 'yes_no_explain',
    label: 'Do you wish to make any changes to your current medication?',
    consultIfYes: true,
  },
};

// ─── TRT Screening Form ───────────────────────────────────────────────────────

export const TRT_SCREENING: IntakeFormDefinition = {
  serviceType: 'TRT',
  formType: 'SCREENING',
  title: 'TRT Screening',
  subtitle: 'Testosterone Replacement Therapy — Initial Eligibility Screening',
  sections: [
    {
      id: 'demographics',
      title: 'Patient Information',
      questions: [
        SHARED_QUESTIONS.dob,
        SHARED_QUESTIONS.allergies,
        SHARED_QUESTIONS.current_medications,
        SHARED_QUESTIONS.height,
        SHARED_QUESTIONS.weight,
        {
          key: 'trt_avg_bp',
          type: 'text',
          label: 'Average Blood Pressure (last 3 months)',
          placeholder: 'e.g. 120/80',
          required: true,
        },
        {
          key: 'trt_pulse',
          type: 'number',
          label: 'Resting Pulse / Heart Rate (bpm)',
          required: true,
        },
      ],
    },
    {
      id: 'trt_allergy_history',
      title: 'TRT Medication History',
      description: 'Please answer the following about your history with testosterone therapies.',
      questions: [
        {
          key: 'trt_prior_adverse_reaction',
          type: 'yes_no_explain',
          label: 'Have you ever had an adverse or allergic reaction to any testosterone therapy or support medication (Testosterone, Clomiphene, Enclomiphene, HCG, Gonadorelin, Anastrozole)?',
          consultIfYes: true,
        },
        {
          key: 'trt_prior_use',
          type: 'yes_no_explain',
          label: 'Have you previously used or are you currently using any TRT medications?',
        },
      ],
    },
    {
      id: 'trt_contraindications',
      title: 'Medical History & Contraindications',
      description: 'The following conditions may affect your eligibility for TRT.',
      questions: [
        {
          key: 'trt_prostate_cancer',
          type: 'yes_no',
          label: 'Have you been diagnosed with prostate cancer or breast cancer?',
          stopIfYes: true,
        },
        {
          key: 'trt_polycythemia',
          type: 'yes_no',
          label: 'Have you been diagnosed with polycythemia (elevated red blood cell count)?',
          stopIfYes: true,
        },
        {
          key: 'trt_sleep_apnea',
          type: 'yes_no_explain',
          label: 'Do you have sleep apnea?',
          consultIfYes: true,
        },
        {
          key: 'trt_gynecomastia',
          type: 'yes_no_explain',
          label: 'Do you have gynecomastia (enlarged breast tissue)?',
          consultIfYes: true,
        },
        {
          key: 'trt_elevated_calcium',
          type: 'yes_no_explain',
          label: 'Do you have elevated calcium levels (hypercalcemia)?',
          consultIfYes: true,
        },
        {
          key: 'trt_elevated_prolactin',
          type: 'yes_no_explain',
          label: 'Do you have elevated prolactin levels (hyperprolactinemia)?',
          consultIfYes: true,
        },
        SHARED_QUESTIONS.cardiac_disease,
        SHARED_QUESTIONS.liver_disease,
        SHARED_QUESTIONS.thromboembolic_history,
        SHARED_QUESTIONS.kidney_disease,
        SHARED_QUESTIONS.cancer_history,
      ],
    },
    {
      id: 'trt_adam',
      title: 'ADAM Questionnaire — Androgen Deficiency Symptoms',
      description: 'The ADAM questionnaire identifies symptoms of low testosterone. A positive result occurs if you answer YES to questions 1 or 7, or YES to more than 3 questions total.',
      questions: [
        {
          key: 'adam_low_libido',
          type: 'yes_no',
          label: '1. Do you have a decrease in libido (sex drive)?',
        },
        {
          key: 'adam_low_energy',
          type: 'yes_no',
          label: '2. Do you have a lack of energy?',
        },
        {
          key: 'adam_low_strength',
          type: 'yes_no',
          label: '3. Do you have a decrease in strength and/or endurance?',
        },
        {
          key: 'adam_lost_height',
          type: 'yes_no',
          label: '4. Have you lost height?',
        },
        {
          key: 'adam_low_enjoyment',
          type: 'yes_no',
          label: '5. Have you noticed a decreased enjoyment of life?',
        },
        {
          key: 'adam_sad_grumpy',
          type: 'yes_no',
          label: '6. Are you sad and/or grumpy?',
        },
        {
          key: 'adam_weak_erections',
          type: 'yes_no',
          label: '7. Are your erections less strong?',
        },
        {
          key: 'adam_low_sports',
          type: 'yes_no',
          label: '8. Have you noticed a recent deterioration in your ability to play sports?',
        },
        {
          key: 'adam_sleepy_after_dinner',
          type: 'yes_no',
          label: '9. Are you falling asleep after dinner?',
        },
        {
          key: 'adam_poor_work',
          type: 'yes_no',
          label: '10. Has there been a recent deterioration in your work performance?',
        },
      ],
    },
    {
      id: 'trt_medications_interactions',
      title: 'Medication Interaction Screening',
      description: 'TRT can interact with certain medications. Please confirm if you are currently taking any of the following.',
      questions: [
        {
          key: 'trt_anticoagulants',
          type: 'yes_no_explain',
          label: 'Are you taking anticoagulants (Warfarin, Anisindione, Phenprocoumon, Dicumarol)?',
          consultIfYes: true,
        },
        {
          key: 'trt_bupropion',
          type: 'yes_no',
          label: 'Are you taking Bupropion?',
          consultIfYes: true,
        },
        {
          key: 'trt_insulin',
          type: 'yes_no_explain',
          label: 'Are you taking insulin products?',
          consultIfYes: true,
        },
        {
          key: 'trt_methotrexate',
          type: 'yes_no',
          label: 'Are you taking Methotrexate?',
          consultIfYes: true,
        },
      ],
    },
    {
      id: 'trt_lifestyle',
      title: 'Lifestyle',
      questions: [
        SHARED_QUESTIONS.smoking,
        SHARED_QUESTIONS.alcohol_use,
        SHARED_QUESTIONS.exercise_frequency,
        {
          key: 'trt_depression',
          type: 'yes_no_explain',
          label: 'Are you currently experiencing depression or being treated for depression?',
          consultIfYes: true,
        },
      ],
    },
  ],
};

// ─── TRT Re-Assessment Form ───────────────────────────────────────────────────

export const TRT_REASSESSMENT: IntakeFormDefinition = {
  serviceType: 'TRT',
  formType: 'REASSESSMENT',
  title: 'TRT Re-Assessment',
  subtitle: 'Testosterone Replacement Therapy — 10-Week Follow-Up',
  sections: [
    {
      id: 'trt_ra_history_changes',
      title: 'Medical History Update',
      description: 'Please answer the following about any changes since your last consultation.',
      questions: [
        SHARED_QUESTIONS.medical_history_changes,
        SHARED_QUESTIONS.medication_changes,
        SHARED_QUESTIONS.medication_change_request,
      ],
    },
    {
      id: 'trt_ra_adverse_reactions',
      title: 'Adverse & Allergic Reactions',
      description: 'Have you experienced any of the following in the last 10 weeks? Answer YES to any that apply.',
      questions: [
        {
          key: 'trt_ra_injection_site_reaction',
          type: 'yes_no',
          label: 'Injection site reaction (pain, redness, swelling, bruising)',
          consultIfYes: true,
        },
        {
          key: 'trt_ra_acne',
          type: 'yes_no',
          label: 'Acne or oily skin',
          consultIfYes: true,
        },
        {
          key: 'trt_ra_hair_loss',
          type: 'yes_no',
          label: 'Hair loss or thinning',
          consultIfYes: true,
        },
        {
          key: 'trt_ra_mood_changes',
          type: 'yes_no',
          label: 'Mood changes, irritability, or aggression',
          consultIfYes: true,
        },
        {
          key: 'trt_ra_breast_tenderness',
          type: 'yes_no',
          label: 'Breast tenderness or enlargement (gynecomastia)',
          consultIfYes: true,
        },
        {
          key: 'trt_ra_fluid_retention',
          type: 'yes_no',
          label: 'Fluid retention or swelling in extremities',
          consultIfYes: true,
        },
        {
          key: 'trt_ra_sleep_changes',
          type: 'yes_no',
          label: 'Changes in sleep quality or new/worsening sleep apnea',
          consultIfYes: true,
        },
        {
          key: 'trt_ra_testicular_atrophy',
          type: 'yes_no',
          label: 'Testicular atrophy or changes',
          consultIfYes: true,
        },
        {
          key: 'trt_ra_libido_changes',
          type: 'yes_no',
          label: 'Significant changes in libido (increase or decrease)',
          consultIfYes: true,
        },
        {
          key: 'trt_ra_cardiovascular',
          type: 'yes_no',
          label: 'Cardiovascular symptoms (chest pain, palpitations, shortness of breath)',
          stopIfYes: true,
        },
        {
          key: 'trt_ra_polycythemia_symptoms',
          type: 'yes_no',
          label: 'Symptoms of polycythemia (headaches, dizziness, flushing, blurred vision)',
          stopIfYes: true,
        },
        {
          key: 'trt_ra_clotting',
          type: 'yes_no',
          label: 'Signs of blood clot (leg pain/swelling, sudden shortness of breath)',
          stopIfYes: true,
        },
      ],
    },
    {
      id: 'trt_ra_improvements',
      title: 'Symptom Improvement Assessment',
      description: 'Rate your improvement in the following areas since starting TRT. (1 = No improvement, 5 = Significant improvement)',
      questions: [
        {
          key: 'trt_ra_improve_libido',
          type: 'scale_1_5',
          label: 'Libido / Sex Drive',
        },
        {
          key: 'trt_ra_improve_energy',
          type: 'scale_1_5',
          label: 'Energy Levels',
        },
        {
          key: 'trt_ra_improve_strength',
          type: 'scale_1_5',
          label: 'Strength & Endurance',
        },
        {
          key: 'trt_ra_improve_enjoyment',
          type: 'scale_1_5',
          label: 'Enjoyment of Life',
        },
        {
          key: 'trt_ra_improve_mood',
          type: 'scale_1_5',
          label: 'Mood & Emotional Wellbeing',
        },
        {
          key: 'trt_ra_improve_erections',
          type: 'scale_1_5',
          label: 'Erection Quality',
        },
        {
          key: 'trt_ra_improve_athleticism',
          type: 'scale_1_5',
          label: 'Athletic Performance',
        },
        {
          key: 'trt_ra_improve_wakefulness',
          type: 'scale_1_5',
          label: 'Wakefulness / Alertness',
        },
        {
          key: 'trt_ra_improve_work',
          type: 'scale_1_5',
          label: 'Work Performance',
        },
      ],
    },
    {
      id: 'trt_ra_labs',
      title: 'Lab Work Reminder',
      description: 'Your 10-week labs should include: CBC, Total & Free Testosterone, SHBG, Estradiol, TSH, and any previously abnormal values. Please confirm your labs have been ordered or completed.',
      questions: [
        {
          key: 'trt_ra_labs_completed',
          type: 'yes_no',
          label: 'Have you completed or scheduled your 10-week lab work?',
        },
        {
          key: 'trt_ra_labs_notes',
          type: 'text',
          label: 'Any notes about your lab results or concerns?',
          placeholder: 'Optional — share any lab values or concerns',
        },
      ],
    },
  ],
};

// ─── GLP-1 Screening Form ─────────────────────────────────────────────────────

export const GLP1_SCREENING: IntakeFormDefinition = {
  serviceType: 'GLP1',
  formType: 'SCREENING',
  title: 'GLP-1 Screening',
  subtitle: 'GLP-1 Receptor Agonist Therapy — Initial Eligibility Screening',
  sections: [
    {
      id: 'glp1_demographics',
      title: 'Patient Information & Biometrics',
      questions: [
        SHARED_QUESTIONS.dob,
        SHARED_QUESTIONS.allergies,
        SHARED_QUESTIONS.current_medications,
        SHARED_QUESTIONS.height,
        SHARED_QUESTIONS.weight,
        {
          key: 'glp1_bmi',
          type: 'number',
          label: 'BMI (if known)',
          placeholder: 'Optional',
        },
        {
          key: 'glp1_body_fat_pct',
          type: 'number',
          label: 'Body Fat % (if known)',
          placeholder: 'Optional',
        },
        {
          key: 'glp1_avg_bp',
          type: 'text',
          label: 'Average Blood Pressure (last 3 months)',
          placeholder: 'e.g. 120/80',
          required: true,
        },
        {
          key: 'glp1_pulse',
          type: 'number',
          label: 'Resting Pulse / Heart Rate (bpm)',
          required: true,
        },
      ],
    },
    {
      id: 'glp1_allergy_history',
      title: 'GLP-1 Medication History',
      questions: [
        {
          key: 'glp1_prior_adverse_reaction',
          type: 'yes_no_explain',
          label: 'Have you ever had an adverse or allergic reaction to any GLP-1 medication (Tirzepatide, Semaglutide, Liraglutide, Dulaglutide, Exenatide, Lixisenatide)?',
          stopIfYes: true,
        },
        {
          key: 'glp1_other_glp1_current',
          type: 'yes_no_explain',
          label: 'Are you currently taking any other GLP-1 receptor agonist?',
          consultIfYes: true,
        },
      ],
    },
    {
      id: 'glp1_contraindications',
      title: 'Medical History & Contraindications',
      description: 'The following conditions may affect your eligibility for GLP-1 therapy.',
      questions: [
        {
          key: 'glp1_type1_diabetes',
          type: 'yes_no',
          label: 'Have you been diagnosed with Type 1 Diabetes?',
          stopIfYes: true,
        },
        {
          key: 'glp1_type2_diabetes',
          type: 'yes_no_explain',
          label: 'Have you been diagnosed with Type 2 Diabetes?',
          consultIfYes: true,
        },
        {
          key: 'glp1_hba1c_high',
          type: 'yes_no',
          label: 'Is your most recent HbA1C greater than 8%?',
          stopIfYes: true,
        },
        {
          key: 'glp1_diabetic_retinopathy',
          type: 'yes_no_explain',
          label: 'Do you have diabetic retinopathy?',
          consultIfYes: true,
        },
        {
          key: 'glp1_diabetic_ketoacidosis',
          type: 'yes_no',
          label: 'Have you had diabetic ketoacidosis?',
          stopIfYes: true,
        },
        {
          key: 'glp1_pancreatitis',
          type: 'yes_no',
          label: 'Have you had pancreatitis?',
          stopIfYes: true,
        },
        {
          key: 'glp1_gallbladder_disease',
          type: 'yes_no_explain',
          label: 'Do you have gallbladder disease?',
          consultIfYes: true,
        },
        {
          key: 'glp1_medullary_thyroid_cancer',
          type: 'yes_no',
          label: 'Have you been diagnosed with medullary thyroid carcinoma?',
          stopIfYes: true,
        },
        {
          key: 'glp1_men2',
          type: 'yes_no',
          label: 'Do you have Multiple Endocrine Neoplasia Type 2 (MEN2) or a family history of it?',
          stopIfYes: true,
        },
        {
          key: 'glp1_stomach_gi_problems',
          type: 'yes_no_explain',
          label: 'Do you have stomach or gastrointestinal problems (gastroparesis, severe GERD, etc.)?',
          consultIfYes: true,
        },
        {
          key: 'glp1_leber_optic_neuropathy',
          type: 'yes_no',
          label: 'Do you have Leber hereditary optic neuropathy?',
          stopIfYes: true,
        },
        SHARED_QUESTIONS.liver_disease,
        SHARED_QUESTIONS.kidney_disease,
        SHARED_QUESTIONS.pregnant_or_breastfeeding,
        SHARED_QUESTIONS.cancer_history,
        {
          key: 'glp1_chemotherapy',
          type: 'yes_no',
          label: 'Are you currently undergoing chemotherapy?',
          stopIfYes: true,
        },
      ],
    },
    {
      id: 'glp1_medications',
      title: 'Medication Interaction Screening',
      description: 'GLP-1 medications can interact with certain drugs.',
      questions: [
        {
          key: 'glp1_insulin',
          type: 'yes_no',
          label: 'Are you currently taking insulin?',
          stopIfYes: true,
        },
        {
          key: 'glp1_insulin_secretagogues',
          type: 'yes_no_explain',
          label: 'Are you taking insulin secretagogues or other diabetic medications?',
          consultIfYes: true,
        },
        {
          key: 'glp1_abiraterone',
          type: 'yes_no',
          label: 'Are you taking Abiraterone acetate?',
          stopIfYes: true,
        },
        {
          key: 'glp1_chloroquine_hydroxychloroquine',
          type: 'yes_no',
          label: 'Are you taking Chloroquine or Hydroxychloroquine?',
          stopIfYes: true,
        },
        {
          key: 'glp1_somatrogon',
          type: 'yes_no',
          label: 'Are you taking Somatrogon-GHLA?',
          stopIfYes: true,
        },
      ],
    },
    {
      id: 'glp1_lifestyle',
      title: 'Lifestyle',
      questions: [
        SHARED_QUESTIONS.smoking,
        SHARED_QUESTIONS.alcohol_use,
        SHARED_QUESTIONS.exercise_frequency,
      ],
    },
  ],
};

// ─── GLP-1 Re-Assessment Form ─────────────────────────────────────────────────

export const GLP1_REASSESSMENT: IntakeFormDefinition = {
  serviceType: 'GLP1',
  formType: 'REASSESSMENT',
  title: 'GLP-1 Re-Assessment',
  subtitle: 'GLP-1 Receptor Agonist Therapy — 1–3 Month Follow-Up',
  sections: [
    {
      id: 'glp1_ra_history_changes',
      title: 'Medical History Update',
      questions: [
        SHARED_QUESTIONS.medical_history_changes,
        SHARED_QUESTIONS.medication_changes,
        SHARED_QUESTIONS.medication_change_request,
      ],
    },
    {
      id: 'glp1_ra_adverse_reactions',
      title: 'Adverse & Allergic Reactions',
      description: 'Have you experienced any of the following in the last 1–3 months? Answer YES to any that apply.',
      questions: [
        {
          key: 'glp1_ra_nausea',
          type: 'yes_no',
          label: 'Nausea',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_vomiting',
          type: 'yes_no',
          label: 'Vomiting',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_diarrhea',
          type: 'yes_no',
          label: 'Diarrhea',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_constipation',
          type: 'yes_no',
          label: 'Constipation',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_abdominal_pain',
          type: 'yes_no',
          label: 'Abdominal pain or discomfort',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_injection_site_reaction',
          type: 'yes_no',
          label: 'Injection site reaction (pain, redness, swelling)',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_headache',
          type: 'yes_no',
          label: 'Headache',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_dizziness',
          type: 'yes_no',
          label: 'Dizziness or lightheadedness',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_fatigue',
          type: 'yes_no',
          label: 'Fatigue or low energy',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_appetite_changes',
          type: 'yes_no',
          label: 'Significant changes in appetite',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_hair_loss',
          type: 'yes_no',
          label: 'Hair loss',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_mood_changes',
          type: 'yes_no',
          label: 'Mood changes or depression',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_hypoglycemia',
          type: 'yes_no',
          label: 'Low blood sugar symptoms (shakiness, sweating, confusion)',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_allergic_reaction',
          type: 'yes_no',
          label: 'Signs of allergic reaction (rash, itching, swelling)',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_vision_changes',
          type: 'yes_no',
          label: 'Vision changes',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_muscle_weakness',
          type: 'yes_no',
          label: 'Muscle weakness or pain',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_rapid_heartbeat',
          type: 'yes_no',
          label: 'Rapid or irregular heartbeat',
          consultIfYes: true,
        },
        {
          key: 'glp1_ra_other_reactions',
          type: 'yes_no_explain',
          label: 'Any other adverse reactions not listed above?',
          consultIfYes: true,
        },
      ],
    },
    {
      id: 'glp1_ra_severe_symptoms',
      title: 'Severe Symptoms — Immediate Action Required',
      description: 'If you answer YES to ANY of the following, do NOT continue. Seek immediate medical attention.',
      questions: [
        {
          key: 'glp1_ra_severe_abdominal',
          type: 'yes_no',
          label: 'Severe abdominal pain that does not go away',
          stopIfYes: true,
        },
        {
          key: 'glp1_ra_severe_vomiting',
          type: 'yes_no',
          label: 'Severe vomiting that will not stop',
          stopIfYes: true,
        },
        {
          key: 'glp1_ra_neck_lump',
          type: 'yes_no',
          label: 'Lump or swelling in your neck, hoarseness, or difficulty swallowing',
          stopIfYes: true,
        },
        {
          key: 'glp1_ra_jaundice',
          type: 'yes_no',
          label: 'Yellowing of skin or eyes (jaundice)',
          stopIfYes: true,
        },
        {
          key: 'glp1_ra_severe_allergic',
          type: 'yes_no',
          label: 'Severe allergic reaction (difficulty breathing, swelling of face/lips/tongue)',
          stopIfYes: true,
        },
        {
          key: 'glp1_ra_severe_hypoglycemia',
          type: 'yes_no',
          label: 'Severe low blood sugar (loss of consciousness, seizure)',
          stopIfYes: true,
        },
      ],
    },
    {
      id: 'glp1_ra_progress',
      title: 'Progress Check',
      questions: [
        {
          key: 'glp1_ra_current_weight',
          type: 'number',
          label: 'Current Weight (lbs)',
          required: true,
        },
        {
          key: 'glp1_ra_weight_loss',
          type: 'number',
          label: 'Total Weight Lost Since Starting (lbs)',
        },
        {
          key: 'glp1_ra_overall_satisfaction',
          type: 'scale_1_5',
          label: 'Overall satisfaction with your GLP-1 therapy so far',
        },
      ],
    },
  ],
};

// ─── Female HRT Screening Form ────────────────────────────────────────────────

export const FEMALE_HRT_SCREENING: IntakeFormDefinition = {
  serviceType: 'FEMALE_HRT',
  formType: 'SCREENING',
  title: 'Female HRT Screening',
  subtitle: 'Female Hormone Replacement Therapy — Initial Eligibility Screening',
  sections: [
    {
      id: 'fhrt_demographics',
      title: 'Patient Information',
      questions: [
        SHARED_QUESTIONS.dob,
        SHARED_QUESTIONS.allergies,
        SHARED_QUESTIONS.current_medications,
        SHARED_QUESTIONS.height,
        SHARED_QUESTIONS.weight,
        {
          key: 'fhrt_last_clinic_visit',
          type: 'text',
          label: 'Date of Last Clinic Visit (if applicable)',
          placeholder: 'MM/YYYY or N/A',
        },
      ],
    },
    {
      id: 'fhrt_hrt_history',
      title: 'HRT Medication History',
      questions: [
        {
          key: 'fhrt_prior_adverse_reaction',
          type: 'yes_no_explain',
          label: 'Have you ever had an adverse or allergic reaction to any HRT medication?',
          consultIfYes: true,
        },
        {
          key: 'fhrt_prior_hrt_use',
          type: 'yes_no_explain',
          label: 'Have you previously used or are you currently using any HRT medications?',
        },
        {
          key: 'fhrt_dosage_form_preference',
          type: 'select',
          label: 'Preferred Dosage Form',
          options: ['Cream / Topical', 'Oral / Tablet', 'Injection', 'Patch', 'No preference'],
        },
      ],
    },
    {
      id: 'fhrt_contraindications',
      title: 'Medical History & Contraindications',
      description: 'The following conditions may affect your eligibility for HRT.',
      questions: [
        {
          key: 'fhrt_hormone_sensitive_cancer',
          type: 'yes_no',
          label: 'Have you been diagnosed with a hormone-sensitive cancer (breast, uterine, ovarian)?',
          stopIfYes: true,
        },
        {
          key: 'fhrt_genetic_cancer_risk',
          type: 'yes_no_explain',
          label: 'Do you have a known genetic predisposition to cancer (BRCA1/BRCA2)?',
          consultIfYes: true,
        },
        {
          key: 'fhrt_abnormal_mammogram',
          type: 'yes_no_explain',
          label: 'Have you had an abnormal mammogram in the last 12 months?',
          consultIfYes: true,
        },
        {
          key: 'fhrt_pcos',
          type: 'yes_no_explain',
          label: 'Have you been diagnosed with PCOS (Polycystic Ovary Syndrome)?',
          consultIfYes: true,
        },
        {
          key: 'fhrt_endometriosis',
          type: 'yes_no_explain',
          label: 'Have you been diagnosed with endometriosis?',
          consultIfYes: true,
        },
        {
          key: 'fhrt_lifelong_menstrual_irregularities',
          type: 'yes_no_explain',
          label: 'Have you had lifelong menstrual irregularities?',
          consultIfYes: true,
        },
        SHARED_QUESTIONS.thromboembolic_history,
        SHARED_QUESTIONS.liver_disease,
        SHARED_QUESTIONS.kidney_disease,
        SHARED_QUESTIONS.cardiac_disease,
        SHARED_QUESTIONS.pregnant_or_breastfeeding,
        SHARED_QUESTIONS.cancer_history,
      ],
    },
    {
      id: 'fhrt_reproductive_history',
      title: 'Reproductive & Menstrual History',
      questions: [
        {
          key: 'fhrt_hysterectomy',
          type: 'yes_no_explain',
          label: 'Have you had a hysterectomy? If yes, was it partial or total?',
        },
        {
          key: 'fhrt_menopause_status',
          type: 'select',
          label: 'Menopause Status',
          options: [
            'Pre-menopausal (regular periods)',
            'Peri-menopausal (irregular periods)',
            'Post-menopausal (no period for 12+ months)',
            'Surgical menopause',
          ],
          required: true,
        },
        {
          key: 'fhrt_last_period',
          type: 'text',
          label: 'Date of Last Menstrual Period',
          placeholder: 'MM/YYYY or N/A',
        },
        {
          key: 'fhrt_reproductive_plans',
          type: 'yes_no',
          label: 'Do you have plans for future pregnancy?',
        },
        {
          key: 'fhrt_pms_symptoms',
          type: 'yes_no_explain',
          label: 'Do you experience significant PMS symptoms?',
        },
      ],
    },
    {
      id: 'fhrt_symptoms',
      title: 'Current Symptom Assessment',
      description: 'Rate the severity of the following symptoms. (1 = None/Minimal, 5 = Severe)',
      questions: [
        {
          key: 'fhrt_sx_hot_flashes',
          type: 'scale_1_5',
          label: 'Hot flashes / Night sweats',
        },
        {
          key: 'fhrt_sx_low_libido',
          type: 'scale_1_5',
          label: 'Low libido / Decreased sex drive',
        },
        {
          key: 'fhrt_sx_vaginal_dryness',
          type: 'scale_1_5',
          label: 'Vaginal dryness / Discomfort',
        },
        {
          key: 'fhrt_sx_mood_changes',
          type: 'scale_1_5',
          label: 'Mood changes / Irritability / Anxiety',
        },
        {
          key: 'fhrt_sx_sleep_disturbances',
          type: 'scale_1_5',
          label: 'Sleep disturbances / Insomnia',
        },
        {
          key: 'fhrt_sx_fatigue',
          type: 'scale_1_5',
          label: 'Fatigue / Low energy',
        },
        {
          key: 'fhrt_sx_cognitive_changes',
          type: 'scale_1_5',
          label: 'Cognitive changes / Brain fog / Memory issues',
        },
        {
          key: 'fhrt_sx_joint_pain',
          type: 'scale_1_5',
          label: 'Joint pain / Muscle aches',
        },
        {
          key: 'fhrt_sx_weight_gain',
          type: 'scale_1_5',
          label: 'Weight gain / Difficulty losing weight',
        },
        {
          key: 'fhrt_sx_skin_changes',
          type: 'scale_1_5',
          label: 'Skin changes / Dryness / Thinning',
        },
        {
          key: 'fhrt_sx_hair_changes',
          type: 'scale_1_5',
          label: 'Hair thinning / Loss',
        },
        {
          key: 'fhrt_sx_urinary',
          type: 'scale_1_5',
          label: 'Urinary symptoms (urgency, frequency, incontinence)',
        },
      ],
    },
    {
      id: 'fhrt_medications_interactions',
      title: 'Medication Interaction Screening',
      description: 'HRT can interact with certain medications.',
      questions: [
        {
          key: 'fhrt_anticoagulants',
          type: 'yes_no_explain',
          label: 'Are you taking anticoagulants (Warfarin, blood thinners)?',
          consultIfYes: true,
        },
        {
          key: 'fhrt_insulin_meds',
          type: 'yes_no_explain',
          label: 'Are you taking insulin or diabetic medications?',
          consultIfYes: true,
        },
        {
          key: 'fhrt_thyroid_meds',
          type: 'yes_no_explain',
          label: 'Are you taking thyroid medications?',
          consultIfYes: true,
        },
      ],
    },
    {
      id: 'fhrt_lifestyle',
      title: 'Lifestyle',
      questions: [
        SHARED_QUESTIONS.smoking,
        SHARED_QUESTIONS.alcohol_use,
        SHARED_QUESTIONS.exercise_frequency,
        {
          key: 'fhrt_depression',
          type: 'yes_no_explain',
          label: 'Are you currently experiencing depression or being treated for depression?',
          consultIfYes: true,
        },
      ],
    },
  ],
};

// ─── Female HRT Re-Assessment Form ───────────────────────────────────────────

export const FEMALE_HRT_REASSESSMENT: IntakeFormDefinition = {
  serviceType: 'FEMALE_HRT',
  formType: 'REASSESSMENT',
  title: 'Female HRT Re-Assessment',
  subtitle: 'Female Hormone Replacement Therapy — 3–6 Month Follow-Up',
  sections: [
    {
      id: 'fhrt_ra_history_changes',
      title: 'Medical History Update',
      questions: [
        SHARED_QUESTIONS.medical_history_changes,
        SHARED_QUESTIONS.medication_changes,
        SHARED_QUESTIONS.medication_change_request,
      ],
    },
    {
      id: 'fhrt_ra_adverse_reactions',
      title: 'Adverse & Allergic Reactions',
      description: 'Have you experienced any of the following since starting HRT? Answer YES to any that apply.',
      questions: [
        {
          key: 'fhrt_ra_breast_tenderness',
          type: 'yes_no',
          label: 'Breast tenderness or swelling',
          consultIfYes: true,
        },
        {
          key: 'fhrt_ra_spotting_bleeding',
          type: 'yes_no',
          label: 'Unexpected vaginal spotting or bleeding',
          consultIfYes: true,
        },
        {
          key: 'fhrt_ra_nausea',
          type: 'yes_no',
          label: 'Nausea or stomach upset',
          consultIfYes: true,
        },
        {
          key: 'fhrt_ra_headaches',
          type: 'yes_no',
          label: 'New or worsening headaches / migraines',
          consultIfYes: true,
        },
        {
          key: 'fhrt_ra_mood_changes',
          type: 'yes_no',
          label: 'Mood changes, anxiety, or depression',
          consultIfYes: true,
        },
        {
          key: 'fhrt_ra_fluid_retention',
          type: 'yes_no',
          label: 'Fluid retention or bloating',
          consultIfYes: true,
        },
        {
          key: 'fhrt_ra_skin_reactions',
          type: 'yes_no',
          label: 'Skin reactions at application site (if topical)',
          consultIfYes: true,
        },
        {
          key: 'fhrt_ra_hair_changes',
          type: 'yes_no',
          label: 'Hair loss or unwanted hair growth',
          consultIfYes: true,
        },
        {
          key: 'fhrt_ra_acne',
          type: 'yes_no',
          label: 'Acne or oily skin',
          consultIfYes: true,
        },
        {
          key: 'fhrt_ra_clotting',
          type: 'yes_no',
          label: 'Signs of blood clot (leg pain/swelling, shortness of breath)',
          stopIfYes: true,
        },
        {
          key: 'fhrt_ra_cardiovascular',
          type: 'yes_no',
          label: 'Cardiovascular symptoms (chest pain, palpitations)',
          stopIfYes: true,
        },
      ],
    },
    {
      id: 'fhrt_ra_symptom_improvement',
      title: 'Symptom Improvement Since Starting HRT',
      description: 'Rate your improvement in the following areas. (1 = No improvement, 5 = Significant improvement)',
      questions: [
        {
          key: 'fhrt_ra_improve_hot_flashes',
          type: 'scale_1_5',
          label: 'Hot flashes / Night sweats',
        },
        {
          key: 'fhrt_ra_improve_libido',
          type: 'scale_1_5',
          label: 'Libido / Sex drive',
        },
        {
          key: 'fhrt_ra_improve_vaginal_dryness',
          type: 'scale_1_5',
          label: 'Vaginal dryness / Comfort',
        },
        {
          key: 'fhrt_ra_improve_mood',
          type: 'scale_1_5',
          label: 'Mood / Emotional wellbeing',
        },
        {
          key: 'fhrt_ra_improve_sleep',
          type: 'scale_1_5',
          label: 'Sleep quality',
        },
        {
          key: 'fhrt_ra_improve_energy',
          type: 'scale_1_5',
          label: 'Energy levels',
        },
        {
          key: 'fhrt_ra_improve_cognitive',
          type: 'scale_1_5',
          label: 'Cognitive function / Brain fog',
        },
        {
          key: 'fhrt_ra_improve_joint_pain',
          type: 'scale_1_5',
          label: 'Joint pain / Muscle aches',
        },
        {
          key: 'fhrt_ra_improve_skin',
          type: 'scale_1_5',
          label: 'Skin quality',
        },
      ],
    },
    {
      id: 'fhrt_ra_labs',
      title: 'Lab Work Reminder',
      description: 'Your follow-up labs should include: Testosterone, Estradiol, Progesterone, LH, FSH, DHEA-S. Full panel (fasting) every 6 months: CMP, Lipid Panel, CBC, TSH, AM Cortisol, and the above.',
      questions: [
        {
          key: 'fhrt_ra_labs_completed',
          type: 'yes_no',
          label: 'Have you completed or scheduled your follow-up lab work?',
        },
        {
          key: 'fhrt_ra_labs_notes',
          type: 'text',
          label: 'Any notes about your lab results or concerns?',
          placeholder: 'Optional — share any lab values or concerns',
        },
      ],
    },
  ],
};

// ─── Form Registry ────────────────────────────────────────────────────────────

export const FORM_REGISTRY: Record<string, IntakeFormDefinition> = {
  TRT_SCREENING: TRT_SCREENING,
  TRT_REASSESSMENT: TRT_REASSESSMENT,
  GLP1_SCREENING: GLP1_SCREENING,
  GLP1_REASSESSMENT: GLP1_REASSESSMENT,
  FEMALE_HRT_SCREENING: FEMALE_HRT_SCREENING,
  FEMALE_HRT_REASSESSMENT: FEMALE_HRT_REASSESSMENT,
};

// ─── Re-Assessment Timing ─────────────────────────────────────────────────────
// Days after screening submission when re-assessment becomes available

export const REASSESSMENT_TIMING_DAYS: Record<ServiceType, number> = {
  TRT: 70,          // 10 weeks
  GLP1: 30,         // 1 month minimum
  FEMALE_HRT: 90,   // 3 months minimum
};

// ─── Shared Question Deduplication ───────────────────────────────────────────
// For patients with multiple services, returns deduplicated question keys
// that have already been answered in a prior form submission.

export function getAlreadyAnsweredKeys(
  submittedAnswers: Record<string, Record<string, unknown>>
): Set<string> {
  const answered = new Set<string>();
  for (const answers of Object.values(submittedAnswers)) {
    for (const key of Object.keys(answers)) {
      answered.add(key);
    }
  }
  return answered;
}

export function filterDuplicateQuestions(
  form: IntakeFormDefinition,
  alreadyAnswered: Set<string>
): IntakeFormDefinition {
  return {
    ...form,
    sections: form.sections.map((section) => ({
      ...section,
      questions: section.questions.filter((q) => !alreadyAnswered.has(q.key)),
    })).filter((section) => section.questions.length > 0),
  };
}
