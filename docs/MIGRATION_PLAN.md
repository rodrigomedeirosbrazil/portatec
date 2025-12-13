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

### Changes:
- **Schema Updates**:
    - Add `place_id` (Foreign Key to `places`, nullable for unassigned devices).
    - Add `type` (Enum/String: 'portatec', 'tuya').
    - Add `default_pin` (String, 6 chars, nullable).
    - Add `is_online` (Boolean, default false) - helpful for status tracking.
- **Migration Strategy**:
    - If `PlaceDeviceFunction` is currently used to link Places and Devices, we might need a script to populate `device.place_id` based on existing relationships.
- **Tuya Integration**:
    - Add fields necessary for Tuya (e.g., `tuya_device_id`, `tuya_local_key` if needed, though plan says API based).

## 3. Platforms

New entity to manage external booking platforms (Airbnb, Booking.com).

### Implementation:
- **Model**: `Platform`
- **Table**: `platforms`
- **Fields**:
    - `id`
    - `user_id` (Owner of the integration)
    - `name` (e.g., "My Airbnb Listing")
    - `type` (Enum: 'airbnb', 'booking_com', 'other')
    - `ical_url` (URL for calendar synchronization)
    - `refresh_rate` (Integer, minutes - optional)
- **Relationships**:
    - BelongsTo `User`
    - HasMany `Bookings`

## 4. Bookings

New entity to manage reservations and trigger AccessCode generation.

### Implementation:
- **Model**: `Booking`
- **Table**: `bookings`
- **Fields**:
    - `id`
    - `place_id` (FK to `places`)
    - `platform_id` (FK to `platforms`, nullable for manual bookings)
    - `external_id` (String, unique per platform - for iCal UID)
    - `guest_name` (String)
    - `check_in` (DateTime)
    - `check_out` (DateTime)
    - `status` (Enum: 'confirmed', 'cancelled')
- **Relationships**:
    - BelongsTo `Place`
    - BelongsTo `Platform`
    - HasOne `AccessCode`
- **Logic**:
    - **Observer**: On `Booking` creation/update (if confirmed), generate or update an `AccessCode` for the `check_in` - `check_out` period.

## 5. Synchronization Logic (Overview)

- **Device Sync**:
    - Implement endpoint/websocket event `GET /api/device/sync` or `ws:sync`.
    - Returns: List of valid `AccessCodes` for the Device's `Place`.
- **iCal Sync**:
    - Scheduled Job (`SyncPlatformsJob`) to fetch `ical_url`, parse events, and create/update `Bookings`.

## 6. Execution Order

1.  **Create Bookings & Platforms**:
    - Create migrations for `platforms` and `bookings`.
    - Create Models.
2.  **Refactor AccessCode**:
    - Create migration to rename table and add columns.
    - Rename Model file and update references.
3.  **Update Device**:
    - Add migration for `place_id`, `type`, `default_pin`.
4.  **Implement Logic**:
    - `BookingObserver` for AccessCode generation.
    - `PlatformService` for iCal parsing.

