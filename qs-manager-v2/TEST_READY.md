# QS Manager V2 - Test Suite Readiness Report

All test suites have been successfully implemented and are ready for execution. This document details the setup, execution commands, coverage details, and structure of the test suites.

## 1. Test Suite Execution Commands

To execute the tests in the standalone application directory (`c:\Users\USER\Repo\qs-manager\qs-manager-v2`):

### Backend Integration Tests (PHPUnit)
```bash
# Run all backend tests, including the new integration and validation suites
vendor/bin/phpunit
```

### Frontend E2E Tests (Playwright)
```bash
# Navigate to the E2E tests directory
cd tests/E2E

# Install dependencies (only required on first run)
npm install

# Install Playwright browser engines
npx playwright install chromium

# Run E2E test suite against the local development server (assumed running at http://localhost:8080)
npm run test
```

---

## 2. Test Tiers Summary Table

| Test Tier | Test Case Count | Source Directory / File | Description |
| :--- | :---: | :--- | :--- |
| **Domain Unit Tests** | 3 | `tests/Domain` | Unit tests for domain models (`BookingTest`, `ServiceTest`, `StaffMemberTest`) validating business invariant rules. |
| **Backend Integration & Validation** | 18 | `tests/Integration/HttpRoutesTest.php` | Simulates HTTP requests (GET, POST, PUT, DELETE) validating strict inputs for Services, Team, and Booking Controllers, mock GAS integration (R3), and error tolerance. |
| **Frontend UI Integration** | 1 | `tests/Integration/HttpRoutesTest.php::testWebDashboardHtmlContent` | Assertions on `/` HTML response to verify structural layout, font configurations, pagination controls, dropdown elements, and script files. |
| **Frontend E2E Tests** | 7 | `tests/E2E/dashboard.spec.ts` | Dynamic Playwright browser tests validating real-time filtering, visual token styling, form validations, toast notifications, pagination, and GAS synchronization buttons. |

---

## 3. Checklist of Features Covered

### Backend Validation & Controller Integration (R2)
- [x] **ServicesController Validations**:
  - `name`: required, min 3 characters, max 160 characters.
  - `category`: optional, max 80 characters.
  - `duration_minutes`: optional, positive integer.
  - Validation failures return `422 Unprocessable Entity` in JSON format with clear error fields.
- [x] **TeamController Validations**:
  - `display_name`: required, min 3 characters, max 160 characters.
  - `role`: required, must be exactly one of: `admin`, `coordinadora`, `staff`.
  - Validation failures return `422 Unprocessable Entity` with JSON errors.
- [x] **BookingController Validations**:
  - `service_id`: optional, must reference an existing service ID.
  - `staff_id`: optional, must reference an existing staff member ID.
  - `customer_name`: optional, max 160 characters.
  - `customer_phone`: optional, regex `/^\+?[0-9\s\-]+$/`, max 40 characters.
  - `scheduled_for`: optional, parseable ISO-8601 datetime.
  - `status`: required, must be exactly one of: `draft`, `confirmed`, `cancelled`, `completed`.
  - Validation failures return `422 Unprocessable Entity` with JSON errors.
- [x] **Database Transactions**: Secure transaction blocks tested.

### Google Sheets & GAS Integration (R3)
- [x] **GAS Sync Payload Format**: Creating or updating bookings triggers a POST request to `GAS_WEBAPP_URL` matching the exact JSON structure:
  - `id`, `service_id`, `service_name`, `staff_id`, `staff_name`, `customer_name`, `customer_phone`, `fecha`, `hora`, `status`, `tipo` (Servicio).
- [x] **Manual Sync Endpoint**: `POST /api/v1/bookings/{id}/sync-gas` returns success or warning.
- [x] **Network Resilience**: Booking creation and update capture network/timeout errors gracefully, return a `warning` response field, and write `failed` sync status to the database instead of crashing.

### Frontend UX & UI Verification (R1)
- [x] **Google Font Outfit**: Verify font configuration and headers styling.
- [x] **Real-Time Booking Filtering**: Instant real-time filtering for Service, Staff, and Status dropdown controls without page refreshes.
- [x] **Table Pagination**: Max 10 rows per page, Anterior and Siguiente buttons, rows-per-page dropdown controls.
- [x] **Toasts Alert Auto-Dismiss**: 5-second fadeout auto-dismiss notification states.
- [x] **Validation Helpers**: Input fields highlighting in red (`--danger`) and error text labels rendered underneath.
- [x] **Sincronizar GAS UI Button**: Action button per table row triggers POST `/sync-gas` and displays toast feedback.

---

## 4. Test Execution Results

All integration tests inside PHPUnit have been successfully configured and pass. The E2E tests have been fully written and configured to target `http://localhost:8080`.
