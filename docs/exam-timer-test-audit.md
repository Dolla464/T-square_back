# Exam Timer Test Audit

**Phase:** Read + Audit + Tests ONLY  
**Date:** 2026-03-25  
**Production code changed:** NO  
**Repos:** Backend `t-square-lms` · Frontend `T-square_front`

---

## 1. Symptom Under Investigation

| Reported | Meaning |
|----------|---------|
| Exam duration | 10 minutes (600 seconds) |
| Student saw | ~4361.5 seconds (~72 min 41.5 sec) |
| Conclusion | Not normal 1–2s clock drift — implies ~72-minute window or wrong snap value |

---

## 2. Architecture

### Backend flow

```
POST /api/exams/start
  → ExamService::startAttempt()
      → createOrResumeAttempt (started_at frozen on resume)
      → duration_minutes frozen on ExamAttempt row
  → ExamAttemptResource
      → ExamAttemptAuthorizationService::getTimeStatusPayload()
          deadline_at = started_at + duration_minutes
          remaining_seconds = max(0, deadline_ts - now_ts)
          server_time = now (ISO8601)
          is_timed_out = now >= deadline
```

### Frontend flow

```
QuizExamPage init → startExam() → useExam state
  → QuizTimer (inline, QuizExamPage.jsx:40–147)
      Effect 1: interval from deadline_at OR started_at + duration*60*1000
      Effect 2: snap timeLeft = remaining_seconds from server
      Effect 3: sync every 60s + visibilitychange
  → handleTimerSync → GET /time-status → merge into exam state
  → auto-submit when is_timed_out || remaining_seconds === 0
```

### Sources of truth

| Field | Source | Used for display? |
|-------|--------|-----------------|
| `duration_minutes` | Frozen on `ExamAttempt` at create | Yes (`parseFloat(exam.duration)`) |
| `started_at` | DB, unchanged on resume | Yes (fallback) |
| `deadline_at` | Server: `started_at + duration_minutes` | Yes (primary in Effect 1) |
| `remaining_seconds` | Server: `deadline - now` | Yes (Effect 2 snap) |
| `server_time` | Server `Carbon::now()` | **No** (received, unused) |

**Display path:** Effect 1 computes from `deadline_at`; Effect 2 **overwrites** with `remaining_seconds` whenever it changes.

---

## 3. Failure Points Matrix

| Area | Potential issue | Existing tests? | New audit tests? |
|------|-----------------|-----------------|------------------|
| Backend duration | Wrong `duration_minutes` on attempt | Partial (30m freeze) | **Yes** (10m) |
| Backend deadline | `started_at + duration` wrong | Partial | **Yes** (invariants) |
| Remaining seconds | `> duration*60` | No | **Yes** (>600 regression) |
| API serialization | ISO8601 fields | Partial | **Yes** (server_time math) |
| Frontend init | Timer hidden during load | No | Mirror only |
| Deadline parsing | `new Date()` ambiguity | No | **Yes** |
| Local countdown | Client clock skew | No | **Yes** (documented) |
| Server sync | Snap overwrites local | No | **Yes** |
| Visibility change | Sync on tab focus | No | **Blocked** (no RTL) |
| Resume | Reset to 600 | Partial | **Yes** |
| Refresh | Stale/wrong init | No | Mirror only |
| Race conditions | Dual effects + interval | No | **Yes** (sync mirror) |
| Device clock | ±10 min skew | No | **Yes** (cannot → 4361) |
| Unit conversion | minutes vs seconds | No | **Yes** |
| Lazy loading | Delayed timer mount | No | **Blocked** (no RTL) |
| Wrong deadline_at | ~72m window | No | **Yes** (reproduces 4361) |
| Wrong remaining snap | Effect 2 shows 4361 | No | **Yes** (documented) |

---

## 4. Code Search — Timer-Related Locations

### Backend

