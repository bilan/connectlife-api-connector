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

The app uses [Connectlife API](https://api.connectlife.io/swagger/index.html)
to control AC devices. It integrates with Home Assistant through
[MQTT](https://www.home-assistant.io/integrations/climate.mqtt/)
using [discovery feature](https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery).

Pull requests, bug reports and feature requests are welcomed. You can do it in the
[issues section](https://github.com/Bilan/connectlife-api-connector/issues).

## Install in Home Assistant with Supervisor

[![ha_badge](https://img.shields.io/badge/Home%20Assistant-Add%20On-blue.svg)](https://www.home-assistant.io/)

1. In Supervisor go to the Add-on Store.
2. In the overflow menu click "Repositories".
3. Add `https://github.com/bilan/home-assistant-addons/`.
4. Wait for Add-on to show up or click reload in the same overflow menu.
5. Install, configure, enjoy.

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

## Useful links

-   https://api.connectlife.io/swagger/index.html
-   https://developers.home-assistant.io/docs/add-ons/testing
-   https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery
-   https://www.home-assistant.io/integrations/climate.mqtt/
