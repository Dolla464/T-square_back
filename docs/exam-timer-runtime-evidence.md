# Exam Timer Runtime Evidence — Instrumentation Guide

**Phase:** Diagnostic logging only — NO timer fixes  
**Date:** 2026-03-25  
**Related audit:** [`docs/exam-timer-test-audit.md`](./exam-timer-test-audit.md)

---

## 1. Purpose

Collect runtime evidence to identify the **first layer** where a 10-minute exam timer becomes an abnormal value (e.g. `4361.5` seconds).

Instrumentation is **passive** — it does not modify calculations, state, API contracts, or control flow.

---

## 2. What Was Instrumented

### Backend

| Event | Prefix | Location |
|-------|--------|----------|
| Start / resume response | `[EXAM_TIMER_DIAGNOSTIC][START]` | `ExamController::start()` after `ExamAttemptResource` resolve |
| Time-status response | `[EXAM_TIMER_DIAGNOSTIC][TIME_STATUS]` | `ExamService::getAttemptTimeStatus()` before return |

Helper: `app/Support/ExamTimerDiagnostic.php`  
Config: `config/exam_timer.php`

### Frontend

| Event | Prefix | Location |
|-------|--------|----------|
| Start API raw response | `[EXAM_TIMER_DIAGNOSTIC][START_RESPONSE]` | `useExam.js` — before `setExam` |
| Time-status raw response | `[EXAM_TIMER_DIAGNOSTIC][TIME_STATUS_RESPONSE]` | `useExam.js` — before state merge |
| Timer props | `[EXAM_TIMER_DIAGNOSTIC][TIMER_INPUT]` | `QuizTimer` — on prop change |
| End-time calculation | `[EXAM_TIMER_DIAGNOSTIC][END_TIME]` | `QuizTimer` Effect 1 init |
| timeLeft assignment | `[EXAM_TIMER_DIAGNOSTIC][TIME_LEFT]` | init, sync, suspicious ticks |
| Rendered display | `[EXAM_TIMER_DIAGNOSTIC][RENDER]` | first render + abnormal values |

Helper: `T-square_front/src/modules/student-dashboard/utils/examTimerDiagnostic.js`

---

## 3. How to Enable

### Backend (Laravel)

```env
EXAM_TIMER_DIAGNOSTIC=true
```

Then refresh config cache if used in production:

```bash
php artisan config:clear
# or after testing: php artisan config:cache
```

Logs appear in the default Laravel log channel (typically `storage/logs/laravel.log`).

Search:

```text
[EXAM_TIMER_DIAGNOSTIC]
```

### Frontend (Vite)

```env
VITE_EXAM_TIMER_DIAGNOSTIC=true
```

Rebuild or restart dev server:

```bash
npm run dev
# or production build: npm run build
```

Logs appear in **browser DevTools Console**.

---

## 4. How to Disable (Default)

```env
EXAM_TIMER_DIAGNOSTIC=false
VITE_EXAM_TIMER_DIAGNOSTIC=false
```

When disabled (default):

- No diagnostic logs emitted
- No behavioral difference from pre-instrumentation code paths

---

## 5. Logged Fields

### Safe to log

- `attempt_id`
- `duration` / `duration_minutes` / `duration_seconds`
- `started_at`, `deadline_at`, `server_time`
- `remaining_seconds`
- `status`, `is_timed_out`
- Calculated diagnostics: `calculated_end_time_ms`, `source`, `timeLeft`
- Sanity flags (observation only): `remaining_exceeds_duration`, `deadline_duration_mismatch`, `time_left_exceeds_expected`

### NOT logged

- Passwords, tokens, cookies, auth headers
- Exam answers, question content
- Student names, emails, phone numbers
- Full request/response bodies

---

## 6. Sanity Flags (Diagnostic Only)

For a timed exam with `duration_minutes = N`:

| Flag | Meaning |
|------|---------|
| `remaining_exceeds_duration` | `remaining_seconds > N * 60` |
| `deadline_duration_mismatch` | `(deadline_at - started_at) != N * 60` (±1s) |
| `time_left_exceeds_expected` | Frontend `timeLeft > N * 60` |
| `expected_max_seconds` | `N * 60` (logging reference only) |

These flags **do not** clamp, reject, or alter behavior.

---

## 7. Correlating Backend + Frontend

