<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;

class ConnectlifeApiService
{
    public function __construct(private Client $httpClient)
    {
        $this->httpClient = new Client([
            RequestOptions::TIMEOUT => 30,
            RequestOptions::DEBUG => (env('LOG_LEVEL') === 'debug'),
            RequestOptions::HEADERS => ['User-Agent' => $this->getUserAgent()]
        ]);
    }

    private function getUserAgent()
    {
        preg_match('/(version:\s)(.*)/', Storage::get('config.yaml'), $match);
        $version = $match[2] ?? 'unknown';

        return 'connectlife-api-connector ' . $version;
    }

    public function updateDevice(string $deviceId, array $properties)
    {
        $data = [
            'puid' => $deviceId,
            'properties' => $properties
        ];

        Log::info('ConnectLife: updating device.', $data);

        $result = $this->decodeJsonResponse(
            $this->httpClient->request('POST', 'https://connectlife.bapi.ovh/appliances', [
                RequestOptions::HEADERS => ['X-Token' => $this->getAccessToken()],
                RequestOptions::JSON => $data
            ])
        );

        Log::info('ConnectLife: updating device result.', $result);

        return $result;
    }

    private function decodeJsonResponse(ResponseInterface $response)
    {
        $data = $response->getBody()->getContents();

        Log::info('Response', [$data]);

        return json_decode($data, true);
    }

    private function getAccessToken(): string
    {
        return Cache::remember('accessToken', 60 * 60 * 24, function () {
            Log::info('Getting access token.');

            $apiKey = '4_yhTWQmHFpZkQZDSV1uV-_A';
            $gmid = 'gmid.ver4.AtLt3mZAMA.C8m5VqSTEQDrTRrkYYDgOaJWcyQ-XHow5nzQSXJF3EO3TnqTJ8tKUmQaaQ6z8p0s.zcTbHe6Ax6lHfvTN7JUj7VgO4x8Vl-vk1u0kZcrkKmKWw8K9r0shyut_at5Q0ri6zTewnAv2g1Dc8dauuyd-Sw.sc3';
            $clientId = "5065059336212";

            $response = $this->decodeJsonResponse(
                $this->httpClient->request('POST', 'https://accounts.eu1.gigya.com/accounts.login', [
                    RequestOptions::FORM_PARAMS => [
                        'loginID' => env('CONNECTLIFE_LOGIN'),
                        'password' => env('CONNECTLIFE_PASSWORD'),
                        'APIKey' => $apiKey,
                        'gmid' => $gmid,
                    ]
                ])
            );

            $token = $response['sessionInfo']['cookieValue'] ?? null;

            if (!$token) {
                throw new \Exception('Cannot login to Connectlife. Response: ' . json_encode($response->body()));
            }

            $uid = $response['UID'];

            $response = $this->decodeJsonResponse(
                $this->httpClient->request('POST', 'https://accounts.eu1.gigya.com/accounts.getJWT', [
                    RequestOptions::FORM_PARAMS => [
                        'APIKey' => $apiKey,
                        'gmid' => $gmid,
                        'login_token' => $token
                    ]
                ])
            );

            $response = $this->decodeJsonResponse(
                $this->httpClient->request('POST', 'https://oauth.hijuconn.com/oauth/authorize', [
                    RequestOptions::JSON => [
                        'client_id' => $clientId,
                        'idToken' => $response['id_token'],
                        'response_type' => 'code',
                        'redirect_uri' => 'https://api.connectlife.io/swagger/oauth2-redirect.html',
                        'thirdType' => 'CDC',
                        'thirdClientId' => $uid,
                    ]
                ])
            );

            $response = $this->decodeJsonResponse(
                $this->httpClient->request('POST', 'https://oauth.hijuconn.com/oauth/token', [
                    RequestOptions::FORM_PARAMS => [
                        'client_id' => $clientId,
                        'code' => $response['code'],
                        'grant_type' => 'authorization_code',
                        'client_secret' => '07swfKgvJhC3ydOUS9YV_SwVz0i4LKqlOLGNUukYHVMsJRF1b-iWeUGcNlXyYCeK',
                        'redirect_uri' => 'https://api.connectlife.io/swagger/oauth2-redirect.html',
                    ]
                ])
            );

            return $response['access_token'];
        });
    }

    /**
     * @return array<AcDevice>
     */
    public function getOnlineAcDevices(): array
    {
        $acDevices = [];
        foreach ($this->devices() as $device) {
            $id = $device['puid'];
            if ($device['offlineState'] === 0) {
                Log::info("Skipping offline device: $id", $device);
                continue;
            }
            $acDevices[] = new AcDevice($device, $this->getDeviceConfiguration($device['deviceTypeCode']));
        }

        return $acDevices;
    }

    public function devices(?string $deviceId = null)
    {
        $devicesData = $this->decodeJsonResponse(
            $this->httpClient->get('https://connectlife.bapi.ovh/appliances', [
                RequestOptions::HEADERS => ['X-Token' => $this->getAccessToken()]
            ])
        );

        Log::debug('Devices status.', $devicesData);

        if (null === $deviceId) {
            return $devicesData;
        }

        foreach ($devicesData as $device) {
            if ($device['deviceId'] === $deviceId) {
                return $device;
            }
        }

        return [];
    }

    private function getDeviceConfiguration(string $deviceTypeCode): array
    {
        $configuration = json_decode(env('DEVICES_CONFIG', '[]'), true);

        if (isset($configuration[$deviceTypeCode])) {
            return $configuration[$deviceTypeCode];
        }

        Log::debug('Device configuration not found, using default.');

        $defaultConfiguration = '{"t_work_mode":["fan only","heat","cool","dry","auto"],"t_fan_speed":{"0":"auto","5":"super low","6":"low","7":"medium","8":"high","9":"super high"},"t_swing_direction":["straight","right","both sides","swing","left"],"t_swing_angle":{"0":"swing","2":"bottom 1\/6 ","3":"bottom 2\/6","4":"bottom 3\/6","5":"top 4\/6","6":"top 5\/6","7":"top 6\/6"}}';

        return json_decode($defaultConfiguration, true);
    }
}
