# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2023-02-25

### Added

### Fixed

- Crash when swing is not supported.
- "power" on/off command.
- Using "deviceFeatureCode" instead of "deviceFeatureCode" to differentiate AC devices model.

## [2.0.0] - 2023-02-24

### Added

-   BEEPING env - option to silence buzzer
-   DEVICES_CONFIG env - stores configuration for your devices (modes, swing and fan options)
-   swing modes support

### Changed

-   Due to the deactivation of the api.connectlife.io endpoints, I decompiled the Connectlife mobile app and based on this I prepared a new version.

### Removed

-   Some HTTP API endpoints.

## [1.1.0] - 2023-02-16
