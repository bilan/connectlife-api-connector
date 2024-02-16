# Connectlife API proxy / MQTT Home Assistant integration

[aarch64-shield]: https://img.shields.io/badge/aarch64-yes-green.svg
[amd64-shield]: https://img.shields.io/badge/amd64-yes-green.svg
[armv6-shield]: https://img.shields.io/badge/armv6-yes-green.svg
[armv7-shield]: https://img.shields.io/badge/armv7-yes-green.svg
[i386-shield]: https://img.shields.io/badge/i386-yes-green.svg
![aarch64-shield]
![amd64-shield]
![armv6-shield]
![armv7-shield]
![i386-shield]

The add-on utilizes the [Connectlife API](https://api.connectlife.io/swagger/index.html) to control AC devices and 
integrates seamlessly with Home Assistant through
[MQTT](https://www.home-assistant.io/integrations/climate.mqtt/), leveraging its
[discovery feature](https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery).

I welcome pull requests, bug reports, and feature requests. Please feel free to submit them in the
[issues section](https://github.com/Bilan/connectlife-api-connector/issues).

## Install in Home Assistant with Supervisor

[![ha_badge](https://img.shields.io/badge/Home%20Assistant-Add%20On-blue.svg)](https://www.home-assistant.io/)

1. Make sure your Connectlife appliances are online.
2. In Supervisor, navidate to the Add-on Store.
3. From the overflow menu, select "Repositories".
4. Add `https://github.com/bilan/home-assistant-addons/`.
5. Wait for Add-on to appear or click "Reload" in the same overflow menu.
6. Install / build thhe add-on.
7. Turn on the add-on watchdog - Connectlife API is not stable and sometimes times out.
8. In the Configuration section, fill in the necessary fields. If you leave the fields blank,
the add-on will attempt to fetch MQTT credentials from the Supervisor API.

## Run as independent docker container without HA/Supervisor

#### Build image
```bash
docker build . --build-arg='BUILD_FROM=alpine:3.19' -t ha-connectlife-addon
```

#### Run HTTP API and MQTT client both
```bash
docker run -it \
-p 8000:8000 \
-e CONNECTLIFE_LOGIN=connectlife-login-email \
-e CONNECTLIFE_PASSWORD=your-password \
-e LOG_LEVEL=info \
-e MQTT_HOST=host \
-e MQTT_USER=login  \
-e MQTT_PASSWORD=mqtt-pass  \
-e MQTT_PORT=1883 \
-e MQTT_SSL=false \
ha-connectlife-addon /bin/ash -c '/usr/bin/supervisord -c /home/app/docker-files/supervisord.conf'
```

#### HTTP API only
```bash
docker run -it \
-p 8000:8000 \
-e CONNECTLIFE_LOGIN=connectlife-login-email \
-e CONNECTLIFE_PASSWORD=your-password \
-e LOG_LEVEL=info \
ha-connectlife-addon /bin/ash -c 'php artisan serve --port=8000 --host=0.0.0.0'
```

#### MQTT client only
```bash
docker run -it \
-p 8000:8000 \
-e CONNECTLIFE_LOGIN=connectlife-login-email \
-e CONNECTLIFE_PASSWORD=your-password \
-e LOG_LEVEL=info \
-e MQTT_HOST=host \
-e MQTT_USER=login  \
-e MQTT_PASSWORD=mqtt-pass  \
-e MQTT_PORT=1883 \
-e MQTT_SSL=false \
ha-connectlife-addon /bin/ash -c 'php artisan app:mqtt-loop'
```

## API endpoints

- `GET /api/devices-list`
- `GET /api/devices/`
- `GET /api/devices/{DEVICE_ID}`
- `POST /api/devices/{DEVICE_ID}` with example JSON data `{"Power":"1","TemperatureUnit":"0","SetTemperature":"31","Mode":"1","FanSpeed":"0"}`

## Useful links

-   https://api.connectlife.io/swagger/index.html
-   https://developers.home-assistant.io/docs/add-ons/testing
-   https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery
-   https://www.home-assistant.io/integrations/climate.mqtt/
