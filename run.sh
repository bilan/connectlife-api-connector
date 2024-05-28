#!/usr/bin/with-contenv bashio

if ! [[ -v CONNECTLIFE_LOGIN ]]; then
    CONNECTLIFE_LOGIN=$(bashio::config 'connectlife_login')
    export CONNECTLIFE_LOGIN
fi

if ! [[ -v CONNECTLIFE_PASSWORD ]]; then
    CONNECTLIFE_PASSWORD=$(bashio::config 'connectlife_password')
    export CONNECTLIFE_PASSWORD
fi

if ! [[ -v TEMPERATURE_UNIT ]]; then
    TEMPERATURE_UNIT=$(bashio::config 'temperature_unit')
    export TEMPERATURE_UNIT
fi

if ! [[ -v LOG_LEVEL_APP ]]; then
    LOG_LEVEL_APP=$(bashio::config 'log_level_app')
    export LOG_LEVEL_APP
fi

if ! [[ -v DISABLE_HTTP_API ]]; then
    DISABLE_HTTP_API=$(bashio::config 'disable_http_api')
    export DISABLE_HTTP_API
else
    DISABLE_HTTP_API=false
    export DISABLE_HTTP_API
fi

if ! [[ -v BEEPING ]]; then
    BEEPING=$(bashio::config "beeping")
    export BEEPING
fi

if ! [[ -v DEVICES_CONFIG ]]; then
    DEVICES_CONFIG=$(bashio::config "devices_config")
    export DEVICES_CONFIG
fi

# mqtt config

if ! [[ -v MQTT_HOST ]]; then
    MQTT_HOST=$(bashio::config "mqtt_host")
    export MQTT_HOST
fi

if ! [[ -v MQTT_USER ]]; then
    MQTT_USER=$(bashio::config "mqtt_user")
    export MQTT_USER
fi

if ! [[ -v MQTT_PASSWORD ]]; then
    MQTT_PASSWORD=$(bashio::config "mqtt_password")
    export MQTT_PASSWORD
fi

if ! [[ -v MQTT_PORT ]]; then
    MQTT_PORT=$(bashio::config "mqtt_port")
    export MQTT_PORT
fi

if ! [[ -v MQTT_SSL ]]; then
    MQTT_SSL=$(bashio::config "mqtt_ssl")
    export MQTT_SSL
fi

# Try to get mqtt config from ha if config empty
if [ -z "$MQTT_HOST" ]; then
    MQTT_HOST=$(bashio::services mqtt "host")
    MQTT_USER=$(bashio::services mqtt "username")
    MQTT_PASSWORD=$(bashio::services mqtt "password")
    MQTT_PORT=$(bashio::services mqtt "port")
    MQTT_SSL=$(bashio::services mqtt "ssl")

    export MQTT_HOST
    export MQTT_USER
    export MQTT_PASSWORD
    export MQTT_PORT
    export MQTT_SSL
fi

php artisan app:check-config

if [ -z "$MQTT_HOST" ]; then
    echo "MQTT configuration not found, running HTTP API only."
    /usr/bin/supervisord -c /home/app/docker-files/supervisord/webapi.conf
elif [ "$DISABLE_HTTP_API" = "true" ]; then
    echo "HTTP API disabled, running MQTT client only."
    /usr/bin/supervisord -c /home/app/docker-files/supervisord/mqtt.conf
else
    /usr/bin/supervisord -c /home/app/docker-files/supervisord/all.conf
fi
