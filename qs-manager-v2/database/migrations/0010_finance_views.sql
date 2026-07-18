CREATE OR REPLACE VIEW v_sheet_import_runs_latest AS
SELECT r.*
FROM qs_sheet_import_runs r
WHERE r.status = 'completed'
  AND r.id = (
      SELECT MAX(id) 
      FROM qs_sheet_import_runs 
      WHERE source_id = r.source_id AND status = 'completed'
  );

CREATE OR REPLACE VIEW v_cash_tracking_latest AS
SELECT c.*,
       COALESCE(
           c.service_external_id, 
           md5(c.service_date::text || '|' || COALESCE(lower(trim(c.customer_name)), '') || '|' || COALESCE(lower(trim(c.service_names)), ''))
       ) as stable_external_id
FROM qs_sheet_cash_tracking_rows c
JOIN v_sheet_import_runs_latest r ON r.id = c.import_run_id;

CREATE OR REPLACE VIEW v_operational_expense_latest AS
SELECT o.*,
       COALESCE(
           o.expense_external_id, 
           md5(o.expense_date::text || '|' || COALESCE(lower(trim(o.concept)), ''))
       ) as stable_external_id
FROM qs_sheet_operational_expense_rows o
JOIN v_sheet_import_runs_latest r ON r.id = o.import_run_id;

CREATE OR REPLACE VIEW v_bitacora_latest AS
SELECT b.*,
       COALESCE(
           b.qs_external_id, 
           b.calendar_event_id, 
           md5(b.service_date::text || '|' || COALESCE(lower(trim(b.customer_name)), '') || '|' || COALESCE(lower(trim(b.service_name)), ''))
       ) as stable_external_id
FROM qs_sheet_bitacora_rows b
JOIN v_sheet_import_runs_latest r ON r.id = b.import_run_id;

CREATE OR REPLACE VIEW v_agenda_latest AS
SELECT a.*,
       COALESCE(
           a.calendar_event_id, 
           md5(a.service_date::text || '|' || COALESCE(lower(trim(a.customer_name)), '') || '|' || COALESCE(lower(trim(a.service_name)), ''))
       ) as stable_external_id
FROM qs_sheet_agenda_month_rows a
JOIN v_sheet_import_runs_latest r ON r.id = a.import_run_id;
