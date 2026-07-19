# Smart Sourcing Academy — Stakeholder Overview

**Smart Sourcing USA | Enterprise Training Platform**  
*One-page summary for non-technical audiences | June 2026*

---

## What is SSU Academy?

Smart Sourcing Academy is Smart Sourcing USA’s online training platform. It supports **two audiences**:

1. **Internal employees** — company staff who train for free on internal and shared courses.  
2. **External learners** — public registrants who can browse the catalog, pay for courses, and be tracked through a hiring pipeline.

Trainers and administrators manage courses, monitor progress, handle payments, and review candidates from a secure dashboard.

---

## Who Uses the Platform?

| Role | Typical user | What they do |
|------|--------------|--------------|
| **Employee learner** | Staff with a company email | Enroll free; access internal training |
| **External learner** | Job seekers, contractors, public students | Register, pay for courses, complete training |
| **Trainer** | Course author | Create courses, view student progress |
| **Administrator** | HR, ops, leadership | Users, payments, candidates, refunds |

Employees are recognized automatically by email domain. Self-registration is intended for external learners.

---

## Key Features at a Glance

### 1. Course catalog and access

- Public course catalog open to guests and external learners.
- **Internal-only** courses hidden from the public; visible to employees.
- Courses can be marked **Internal**, **Public**, or **Both** when created.

### 2. Public paid enrollment

- External learners see paid courses on the open catalog.
- **Buy Now** → secure checkout (Stripe sandbox ready).
- Automatic enrollment after successful payment.
- Employees skip checkout and enroll for free.

### 3. Candidate pipeline (HR)

- External learners appear in **Candidate Pipeline** with profile and CV.
- Tracks paid course/exam progress, scores, and completion.
- Hiring stages: **New → In Review → Shortlisted → Hired / Rejected**.
- Internal notes for HR (tracking only; no automatic decisions).

### 4. Payments and refunds

- **Refund Tracking** — manual status per payment: Paid, Refund Pending, Refunded.
- Full audit trail: who changed status, when, and notes.
- When a candidate is marked **Hired**, admins can **Process Refund** via Stripe/PayPal (with confirmation).

### 5. Trainer visibility

- **Student Progress** per course: completion %, quizzes, assignments, exams.
- Trainers see their own courses; admins see all.

### 6. Content protection in the course player

- Reduces casual copying (text selection, right-click, common shortcuts).
- Uploaded videos served via temporary secure links for enrolled users only.
- Trainers choose per resource: **downloadable** (e.g. PDF worksheets) or **view-only**.
- Assignment take-offs remain downloadable for offline work.

---

## How the Journeys Work

### External learner (public)

```
Browse catalog → Register / Log in → Buy course (Stripe) → Take course → HR reviews in Candidate Pipeline
```

### Employee (internal)

```
Log in (company email) → Browse all relevant courses → Enroll free → Complete training
```

### HR / Admin (hired candidate)

```
Candidate Pipeline → Mark Hired → Process Refund (if applicable) → Update refund status and notes
```

---

## What Requires Setup or Awareness

| Item | Notes |
|------|--------|
| **Stripe test keys** | Add to environment or enable in Dashboard → Payment for checkout testing |
| **Production payments** | Switch from sandbox to live keys when ready to go live |
| **Content protection** | Deters casual copying; not DRM — screen recording still possible |
| **Branding** | Unified SSU Academy identity across UI, settings, and seeded content |

---

## Elevator pitch (30 seconds)

> Smart Sourcing Academy is our training and hiring support platform. Employees train free on internal content. External learners pay through a public catalog. HR tracks candidates with CVs, scores, and hiring status, and can process refunds when someone is hired. Trainers see real progress, and course content is protected against casual copying while assignment materials stay downloadable.

---

## Dashboard menu (admin quick reference)

| Menu area | Purpose |
|-----------|---------|
| Courses → Student Progress | Trainer/admin progress reports |
| Candidate Pipeline | External learner hiring funnel |
| Payment Report → Refund Tracking | Payment and refund status |
| Users List | Manage learners and employee/external type |
| Settings → Payment | Stripe and gateway configuration |

---

*For technical details, see `CONTENT_PROTECTION.md` and project documentation in the repository.*
