CREATE OR REPLACE VIEW v_workshop_latest AS
SELECT w.*,
       md5(w.workshop_date::text || '|' || COALESCE(lower(trim(w.customer_name)), '')) as stable_external_id
FROM qs_sheet_workshop_rows w
JOIN v_sheet_import_runs_latest r ON r.id = w.import_run_id;
