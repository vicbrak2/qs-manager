-- 0009_finance_projection_sync_run.sql

-- Añadir sync_run_id a qs_finance_entries
ALTER TABLE qs_finance_entries
    ADD COLUMN IF NOT EXISTS sync_run_id BIGINT REFERENCES qs_sync_runs(id);