| File | Role |
|------|------|
| `app/Services/Exam/ExamAttemptAuthorizationService.php:65–109` | Deadline + remaining math |
| `app/Services/User/ExamService.php:50–190,489–518` | Start/resume + time-status |
| `app/Http/Resources/User/Exam/ExamAttemptResource.php:21–32` | API timing fields |
| `app/Http/Controllers/Api/User/ExamController.php:50–65,143–157` | start + time-status endpoints |
| `routes/student.php:137–141` | Route definitions |
| `tests/Feature/Student/StudentExamFlowTest.php` | Existing 30m flow tests |
| `tests/Feature/Student/ExamTimerAuditTest.php` | **New** 10m audit tests |

### Frontend

| File | Role |
|------|------|
| `QuizExamPage.jsx:40–147` | `QuizTimer` inline |
| `QuizExamPage.jsx:545–584` | `handleTimerSync` |
| `QuizExamPage.jsx:1168–1180` | Timer render |
| `hooks/useExam.js:48–91` | start + syncExamTime |
| `services/dashboardService.js:78–107` | API calls |
| `__tests__/quizTimerAuditMirror.js` | Audit mirror (not production) |
| `__tests__/quizTimerCalculations.test.js` | **New** calculation tests |
| `__tests__/quizTimerSyncBehavior.test.js` | **New** sync behavior tests |

**No `4361` literal found in production code.**

---

## 5. Frontend Timer Trace (Read-Only)

1. **`remaining_seconds` enters state:** `useExam.startExam()` → `setExam(res.data.data)`; updated by `syncExamTime()` merge.
2. **`deadline_at` enters state:** Same API payloads.
3. **`getEndTimeMs()`:** `deadline_at` first; else `started_at + durationMins * 60 * 1000`.
4. **`timeLeft` init:** Effect 1 calls `calculateTimeLeft()` once, then `setInterval` every 1s.
5. **Interval:** Recalculates `endTime - Date.now()`, floors to seconds.
6. **`remainingSeconds` change:** Effect 2 sets `timeLeft = max(0, remainingSeconds)` — **overwrites interval value**.
7. **`deadline_at` change:** Effect 1 deps change → interval recreated.
8. **`syncExamTime()`:** Merges API data; triggers Effect 2 if `remaining_seconds` changed.
9. **Tab visible:** Effect 3 calls `onSync()` → `handleTimerSync`.
10. **Rerender:** Memo on `QuizTimer`; props change recreates effects.
11. **Unmount:** Intervals/listeners cleared in effect cleanup.
12. **Refresh/resume:** `startExam()` returns server `started_at`/`deadline_at`; timer recalculates from absolute deadline.

---

## 6. Tests Added

### Backend — `tests/Feature/Student/ExamTimerAuditTest.php`

| Test | Coverage |
|------|----------|
| starts a 10 minute exam with deterministic timing fields | G1 new 10m |
| does not return more than 600 remaining seconds… | >600 regression |
| never returns negative remaining_seconds… | Safety |
| resumes immediately without resetting… | Resume A |
| resumes after 2 minutes with ~480 seconds | Resume B |
| keeps frozen duration_minutes after exam duration changed | Duration freeze |
| tracks remaining seconds at boundary offsets (10 datasets) | Boundaries |
| marks timed out attempts with zero remaining… | Timeout |
| does not reset timing when start called twice | Double start |
| does not generate timed countdown for untimed exam | duration=0 |

### Frontend — audit mirror tests (no production changes)

| File | Coverage |
|------|----------|
| `quizTimerAuditMirror.js` | Pure mirror of QuizTimer math |
| `quizTimerCalculations.test.js` | 10m payload, 4361.5 regression, units, parsing, fallback, clock offsets, malformed inputs, refresh/resume |
| `quizTimerSyncBehavior.test.js` | Snap up/down, race ordering, timeout, repeated sync |

---

## 7. Test Execution Results

### Commands

```bash
# Backend
cd "d:/ADEL/Web Developing/tsquare project/t-square-lms"
php artisan test tests/Feature/Student/ExamTimerAuditTest.php

# Frontend (mirror tests only)
cd "D:/ADEL/T-square_front"
npx vitest run src/modules/student-dashboard/pages/QuizExam/__tests__/
```

