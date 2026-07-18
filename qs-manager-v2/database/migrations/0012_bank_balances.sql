CREATE TABLE IF NOT EXISTS qs_bank_balances (
    id SERIAL PRIMARY KEY,
    abono_servicios_real NUMERIC(15, 2) DEFAULT 0.00,
    caja_real NUMERIC(15, 2) DEFAULT 0.00,
    inyeccion_real NUMERIC(15, 2) DEFAULT 0.00,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Seed initial values from user's Mach bank screenshot:
-- Abono de servicios: $221.370
-- Caja: $60.584
-- Inyección de capital: $0
INSERT INTO qs_bank_balances (id, abono_servicios_real, caja_real, inyeccion_real)
VALUES (1, 221370.00, 60584.00, 0.00)
ON CONFLICT (id) DO UPDATE SET
    abono_servicios_real = EXCLUDED.abono_servicios_real,
    caja_real = EXCLUDED.caja_real,
    inyeccion_real = EXCLUDED.inyeccion_real;
