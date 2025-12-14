# PORTATEC - Migration Plan

This document outlines the steps required to migrate the current codebase to the architecture defined in `NEW_PORTATEC_PLAN.md`.

## 1. AccessCodes (Refactoring AccessPin)

The existing `AccessPin` entity is close to the requirements but needs adjustment to become `AccessCode`.

### Changes:
- **Rename Table**: `access_pins` -> `access_codes`
- **Rename Model**: `AccessPin` -> `AccessCode`
- **Schema Updates**:
    - Add `booking_id` (Nullable, Foreign Key to `bookings`).
    - Ensure `place_id` and `user_id` (nullable) are present.
    - Fields `pin`, `start`, `end` should be preserved.
- **Logic**:
    - Ensure unique constraints (e.g., PIN unique per Place per valid period, if applicable).

## 2. Devices

The `Device` entity needs to be explicitly associated with a `Place` and support new types.

### Important Notes:
- **Device vs DeviceFunction**: A `Device` can have **multiple** `DeviceFunction` (e.g., Button AND Sensor at the same time). The functional type (Button/Sensor) is defined in `DeviceFunction.type`, not in `Device`.
- **External Device ID**: Rename `chip_id` to `external_device_id` for better clarity.

### Changes:
- **Schema Updates**:
    - Rename `chip_id` → `external_device_id`.
    - Add `place_id` (Foreign Key to `places`, nullable for unassigned devices).
    - Add `brand` (Enum/String: 'portatec', 'tuya').
    - Add `default_pin` (String, 6 chars, nullable).
    - **Note**: `external_device_id` is used for both Portatec and Tuya devices. No separate `tuya_device_id` field needed.
    - **Do NOT add `functional_type`** - this is already in `DeviceFunction.type`.
- **Migration Strategy**:
    - If `PlaceDeviceFunction` is currently used to link Places and Devices, we might need a script to populate `device.place_id` based on existing relationships.
- **DeviceFunction**:
    - `DeviceFunction` already exists and is correct.
    - Each `DeviceFunction` has its own `type` (Button/Sensor) and `pin`.
    - A `Device` can have multiple `DeviceFunction` records.

## 3. Platforms

New entity to manage external booking platforms (Airbnb, Booking.com).

### Implementation:
- **Model**: `Platform`
- **Table**: `platforms`
- **Fields**:
    - `id`
    - `name` (e.g., "Airbnb", "Booking.com")
    - `slug` (unique identifier, e.g., "airbnb", "booking_com")
- **Relationships**:
    - HasMany `Integrations`

**Note**: Platforms are system-wide entities. User-specific integrations are managed through the `Integration` entity.

## 3.1. Integrations

New entity to manage user-specific integrations with platforms.

### Implementation:
- **Model**: `Integration`
- **Table**: `integrations`
- **Fields**:
    - `id`
    - `platform_id` (FK to `platforms`)
    - `user_id` (FK to `users`)
    - `timestamps`
    - `soft_deletes`
- **Relationships**:
    - BelongsTo `Platform`
    - BelongsTo `User`
    - BelongsToMany `Places` (via `place_integration` pivot table)
    - HasMany `Bookings`

## 3.2. Place Integration (Pivot Table)

Relationship table between Places and Integrations, storing the external identifier.

### Implementation:
- **Table**: `place_integration`
- **Fields**:
    - `id`
    - `place_id` (FK to `places`)
    - `integration_id` (FK to `integrations`)
    - `external_id` (String - URL completa do iCal ou ID da API)
    - `timestamps`
- **Relationships**:
    - BelongsTo `Place`
    - BelongsTo `Integration`

**Note**: Each iCal receives a calendar for a specific place/property. For API integrations (future), we'll receive an ID instead of a URL. The `external_id` is stored in this pivot table because it's specific to the Place-Integration relationship.

## 4. Bookings

New entity to manage reservations and trigger AccessCode generation.

### Implementation:
- **Model**: `Booking`
- **Table**: `bookings`
- **Fields**:
    - `id`
    - `place_id` (FK to `places`)
    - `integration_id` (FK to `integrations`, nullable for manual bookings)
    - `guest_name` (String, nullable)
    - `check_in` (DateTime)
    - `check_out` (DateTime)
- **Relationships**:
    - BelongsTo `Place`
    - BelongsTo `Integration` (not Platform directly)
    - HasOne `AccessCode`
- **Logic**:
    - **Observer**: On `Booking` creation/update (if confirmed), generate or update an `AccessCode` for the `check_in` - `check_out` period.

## 5. Synchronization Logic (Overview)

- **Device Sync**:
    - Implement endpoint/websocket event `GET /api/device/sync` or `ws:sync`.
    - Returns: List of valid `AccessCodes` for the Device's `Place`.
- **iCal Sync**:
    - Scheduled Job (`SyncIntegrationsJob`) to fetch iCal from `place_integration.external_id` for each Place-Integration relationship, parse events, and create/update `Bookings`.

## 6. Execution Order

1.  **Create Platforms, Integrations & Bookings**:
    - Create migrations for `platforms`, `integrations`, and `bookings`.
    - Create Models.
2.  **Refactor AccessCode**:
    - Create migration to rename table and add columns.
    - Rename Model file and update references.
3.  **Update Device**:
    - Add migration for `place_id`, `type`, `default_pin`.
4.  **Implement Logic**:
    - `BookingObserver` for AccessCode generation.
    - `IntegrationService` (ou `ICalSyncService`) for iCal parsing.