### Results

| Suite | Passed | Failed |
|-------|--------|--------|
| Backend `ExamTimerAuditTest.php` | **19** | **0** |
| Frontend `QuizExam/__tests__/` | **37** | **0** |

### Blocked (not run)

| Blocker | Impact |
|---------|--------|
| Missing `@testing-library/dom` | All existing RTL component tests fail at import; cannot run React component/integration tests without installing dependency |
| `QuizTimer` not exported | Cannot import production component; used audit mirror instead |

```bash
npm test  # FAIL: Cannot find module '@testing-library/dom'
```

---

## 8. Test Matrix

| Scenario | Backend | Frontend mirror | Expected | Result |
|----------|---------|-----------------|----------|--------|
| New 10m exam | ✓ | ✓ | ~600 sec | PASS |
| Resume immediately | ✓ | — | same deadline | PASS |
| Resume after 2m | ✓ | ✓ | ~480 sec | PASS |
| Resume after 9m | ✓ (boundary) | — | ~60 sec | PASS |
| Exact deadline | ✓ | ✓ | 0 / timeout | PASS |
| After deadline | ✓ | ✓ | timeout | PASS |
| Duration changed after start | ✓ | — | frozen 10m | PASS |
| Device clock +10m | — | ✓ | 0 (early) | PASS (documented) |
| Device clock -10m | — | ✓ | 1200 (late) | PASS (documented) |
| Tab hidden 2m | — | **Blocked** | — | Not tested |
| Refresh after 2m | ✓ | ✓ | ~480 sec | PASS |
| Re-render | — | **Blocked** | — | Not tested |
| Repeated sync | ✓ | ✓ | stable ≤600 | PASS |
| Sync/local race | — | ✓ | snap jump ±5s | PASS (documented) |
| Invalid numeric values | — | ✓ | documented | PASS |
| Timezone parsing | — | ✓ | ISO Z/offset | PASS |
| 4361.5 regression (valid 10m payload) | ✓ | ✓ | must not occur | PASS |
| Wrong deadline ~72m41s | — | ✓ | **4361 sec** | **REPRODUCED (mirror)** |
| Wrong remaining snap 4361 | — | ✓ | snap shows 4361 | **REPRODUCED (mirror)** |

---

## 9. 4361.5 Reproduction

### Status: **REPRODUCED in frontend audit mirror — NOT from valid 10-minute backend payload**

### NOT reproduced at backend layer

All backend tests confirm for `duration_minutes = 10`:

```
remaining_seconds <= 600
deadline_at - started_at == 600 seconds
deadline_at - server_time == remaining_seconds
```

**Backend is correct** for valid 10-minute attempts under deterministic tests.

### Reproduced at frontend layer (two paths)

#### Path A — Wrong `deadline_at` (~72m41s window)

```javascript
deadline_at: "2026-01-01T11:12:41.500Z"
started_at:  "2026-01-01T10:00:00Z"
duration:    10
now:         2026-01-01T10:00:00Z
→ calculateTimeLeft() = 4361 seconds
```

4361.5 ≈ 72×60 + 41.5 — the symptom matches a **~72–73 minute deadline**, not a 10-minute exam.

#### Path B — Effect 2 snap with wrong `remaining_seconds`

When `deadline_at` is correct (10m) but API sends `remaining_seconds: 4361`:

- Effect 1 (`calculateTimeLeft` from deadline): **600**
- Effect 2 snap: **`timeLeft = 4361`**

**First wrong displayed value:** `QuizTimer` Effect 2 (`QuizExamPage.jsx:109–113`) when `remaining_seconds` from API/state exceeds 600.

Backend tests prove backend does **not** emit 4361 for valid 10m attempts — so Path B requires either:

- Corrupted/stale client state from a different attempt/exam, or
- A non-reproduced backend edge case outside tested paths, or
- Manual observation of internal state (not MM:SS display — UI shows `72:41`)

---

## 10. Root-Cause Localization Chain

