-- Agenda 2026 > Valores is legacy reference data. The operational source of
-- truth is Bitacora QS > Servicios_Master; Seguimiento Contable > Servicios
-- remains the detailed cost replica.

update qs_bookings booking
set service_id = master_service.id
from qs_services legacy_service,
     qs_services master_service
where booking.service_id = legacy_service.id
  and legacy_service.source_sheet = 'Valores'
  and master_service.source_sheet = 'Servicios_Master'
  and lower(trim(legacy_service.name)) = lower(trim(master_service.name));

delete from qs_services legacy_service
where legacy_service.source_sheet = 'Valores'
  and not exists (
      select 1
      from qs_bookings booking
      where booking.service_id = legacy_service.id
  );

update qs_services
set source_sheet = 'Valores_Archivado',
    source_row = null,
    active = false
where source_sheet = 'Valores';

update qs_sheet_sources
set purpose = 'legacy_excluded'
where spreadsheet_id = '1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4'
  and sheet_name = 'Valores';
