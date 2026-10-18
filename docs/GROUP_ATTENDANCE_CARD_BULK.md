# Group attendance card bulk issuance (Phase 2)

## Endpoints

- `POST /api/admin/learning-groups/{learningGroup}/attendance-cards/batch`
- `GET /api/admin/learning-groups/{learningGroup}/attendance-cards/batch/{client_operation_id}` (metadata only; no tokens)

## Modes

- `issue_new_only` (default)
- `reissue_active_only` (`confirm_reissue: true` required)
- `issue_all` (`confirm_reissue: true` when snapshot includes any reissue)

## Batch cursor

- Max 50 students per request; `from_sequence` must align to multiples of 50.
- `next_batch_index` on the operation drives strict ordering; replay of completed batches is read-only.

## Recovery guarantees

- Per-student DB transaction: card row + `group_attendance_card_bulk_operation_id` + operation student `card_id` commit together.
- `processing` rows recover via explicit card↔operation link; ambiguous states become `RECOVERY_AMBIGUOUS` (no blind reissue).
- Plain tokens are returned at most once per student row (`token_response_generated_at`); replays and status never return tokens.
- Not absolute exactly-once across all failure modes; concurrent paths rely on row locks + group cache lock.

## Retention

- `attendance-cards:cleanup-bulk-operations` (daily 03:30): deletes **completed** operations older than 30 days after nulling card FK links. Active cards are never deleted.

## MySQL migrations

Foreign keys and indexes use short names (`gac_*`) because MySQL limits identifier length to 64 characters.

## MySQL concurrency tests (non-production)

Dedicated database: `t_square_lms_test` (see `phpunit.mysql-concurrency.xml`).

```bash
vendor/bin/pest tests/Feature/Admin/GroupAttendanceCardBulkConcurrencyTest.php -c phpunit.mysql-concurrency.xml
```

## API documentation

Regenerate OpenAPI via Scramble (not hand-edited):

```bash
php -d memory_limit=512M artisan scramble:export
```

Current committed `api.json` may lag until export is run successfully.
