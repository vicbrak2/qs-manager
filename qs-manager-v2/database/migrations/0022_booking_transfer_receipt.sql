alter table qs_bookings
    add column if not exists transfer_receipt_image bytea,
    add column if not exists transfer_receipt_mime varchar(80),
    add column if not exists transfer_receipt_filename varchar(180),
    add column if not exists transfer_receipt_size integer,
    add column if not exists transfer_receipt_uploaded_at timestamptz;

