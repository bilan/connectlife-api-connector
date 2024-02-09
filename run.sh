#!/usr/bin/with-contenv bashio

if ! [[ -v CONNECTLIFE_LOGIN ]]; then
    export CONNECTLIFE_LOGIN=$(bashio::config 'connectlife_login')
fi

if ! [[ -v CONNECTLIFE_PASSWORD ]]; then
    export CONNECTLIFE_PASSWORD=$(bashio::config 'connectlife_password')
fi

if ! [[ -v LOG_LEVEL ]]; then
    export LOG_LEVEL=$(bashio::config 'log_level')
fi

if ! [[ -v DISABLE_HTTP_API ]]; then
    export DISABLE_HTTP_API=$(bashio::config 'disable_http_api')
else
    export DISABLE_HTTP_API=false
fi

if ! [[ -v MQTT_HOST ]]; then
    export MQTT_HOST=$(bashio::services mqtt "host")
fi

if ! [[ -v MQTT_USER ]]; then
    export MQTT_USER=$(bashio::services mqtt "username")
fi

if ! [[ -v MQTT_PASSWORD ]]; then
    export MQTT_PASSWORD=$(bashio::services mqtt "password")
fi

if ! [[ -v MQTT_PORT ]]; then
    export MQTT_PORT=$(bashio::services mqtt "port")
fi

if ! [[ -v MQTT_SSL ]]; then
    export MQTT_SSL=$(bashio::services mqtt "ssl")
fi

php artisan app:check-config

if [ -z "$MQTT_HOST" ]; then
    echo "MQTT configuration not found, running HTTP API only."
    php artisan octane:start --server=roadrunner --host=0.0.0.0 --rpc-port=6001 --port=8000
elif [ "$DISABLE_HTTP_API" = "true" ]; then
    echo "HTTP API disabled, running MQTT client only."
    php artisan -vvv app:mqtt-loop
else
    /usr/bin/supervisord -c /home/app/docker-files/supervisord.conf
fi
