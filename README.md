# Connectlife API proxy / MQTT — Home Assistant Add-on

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

Add-on per Home Assistant che integra i condizionatori **Hisense ConnectLife** (e brand affiliati) tramite l'API mobile della [app ConnectLife](https://en.connectlife.io), esponendoli a HA via [MQTT discovery](https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery) come entità [climate](https://www.home-assistant.io/integrations/climate.mqtt/).

Nasce dall'assenza di un'integrazione ufficiale per questi dispositivi. Tutta la comunicazione passa per il cloud ConnectLife (auth via Gigya OAuth), nessun controllo locale è disponibile.

## Installazione (Home Assistant Supervisor)

[![ha_badge](https://img.shields.io/badge/Home%20Assistant-Add%20On-blue.svg)](https://www.home-assistant.io/)

1. Verifica che i tuoi dispositivi ConnectLife risultino online nell'app ufficiale.
2. In Home Assistant: **Settings → Add-ons → Add-on Store**.
3. Dal menù in alto a destra (tre puntini) seleziona **Repositories**.
4. Aggiungi `https://github.com/nerdosity/home-assistant-addons/`.
5. Aspetta che l'add-on compaia (eventualmente "Reload" dal menù tre puntini).
6. Installa l'add-on **Connectlife API proxy & MQTT Add-on**.
7. **Attiva il Watchdog**: l'API ConnectLife non è particolarmente affidabile e può andare in timeout.
8. Nella sezione **Configuration** compila le credenziali ConnectLife. Per MQTT, se lasci i campi vuoti l'add-on tenta di prendere le credenziali dall'integrazione MQTT di HA (richiede il servizio `mqtt:need` già attivo, di solito tramite l'add-on Mosquitto broker).
9. Avvia l'add-on. Dopo qualche secondo (max ~60s dal primo poll) i tuoi split appariranno come entità climate in HA, autorilevati via MQTT discovery.

## Configurazione

| Opzione | Default | Descrizione |
|---|---|---|
| `connectlife_login` | — | Email account ConnectLife |
| `connectlife_password` | — | Password account ConnectLife |
| `beeping` | `false` | Se `true`, ad ogni comando il device emette il beep di conferma |
| `log_level` | `info` | Log level del processo supervisord |
| `log_level_app` | `info` | Log level dell'applicazione Laravel |
| `disable_http_api` | `true` | Se `true`, gira solo il loop MQTT; se `false`, espone anche l'HTTP API su porta 8000 |
| `mqtt_host` | — | Host del broker MQTT (vuoto = autodiscovery dall'integrazione HA) |
| `mqtt_user` / `mqtt_password` | — | Credenziali broker MQTT |
| `mqtt_port` | `1883` | Porta broker |
| `mqtt_ssl` | `false` | TLS verso il broker |
| `devices_config` | (vedi sotto) | JSON con mapping `deviceFeatureCode → {t_work_mode, t_fan_speed, t_swing_*}` per device non riconosciuti dai default |

### `devices_config`

Se i tuoi device hanno `deviceFeatureCode` diverso da quelli autorilevati, qui puoi mappare manualmente modalità, fan speed, swing. I default coprono il caso più comune (`fan_only`/`heat`/`cool`/`dry`/`auto` + fan a 6 step + autorilevamento swing `t_up_down`). Esempio per featureCode `117`:

```json
{
  "117": {
    "t_work_mode": ["fan only", "heat", "cool", "dry", "auto"],
    "t_fan_speed": {"0": "auto", "5": "super low", "6": "low", "7": "medium", "8": "high", "9": "super high"},
    "t_swing_direction": ["straight", "right", "both sides", "swing", "left"],
    "t_swing_angle": {"0": "swing", "2": "bottom 1/6", "3": "bottom 2/6", "4": "bottom 3/6", "5": "top 4/6", "6": "top 5/6", "7": "top 6/6"}
  }
}
```

## Entità create in Home Assistant

Per ogni split online viene creato automaticamente:

- **Climate**: modalità (off/cool/heat/dry/fan_only/auto), setpoint temperatura, fan speed, swing, preset (eco/sleep/boost/silent se supportati dal device)
- **Sensor — temperatura ambiente** (`f_temp_in`)
- **Sensor — potenza istantanea** (`f_electricity`, W) — se supportato
- **Sensor — tensione** (`f_votage`, V) — se supportato
- **Sensor — energia giornaliera** (`daily_energy_kwh`) e **runtime giornaliero** (`daily_runtime_minutes`) — se supportati
- **Binary sensor — guasto** (qualsiasi `f_e_*` diverso da `0`)
- **Switch — beep** (controlla se i comandi successivi emettono il beep di conferma)
- **Select — swing** (con disponibilità legata alla modalità: nascosto in `dry`/`off`)

I device offline lato cloud vengono ignorati e ritentati al poll successivo (60s).

## Proprietà API del condizionatore

Riferimento delle proprietà ConnectLife ricevute dal cloud (campo `statusList`). Valori basati su uno split `deviceTypeCode` 009, `deviceFeatureCode` 117.

| Proprietà | Descrizione | Tipo | Esempio |
|---|---|---|---|
| `t_power` | accensione | uint | `0` off, `1` on |
| `t_temp` | setpoint temperatura | uint | `21` |
| `t_beep` | buzzer | uint | `0`/`1` |
| `t_work_mode` | modalità operativa | uint | vedi sotto |
| `t_swing_direction` | swing orizzontale | uint | vedi sotto |
| `t_swing_angle` | swing verticale | uint | vedi sotto |
| `t_up_down` | swing verticale (modello alternativo single-axis) | uint | `0` swing, `1-5` posizioni fisse |
| `t_temp_type` | unità temperatura | string | `"0"` fahrenheit, `"1"` celsius |
| `t_fan_speed` | velocità ventola | uint | vedi sotto |
| `t_fan_mute` | modalità silent | uint | `0`/`1` |
| `t_super` | boost/turbo | uint | `0`/`1` |
| `t_eco` | modalità eco | uint | `0`/`1` |
| `t_sleep` | modalità sleep | uint | `0`/`1` |
| `f_temp_in` | temperatura ambiente letta | uint | `25` |
| `f_electricity` | potenza istantanea (W) | uint | `0` |
| `f_votage` | tensione (V) | uint | `0` |

### Valori `t_work_mode` (default)
`0` fan only · `1` heat · `2` cool · `3` dry · `4` auto

### Valori `t_fan_speed` (default)
`0` auto · `5` super low · `6` low · `7` medium · `8` high · `9` super high

### Valori `t_swing_direction`
`0` straight · `1` right · `2` both sides · `3` swing · `4` left

### Valori `t_swing_angle`
`0` swing · `2`→`7` dal basso verso l'alto

## Sviluppo

Repo principale (sorgente): https://github.com/nerdosity/connectlife-api-connector

Repo di distribuzione add-on (puntato da HA): https://github.com/nerdosity/home-assistant-addons

Le modifiche al codice si fanno nel repo principale, poi vengono mirrorate sul repo addons e la versione in `config.yaml` viene bumpata per far scattare l'update lato HA.

Tutte le chiamate al cloud ConnectLife sono in [`app/Services/ConnectlifeApiService.php`](app/Services/ConnectlifeApiService.php). Il flusso auth è Gigya social login (account ConnectLife) → JWT → OAuth code → access_token, con backoff esponenziale sul rate limit Gigya (errorCode 403048). Il mapping device → MQTT discovery è in [`app/Services/AcDevice.php`](app/Services/AcDevice.php). Il loop MQTT è in [`app/Console/Commands/MqttLoop.php`](app/Console/Commands/MqttLoop.php).

## Link utili

- API Swagger ConnectLife: https://api.connectlife.io/swagger/index.html
- Sviluppo add-on HA: https://developers.home-assistant.io/docs/add-ons/testing
- MQTT discovery: https://www.home-assistant.io/integrations/mqtt/#mqtt-discovery
- MQTT climate: https://www.home-assistant.io/integrations/climate.mqtt/
