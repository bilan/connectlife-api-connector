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

The add-on utilizes the API acquired through reverse engineering from the 
[Connectlife mobile app](https://en.connectlife.io)
to control AC devices and 
integrates seamlessly with Home Assistant through
[MQTT](https://www.home-assistant.io/integrations/climate.mqtt/), leveraging its
[discovery feature](https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery).

The reason for the add-on was the lack of official support for device integration.

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
docker build . --build-arg='BUILD_FROM=alpine:3.20' -t ha-connectlife-addon
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
-e DEVICES_CONFIG='{"117":{"t_work_mode":["fan only","heat","cool","dry","auto"],"t_fan_speed":{"0":"auto","5":"super low","6":"low","7":"medium","8":"high","9":"super high"},"t_swing_direction":["straight","right","both sides","swing","left"],"t_swing_angle":{"0":"swing","2":"bottom 1\/6 ","3":"bottom 2\/6","4":"bottom 3\/6","5":"top 4\/6","6":"top 5\/6","7":"top 6\/6"}}}' \
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
-e CONNECTLIFE_LOGIN=connectlife-login-email \
-e CONNECTLIFE_PASSWORD=your-password \
-e LOG_LEVEL=info \
-e MQTT_HOST=host \
-e MQTT_USER=login  \
-e MQTT_PASSWORD=mqtt-pass  \
-e MQTT_PORT=1883 \
-e MQTT_SSL=false \
-e DEVICES_CONFIG='{"117":{"t_work_mode":["fan only","heat","cool","dry","auto"],"t_fan_speed":{"0":"auto","5":"super low","6":"low","7":"medium","8":"high","9":"super high"},"t_swing_direction":["straight","right","both sides","swing","left"],"t_swing_angle":{"0":"swing","2":"bottom 1\/6 ","3":"bottom 2\/6","4":"bottom 3\/6","5":"top 4\/6","6":"top 5\/6","7":"top 6\/6"}}}' \
ha-connectlife-addon /bin/ash -c 'php artisan app:mqtt-loop'
```

## API endpoints

- `GET /api/devices` 

    example: `curl -v http://0.0.0.0:8000/api/devices`

- `POST /api/devices/{DEVICE_ID}` 

    example: `curl -v http://0.0.0.0:8000/api/devices/pu12345 -d '{"t_temp":32}' -H "Content-Type: application/json"`

#### Air Conditioner properties

> Values for my personal split air conditioner (`deviceFeatureCode` 117, `deviceTypeCode` 009)

| Property | Description | Type | Example |
|----------|-------------|------|---------|
|   t_power | on / off | uint   | 0 - off, 1 - on |
|   t_temp  |   temperature |   uint|    21  |
|   t_beep  |   buzzer  |   uint |   0, 1    |
|   t_work_mode |  mode | uint | 3 
|   t_tms   | ?
|   t_swing_direction   |   horizontal swing
|   t_swing_angle   |  vertical swing
|   t_temp_type | temp unit |  string  | "0" - fahr, "1" - celsius
|   t_fan_speed | fan speed | uint | 0 |
|   t_fan_mute | silence mode | uint | 0, 1
|   t_super | fast mode | uint | 0,1
|   t_eco   |   eco mode | uint | 0,1


`t_work_mode`
- 0 - fan only
- 1 - heat
- 2 - cool
- 3 - dry
- 4 - auto

`t_fan_speed`
- 0 - auto
- 5 - super low
- 6 - low
- 7 - medium
- 8 - high
- 9 - super high

`t_swing_direction`
- 0 - straight
- 1 - right
- 2 - both sides
- 3 - swing
- 4 - left

`t_swing_angle`
- 0 - swing
- 2 -> 7 - from bottom to top 

## Useful links

-   https://api.connectlife.io/swagger/index.html
-   https://developers.home-assistant.io/docs/add-ons/testing
-   https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery
-   https://www.home-assistant.io/integrations/climate.mqtt/