Use `attempt_id` as the correlation key.

Example grep:

```bash
# Backend
grep "EXAM_TIMER_DIAGNOSTIC" storage/logs/laravel.log | grep "attempt_id=123"

# Frontend — filter Console by:
EXAM_TIMER_DIAGNOSTIC
```

---

## 8. Reproduction Procedure

1. Set `EXAM_TIMER_DIAGNOSTIC=true` on backend and `VITE_EXAM_TIMER_DIAGNOSTIC=true` on frontend.
2. Restart backend workers / clear config cache; restart Vite or rebuild frontend.
3. Open browser DevTools → Console.
4. Start a known **10-minute** exam as a student.
5. Record `attempt_id` from first `[START_RESPONSE]` log.
6. Keep exam open; allow normal sync (60s) and tab visibility if reproducing intermittently.
7. If timer shows abnormal value (e.g. `72:41` or internal `4361`):
   - Note displayed timer
   - Note timestamp
   - Capture console logs for that `attempt_id`
   - Capture backend logs for same `attempt_id`
8. Identify **FIRST ABNORMAL VALUE** chronologically:

```text
Backend START/TIME_STATUS
  → Frontend START_RESPONSE / TIME_STATUS_RESPONSE
  → TIMER_INPUT
  → END_TIME
  → TIME_LEFT
  → RENDER
```

---

## 9. Expected Normal Output (10-minute exam)

```text
[EXAM_TIMER_DIAGNOSTIC][START]
  attempt_id=123
  duration_minutes=10
  remaining_seconds=600
  remaining_exceeds_duration=false
  deadline_duration_mismatch=false

[EXAM_TIMER_DIAGNOSTIC][START_RESPONSE]
  remaining_seconds=600

[EXAM_TIMER_DIAGNOSTIC][TIMER_INPUT]
  remainingSeconds=600

[EXAM_TIMER_DIAGNOSTIC][END_TIME]
  source=deadline_at

[EXAM_TIMER_DIAGNOSTIC][TIME_LEFT]
  source=initialization
  timeLeft=600

[EXAM_TIMER_DIAGNOSTIC][RENDER]
  display_minutes=10
  display_seconds=0
```

---

## 10. Scenarios to Distinguish

| Scenario | First abnormal event likely at |
|----------|-------------------------------|
| A — Backend problem | `[START]` or `[TIME_STATUS]` with `remaining_seconds > 600` |
| B — State corruption | `[START_RESPONSE]` correct → later `[TIMER_INPUT]` wrong |
| C — Props wrong | API/state correct → `[TIMER_INPUT]` wrong |
| D — Effect 2 snap | `[TIMER_INPUT]` correct → `[TIME_LEFT] source=remaining_seconds_sync` abnormal |
| E — Wrong deadline | `[END_TIME]` with ~72m offset or `deadline_duration_mismatch=true` |
| F — Display only | `[TIME_LEFT]` correct → `[RENDER]` wrong (unlikely with current formatter) |

---

## 11. High-Frequency Logging Policy

- **No** log every 1-second tick by default.
- `[TIME_LEFT]` from `deadline_countdown` logs only when `timeLeft > duration * 60`.
- Init, sync, and API responses always log when diagnostic mode is enabled.

---

## 12. Files Changed (Instrumentation Phase)

### Backend (`t-square-lms`)

| File | Change |
|------|--------|
| `config/exam_timer.php` | Created — diagnostic flag |
| `app/Support/ExamTimerDiagnostic.php` | Created — logger |
| `app/Http/Controllers/Api/User/ExamController.php` | START log only |
| `app/Services/User/ExamService.php` | TIME_STATUS log only |

### Frontend (`T-square_front`)

| File | Change |
|------|--------|
| `src/modules/student-dashboard/utils/examTimerDiagnostic.js` | Created |
| `src/modules/student-dashboard/hooks/useExam.js` | START_RESPONSE + TIME_STATUS_RESPONSE logs |
| `src/modules/student-dashboard/pages/QuizExam/QuizExamPage.jsx` | QuizTimer diagnostic logs only |

**Not modified:** Phase 9A performance code, timer calculations, effect logic, API contracts, database schema.

---

## 13. Runtime Diagnosis Status

```text
No runtime reproduction was performed; instrumentation is ready.
```

Update this section after collecting production/staging evidence.
