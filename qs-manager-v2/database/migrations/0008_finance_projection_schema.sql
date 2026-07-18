-- 0008_finance_projection_schema.sql

-- Adaptar tabla preexistente qs_finance_entries (creada en 0001) para usarse como Read Model financiero.
ALTER TABLE qs_finance_entries
    ADD COLUMN IF NOT EXISTS external_id VARCHAR(120),
    ADD COLUMN IF NOT EXISTS source_type VARCHAR(60),
    ADD COLUMN IF NOT EXISTS source_sheet VARCHAR(120),
    ADD COLUMN IF NOT EXISTS source_row INTEGER,
    ADD COLUMN IF NOT EXISTS status VARCHAR(40),
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'CLP',
    ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}',
    ADD COLUMN IF NOT EXISTS import_run_id BIGINT REFERENCES qs_sheet_import_runs(id);

-- Indice único compuesto para soportar desdoblamientos (ej. una fila produce ingreso y costo)
CREATE UNIQUE INDEX IF NOT EXISTS qs_finance_entries_source_unique
    ON qs_finance_entries(entry_type, source_type, source_sheet, source_row)
    WHERE source_sheet IS NOT NULL;

-- Indice para las agrupaciones y filtros del dashboard
CREATE INDEX IF NOT EXISTS qs_finance_entries_period_idx
    ON qs_finance_entries(occurred_on, entry_type, status);
