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
  - Tuya Smart integration (OAuth, device sync, commands, webhooks)
  - Automated check-in and check-out processes
  - Scheduled maintenance notifications

- **Integrations**
  - **Tuya Smart**: OAuth 2.0 per property (place); tokens stored in `tuya_credentials`. Device sync via API (getDevices) creates/updates devices with brand Tuya. Commands (switch, pulse) and temporary PINs on Tuya locks via `TuyaService` and `DeviceCommandService`. Webhook at POST `/webhooks/tuya` for status notifications; job updates device `last_sync` and emits status events.

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

## Documentation

Detailed documentation for setting up and using PortaTec can be found in the [Wiki](link-to-wiki).

## Security

If you discover any security vulnerabilities within PortaTec, please email us at [security@portatec.com](mailto:security@portatec.com). All security vulnerabilities will be promptly addressed.

## License

PortaTec is proprietary software. All rights reserved.

## Support

For support, please email [support@portatec.com](mailto:support@portatec.com) or visit our [support portal](link-to-support).
