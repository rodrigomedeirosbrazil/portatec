/**
 * Tipos que espelham os API Resources em app/Http/Resources/. Aditivos ao
 * index.ts existente — reexportados a partir dele.
 */

export interface Place {
    id: number;
    name: string;
    devices_count?: number;
    bookings_count?: number;
    access_codes_count?: number;
    devices?: Device[];
    bookings?: Booking[];
    access_codes?: AccessCode[];
    place_users?: PlaceUser[];
    created_at: string | null;
    updated_at: string | null;
}

export type DeviceBrand = 'portatec' | 'tuya';

export interface Device {
    id: number;
    name: string;
    external_device_id: string | null;
    place_id: number | null;
    integration_id: number | null;
    brand: DeviceBrand | null;
    default_pin: string | null;
    last_sync: string | null;
    wifi_strength: number | null;
    firmware_version: string | null;
    tuya_category: string | null;
    tuya_product_id: string | null;
    tuya_product_name: string | null;
    tuya_icon: string | null;
    tuya_online: boolean | null;
    is_available: boolean;
    is_tuya_lock?: boolean;
    supports_tuya_temporary_password?: boolean;
    device_functions_count?: number;
    device_functions?: DeviceFunction[];
    places?: Place[];
    place?: Place | null;
    integration?: Integration | null;
    created_at: string | null;
    updated_at: string | null;
}

export type DeviceType = 'switch' | 'sensor' | 'button';

export interface DeviceFunction {
    id: number;
    device_id: number;
    type: DeviceType | null;
    type_label: string | null;
    pin: string;
    status: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface CommandLog {
    id: number;
    command_id: string;
    user_id: number | null;
    place_id: number;
    device_function_id: number;
    command_type: string;
    command_payload: unknown;
    device_function_type: string | null;
    acknowledged_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export interface Platform {
    id: number;
    name: string;
    slug: string;
}

export interface IntegrationPlace {
    id: number;
    name: string;
    external_id: string;
}

export interface Integration {
    id: number;
    platform?: {
        id: number;
        name: string;
        slug: string;
    };
    tuya_uid: string | null;
    tuya_token_expires_at: string | null;
    places?: IntegrationPlace[];
    created_at: string | null;
    updated_at: string | null;
}

export interface AccessCodeDeviceSync {
    id: number;
    access_code_id: number;
    device_id: number;
    provider: string;
    external_reference: string | null;
    synced_start: string | null;
    synced_end: string | null;
    synced_pin: string | null;
    last_synced_at: string | null;
    status: string;
    error_message: string | null;
    created_at: string | null;
    updated_at: string | null;
}

export type PlaceRole = 'admin' | 'host';

export interface PlaceUser {
    id: number;
    place_id: number;
    user_id: number;
    role: PlaceRole;
    label: string | null;
    user?: {
        id: number;
        name: string;
        email: string;
    };
}

export interface Booking {
    id: number;
    place_id: number;
    integration_id: number | null;
    guest_name: string | null;
    check_in: string | null;
    check_out: string | null;
    source: string | null;
    external_id: string | null;
    deletion_reason: string | null;
    place?: Place;
    access_code?: AccessCode;
    created_at: string | null;
    updated_at: string | null;
}

export interface AccessCode {
    id: number;
    place_id: number;
    user_id: number | null;
    booking_id: number | null;
    pin: string;
    start: string | null;
    end: string | null;
    display_name: string;
    is_valid: boolean;
    created_at: string | null;
    updated_at: string | null;
}