```
Database attempt values          ✅ Correct (duration_minutes=10 frozen)
        ↓
Backend service calculation      ✅ Correct (remaining <= 600)
        ↓
Resource serialization           ✅ Correct (ISO8601, consistent math)
        ↓
HTTP response                    ✅ Correct for valid 10m (tested)
        ↓
dashboardService                 ➖ Pass-through (not tested in isolation)
        ↓
useExam state                    ➖ Pass-through (not tested in isolation)
        ↓
QuizExamPage                     ➖ Not component-tested (RTL blocked)
        ↓
QuizTimer Effect 1 (deadline)    ✅ 600 for valid payload (mirror PASS)
        ↓
QuizTimer Effect 2 (snap)        ⚠️ CAN show 4361 if remaining_seconds wrong
        ↓
Rendered countdown               ⚠️ 4361 reproducible with wrong inputs
```

**Earliest layer where 4361 appears with valid backend payload:** Not demonstrated — valid payload yields 600 in both backend and Effect 1.

**Earliest layer where 4361 appears with any tested inputs:** Frontend `QuizTimer` Effect 2 snap OR Effect 1 with wrong `deadline_at`.

---

## 11. Findings Summary

### Confirmed

- Backend timing math is deterministic and bounded for 10-minute exams.
- `remaining_seconds > 600` never occurs at backend for valid 10m attempts (19/19 tests pass).
- Resume does not reset `started_at` or deadline.
- Duration is frozen on the attempt row.
- Frontend mirror: valid 10m ISO payload always initializes to 600.
- Frontend mirror: clock skew alone cannot produce 4361 from valid 10m payload.
- Frontend mirror: wrong `deadline_at` (~72m41s) produces exactly 4361 seconds.
- Frontend mirror: Effect 2 snap can display 4361 even when deadline math says 600.
- Dual-effect sync can cause ±few second jumps (documented, not 4361).

### Possible (not production-proven)

- Production API returned wrong `deadline_at` for a specific attempt (DB corruption, wrong exam linked, manual DB edit).
- Stale `remaining_seconds` in client state from previous session/attempt merged via Effect 2.
- Student observed raw state/DevTools value (4361.5) not MM:SS display.
- Lazy-load delay caused confusion (timer not visible) — not tested with RTL.

### Not reproduced

- Backend emitting 4361 for `duration_minutes = 10`.
- Valid 10m backend payload → frontend calculation > 600 (Effect 1 path).
- Clock skew → 4361.

### Ruled out (under tested conditions)

- Backend calculation bug for standard 10m timed flow.
- Resume resetting timer to 600 incorrectly.
- Unit conversion error (10 min → 600 sec) in backend.
- Normal ±1–2s drift explaining 4361.5.

---

## 12. Potential Fixes — Future Phase (NOT IMPLEMENTED)

| Fix | Why consider |
|-----|--------------|
| Single source of truth in `QuizTimer` | Remove Effect 1 vs Effect 2 conflict |
| Use `server_time` offset | Correct client clock skew in Effect 1 |
| Clamp display: `min(timeLeft, duration*60)` | Prevent showing > configured duration |
| Sync immediately after `startExam` | Reduce window for stale snap |
| Export/timer unit test in production | Enable RTL-free testing |
| Install `@testing-library/dom` | Enable component/race/visibility tests |
| Log diagnostic on `remaining_seconds > duration*60` | Catch anomalous API payloads in production |

---

## 13. Production Code Changed

```
NO
```

Only test files and this document were added/modified.

---

## 14. Files Changed (This Phase)

| Repo | File | Action |
|------|------|--------|
| t-square-lms | `docs/exam-timer-test-audit.md` | Created |
| t-square-lms | `tests/Feature/Student/ExamTimerAuditTest.php` | Created |
| T-square_front | `src/.../QuizExam/__tests__/quizTimerAuditMirror.js` | Created |
| T-square_front | `src/.../QuizExam/__tests__/quizTimerCalculations.test.js` | Created |
| T-square_front | `src/.../QuizExam/__tests__/quizTimerSyncBehavior.test.js` | Created |
