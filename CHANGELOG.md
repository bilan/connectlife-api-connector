# Changelog

Tutte le modifiche significative a questo progetto sono documentate in questo file.

Il formato si basa su [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
e il progetto segue il [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.17] - 2026-05-23

### Risolto

- **Compatibilità con Home Assistant 2025.5**: rimosso `'none'` da `preset_modes` nel payload MQTT climate discovery. HA 2025.5 ha stretto la validazione dello schema e rigetta i discovery che contengono `'none'` come preset mode (è un valore riservato per "nessun preset attivo"), facendo sparire silenziosamente le entità climate dopo l'upgrade
- Rimossi `power_command_topic`, `payload_on`, `payload_off` deprecati dal discovery climate. Lo spegnimento è già gestito da `mode_command_topic` con la modalità `"off"`

### Rimosso

- Opzione di configurazione `temperature_unit`: era esportata come env var ma mai letta dal codice PHP. L'unità di temperatura viene letta direttamente dal device (`t_temp_type` nello `statusList`)

## [2.3.7] - 2026-04-17

### Risolto

- Sincronizzazione di `t_fan_speed_s` (fan speed stepless) con `t_fan_speed` nei comandi fan

## [2.3.6] - 2026-04-17

### Risolto

- Comandi temperatura ora inviati correttamente anche in modalità `dry` (controlla l'intensità di deumidificazione)
- Solo `fan_only` ignora il setpoint di temperatura

## [2.3.5] - 2026-04-17

### Aggiunto

- Discovery MQTT di sensori per potenza istantanea (`f_electricity`, W), tensione (`f_votage`, V) ed energia giornaliera (`daily_energy_kwh`, kWh)
- Sensori autorilevati dallo `statusList` del device e legati allo stesso dispositivo HA dell'entità climate

## [2.3.4] - 2026-04-17

### Risolto

- Backoff esponenziale sul rate limit Gigya: 5 → 10 → 20 → 40 → 60 min tra i retry
- Il counter di backoff si resetta al primo login riuscito

## [2.3.3] - 2026-04-17

### Risolto

- Stop al retry del login Gigya ogni 60s in caso di rate limit (errorCode 403048): attesa di 5 minuti prima del retry successivo

## [2.3.2] - 2026-04-17

### Aggiunto

- Preset modes: `eco`, `sleep`, `boost`, `silent` — autorilevati dallo `statusList` del device (`t_eco`, `t_sleep`, `t_super`, `t_fan_mute`)
- Supporto swing verticale tramite `t_up_down` — autorilevato dallo statusList quando non è fornita una configurazione swing esplicita
- Topic MQTT dei preset pubblicati con `retain=true`
- Discovery tardivo: i device che vanno online dopo lo startup vengono automaticamente subscribed e annunciati a HA
- Tutte le pubblicazioni MQTT di stato ora usano `retain=true` così i nuovi subscriber vedono subito lo stato corrente

### Risolto

- **Command status mutex (errorCode 16)**: ogni comando MQTT manda ora solo la proprietà modificata invece dello stato completo del device (es. cambio modalità manda solo `t_power`+`t_work_mode`)
- Accensione da off: manda prima `t_power=1` da solo, attende 3s, poi invia il comando di modalità/proprietà — evita il mutex sull'inizializzazione del device
- Robustezza allo startup: il container sopravvive a fallimenti dell'API al boot, i device vengono caricati al poll successivo
- Retry su mutex: retry automatico dopo 2s se il primo tentativo restituisce errorCode 16
- Cache Laravel persistita su `/data` (storage persistente HA) così l'access token Gigya sopravvive ai riavvii dell'addon
- Gestione exception più ampia nel loop MQTT (`\Exception` invece del solo `TransferException`)
- Sincronizzazione di `t_fan_speed_s` con `t_fan_speed` al cambio fan speed
