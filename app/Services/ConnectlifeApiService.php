<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use Carbon\Carbon;

class ConnectlifeApiService
{
    private const BASE_URL = 'https://clife-eu-gateway.hijuconn.com';

    public function __construct(private Client $httpClient)
    {
        $this->httpClient = new Client([
            RequestOptions::TIMEOUT => 30,
            RequestOptions::DEBUG => (env('LOG_LEVEL') === 'debug'),
            RequestOptions::HEADERS => ['User-Agent' => 'Runner/2.0.6 (iPhone; iOS 17.2.1; Scale/3.00)']
        ]);
    }

    public function updateDevice(string $deviceId, array $properties)
    {
        $data = [
            'puid' => $deviceId,
            'properties' => $properties
        ];

        Log::info('ConnectLife: updating device.', $data);

        $requestData = $this->getCommonRequestData() + $data + ['accessToken' => $this->getAccessToken()];

        $result = $this->decodeJsonResponse(
            $this->httpClient->request('POST', self::BASE_URL . '/device/pu/property/set', [
                RequestOptions::JSON => $requestData + ['sign' => $this->getSignature($requestData)]
            ])
        )['response'];
        
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
                throw new \Exception('Cannot login to Connectlife. Response: ' . json_encode($response));
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

            if (!in_array($device['deviceTypeCode'], ['009', '006', '008'], true)) {
                Log::info("Skipping device with unknown type code: $id", $device);
                continue;
            }
            $acDevices[] = new AcDevice($device);
        }

        return $acDevices;
    }
    public function devices(?string $deviceId = null): array
    {
        $devicesData = $this->devicesData($this->getAccessToken());

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

    public function devicesData(string $token): array
    {
        return Cache::remember($token, 5, function () use ($token) {
            $requestData = $this->getCommonRequestData() + ['accessToken' => $token];

            $devices = $this->decodeJsonResponse(
                $this->httpClient->get(self::BASE_URL . '/clife-svc/pu/get_device_status_list', [
                    RequestOptions::QUERY => $requestData + ['sign' => $this->getSignature($requestData)]
                ])
            )['response']['deviceList'];

            foreach ($devices as $k => $v) {
                try {
                    $energy = $this->deviceEnergy($v['puid'], $token);
                    $devices[$k]['statusList']['daily_energy_kwh'] = $energy['resultData']['electricTotal'];
                } catch (\Exception $e) {
                    Log::debug('Unable to fetch device energy', [$e->getMessage()]);
                }
            }

            return $devices;
        });
    }

    public function deviceEnergy(string $deviceId, string $token)
    {
        return Cache::remember($deviceId, 60*10, function () use ($token, $deviceId) {
            $today = Carbon::now()->toDateString();
            $requestData = $this->getCommonRequestData() + [
                    'accessToken' => $token,
                    'puid' => $deviceId,
                    'statType' => 'day',
                    'dateEnd' => $today,
                    'dateStart' => $today,
                    'curve' => '1',
                    'deviceType' => '009',
                    'featureCode' => '117'
                ];

            return $this->decodeJsonResponse(
                $this->httpClient->request('POST', self::BASE_URL . '/clife-svc/pu/air_duct_energy', [
                    RequestOptions::JSON => $requestData + ['sign' => $this->getSignature($requestData)]
                ])
            )['response'];
        });
    }


    private function getSignature(array $data): string
    {
        ksort($data);
        $toHash = '';
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = json_encode($v);
            }
            $toHash .= '&' . $k . '=' . $data[$k];
        }

        $toHash = substr($toHash, 1) . 'D9519A4B756946F081B7BB5B5E8D1197';
        $toEncrypt = hash('sha256', $toHash, true);
        openssl_public_encrypt($toEncrypt, $encrypted, Storage::get('pubkey.pem'));

        return base64_encode($encrypted);
    }

    private function getCommonRequestData(): array
    {
        $timestamp = date_create()->format('Uv');
        return [
            'appId' => '47110565134383',
            'appSecret' => 'yOzhz6junYno-nmULM3Wr7PU_dpSZN22ZdluvVWZ4uW5ZwwG8fIGCHTbrhcnU-iv',
            "languageId" => "12",
            "randStr" => md5($timestamp),
            "timeStamp" => $timestamp,
            "timezone" => "1.0",
            "version" => "5.0"
        ];
    }
}
