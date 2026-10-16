# Student exam journey log

Structured audit trail for student exam flows (start, resume, submit, denials, scheduler auto-close, and optional client breadcrumbs).

## Enable on server

```env
EXAM_STUDENT_EXAM_LOG=true
# Optional: disable client breadcrumbs while keeping server events
EXAM_STUDENT_EXAM_LOG_CLIENT=true
```

After changing env:

```bash
php artisan config:cache
```

## Log file

- Channel: `student_exam`
- Path: `storage/logs/student-exam-YYYY-MM-DD.log`
- Retention: 14 days (daily driver)

Each line is a Monolog record with message `student_exam_journey` and JSON context fields such as:

| Field | Meaning |
| --- | --- |
| `correlation_id` | Ties one HTTP session / exam API flow together |
| `event` | e.g. `ATTEMPT_STARTED`, `ACCESS_DENIED`, `ATTEMPT_COMPLETED` |
| `source` | `server` or `client` |
| `attempt_id`, `exam_id`, `student_id` | When known |
| `completion_trigger` | e.g. `student_submit`, `timeout_on_save`, `scheduler` |

## Frontend (optional)

```env
VITE_EXAM_STUDENT_EXAM_LOG=true
```

Batches allowlisted client events to `POST /api/exams/attempts/{id}/journey-events` (throttled). The server never trusts client pass/fail or ownership fields.

## Search examples

```bash
grep ATTEMPT_STARTED storage/logs/student-exam-*.log
grep correlation_id storage/logs/student-exam-*.log | grep ACCESS_DENIED
```

## Default

Both backend and frontend flags default to **off** so production behavior and volume are unchanged until you opt in.
