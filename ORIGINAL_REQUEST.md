# Original User Request

## Initial Request — 2026-07-13T02:32:55Z

Review the current state of the QS Manager V2 standalone application and implement targeted UX/UI dashboard enhancements, architecture robustness improvements, and Google Sheets / GAS integration capabilities.

Working directory: c:\Users\USER\Repo\qs-manager\qs-manager-v2
Integrity mode: development

## Requirements

### R1. Dashboard UX/UI Enhancements

- **Visual Design & Token System**:
  - Maintain the Google Font `Outfit` and optimize typography scaling (headers: bold, text: light/regular).
  - Modernize buttons and form controls with clear hover and active animations (e.g. subtle scale down on click, background color transitions).
  - Enhance form controls with validation states: highlight invalid fields with a red outline (`--danger`) and render a small helper error text underneath.

- **Notifications & Alert System**:
  - Implement a central toast-like notification system that supports auto-dismiss (after 5 seconds).
  - Design visual states for `success` (soft green background, dark green text), `error` (soft red background, dark red text), and `loading` (a subtle spinner or fade-in status).

- **Real-Time Booking Filtering**:
  - Add filtering controls above the bookings table:
    - Dropdown for **Service** (pre-populated with active services).
    - Dropdown for **Staff Member** (pre-populated with active team members).
    - Dropdown for **Status** (Draft, Confirmed, Cancelled, Completed).
  - Filtering must occur instantly on change of any selector without refreshing the page.

- **Table Pagination**:
  - Paginate the bookings table to display a maximum of 10 rows at a time.
  - Implement pagination controls below the table:
    - "Anterior" and "Siguiente" buttons.
    - Page number indicators (e.g., "Página 1 de 3").
    - A selector for rows-per-page (e.g., 5, 10, 20).

---

### R2. Backend Robustness & Architecture Improvements

- **Controller Integration Tests**:
  - Add PHPUnit integration tests for Slim routes under `tests/Integration`.
  - Simulate HTTP requests (GET and POST) for `ServicesController`, `TeamController`, and `BookingController` using mock request/response environments.
  - Ensure tests use a transactional rollback mechanism (or a clean database setup) so they don't pollute the production database.

- **Strict Input Validation Rules**:
  - In `ServicesController::store`:
    - `name`: Required, string, min 3 characters, max 160 characters.
    - `category`: Optional, string, max 80 characters.
    - `duration_minutes`: Optional, positive integer.
  - In `TeamController::store`:
    - `display_name`: Required, string, min 3 characters, max 160 characters.
    - `role`: Required, must be exactly one of: `admin`, `coordinadora`, `staff`.
  - In `BookingController::store`:
    - `service_id`: Optional, must reference an existing service ID.
    - `staff_id`: Optional, must reference an existing staff member ID.
    - `customer_name`: Optional, string, max 160 characters.
    - `customer_phone`: Optional, string, must match format regex `/^\+?[0-9\s\-]+$/`.
    - `scheduled_for`: Optional, parseable ISO-8601 or standard datetime format.
    - `status`: Required, must be exactly one of: `draft`, `confirmed`, `cancelled`, `completed`.
  - Any validation failure must return a JSON response with status code `422 Unprocessable Entity` containing an explicit error message.

- **Database Transactions**:
  - In `PostgresBookingRepository::save`, `PostgresServiceRepository::save`, and `PostgresStaffRepository::save`, wrap write queries in PDO transactions (`$connection->beginTransaction()`, `commit()`, and `rollBack()`) to ensure absolute data atomicity.

---

### R3. Google Sheets & GAS Integration

- **Trigger & Webapp Payload**:
  - Whenever a booking is created or updated in V2, if `GAS_WEBAPP_URL` is defined in the `.env` file, the application must perform an HTTP POST request to that URL.
  - The JSON payload sent to GAS must match the fields expected by `qs-manager-apps-script.gs`:
    ```json
    {
      "id": 123,
      "service_id": 12,
      "service_name": "Maquillaje social",
      "staff_id": 34,
      "staff_name": "Camila Villalobos",
      "customer_name": "Juan Pérez",
      "customer_phone": "+56912345678",
      "fecha": "2026-07-20",
      "hora": "14:30",
      "status": "confirmed"
    }
    ```
  - Gracefully catch connection timeouts or transfer errors during the sync. Do not crash the booking creation/update process. Instead, return a warning field in the API response.

- **Sync Endpoint**:
  - Implement `POST /api/v1/bookings/{id}/sync-gas`.
  - It retrieves the booking from the database, formats the payload, executes the HTTP POST request to `GAS_WEBAPP_URL`, and returns a JSON response indicating whether the sync was successful.

- **Dashboard UI Actions**:
  - Add a "Sincronizar GAS" button inside each row of the bookings table.
  - On click, it triggers a call to `POST /api/v1/bookings/{id}/sync-gas` and displays a toast message showing the result.
  - Show a visual indicator (e.g. sync icon color) indicating if the sync succeeded or failed.

## Acceptance Criteria

### Frontend UX & UI
- [ ] Dropdown filters (Service, Staff, Status) filter the table rows in real-time.
- [ ] Pagination controls work correctly when there are more than 10 bookings.
- [ ] Alert toasts fade in and auto-dismiss after 5 seconds.
- [ ] HTML forms show visual red borders and helper texts when invalid data is typed.
- [ ] A "Sincronizar GAS" button exists in the booking rows, calling the sync endpoint and displaying visual feedback.

### Backend & Robustness
- [ ] Integration tests exist under `tests/Integration` and test successful routes (200, 201) and failure conditions (400, 422).
- [ ] Validation constraints (e.g. invalid phone pattern, missing required name, incorrect role) consistently reject requests with `422`.
- [ ] PDO write statements in repositories are secured inside try-catch transaction structures.
- [ ] The `POST /api/v1/bookings/{id}/sync-gas` endpoint sends the exact payload layout to `GAS_WEBAPP_URL` and returns details of the HTTP response.
- [ ] All unit and integration tests compile and pass.
