<p align="center">
    <h1 align="center">PortaTec</h1>
    <p align="center">Smart Property Access & Monitoring System</p>
</p>

## About PortaTec

PortaTec is a comprehensive property management system designed for short-term rental properties. It provides an intelligent solution for property owners and managers to control, monitor, and automate their properties. Built with Laravel, PortaTec offers powerful features including:

- **Smart Access Management**
  - Generate and manage temporary access codes
  - Schedule access periods for guests and service providers
  - Real-time access monitoring and logging

- **Property Monitoring**
  - Automated surveillance system integration
  - Environmental monitoring (temperature, humidity, noise levels)
  - Security alerts and notifications

- **Automation Features**
  - Smart device control (lights, thermostats, locks)
  - Automated check-in and check-out processes
  - Scheduled maintenance notifications

- **User Management**
  - Multi-level user access control
  - Guest profile management
  - Service provider coordination

## Getting Started

### Prerequisites
- PHP >= 8.1
- Composer
- MySQL or PostgreSQL
- Node.js & NPM

### Installation

1. Clone the repository
```bash
git clone https://github.com/yourusername/portatec.git
```

2. Install dependencies
```bash
composer install
npm install
```

3. Configure your environment
```bash
cp .env.example .env
php artisan key:generate
```

4. Set up your database and run migrations
```bash
php artisan migrate
```

## External Integrations

### Tuya Smart Devices

PortaTec integrates with Tuya-powered devices (locks, switches, sensors, door contacts) via the **Tuya Device Sharing** mechanism — the same used by Home Assistant since version 2024.2. **No Tuya developer account is required.**

The user authenticates by scanning a QR code with the Tuya Smart or SmartLife app. All communication goes through `apigw.iotbing.com` using a proprietary protocol (AES-128-GCM + custom signing), not the standard Tuya OpenAPI.

- **Service:** `app/Services/Tuya/TuyaQrAuthService.php`
- **Based on:** [`tuya-device-sharing-sdk`](https://github.com/tuya/tuya-device-sharing-sdk) v0.2.1 (MIT) — PHP port
- **Env vars required:** none
- **Endpoint:** `https://apigw.iotbing.com`

### How to inspect the reference SDK

The PHP implementation is a direct port of the Python SDK. If any authentication or protocol behaviour needs to change, read the original source first:

```bash
pip download tuya-device-sharing-sdk==0.2.1 --no-deps -d /tmp/tuya
cd /tmp/tuya
unzip tuya_device_sharing_sdk-0.2.1-py2.py3-none-any.whl -d sdk_source

# Key files:
# sdk_source/tuya_sharing/user.py        → QR login and polling (no auth required)
# sdk_source/tuya_sharing/customerapi.py → authenticated request protocol (AES-GCM)
# sdk_source/tuya_sharing/device.py      → device listing and command sending
# sdk_source/tuya_sharing/home.py        → home listing
```

See `AGENTS.md` for the full protocol specification, DP formats for locks, and field references.

## Documentation

Detailed documentation for setting up and using PortaTec can be found in the [Wiki](link-to-wiki).

## Security

If you discover any security vulnerabilities within PortaTec, please email us at [security@portatec.com](mailto:security@portatec.com). All security vulnerabilities will be promptly addressed.

## License

PortaTec is proprietary software. All rights reserved.

## Support

For support, please email [support@portatec.com](mailto:support@portatec.com) or visit our [support portal](link-to-support).
