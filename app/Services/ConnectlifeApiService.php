<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConnectlifeApiService
{
    public function updateDevice(string $deviceId, array $properties)
    {
        $data = [[
            'id' => $deviceId,
            'properties' => $properties
        ]];

        Log::info('ConnectLife: updating device.', $data);

        $result = Http::throw()
            ->withToken($this->getAccessToken())
            ->post('https://api.connectlife.io/api/v1/appliance', $data)
            ->json()[0];

        Log::info('ConnectLife: updating device result.', $result);

        return $result;
    }

    private function getAccessToken(): string
    {
        return Cache::remember('accessToken', 60 * 60 * 24, function () {
            Log::info('Getting access token.');

            $apiKey = '4_yhTWQmHFpZkQZDSV1uV-_A';
            $gmid = 'gmid.ver4.AtLt3mZAMA.C8m5VqSTEQDrTRrkYYDgOaJWcyQ-XHow5nzQSXJF3EO3TnqTJ8tKUmQaaQ6z8p0s.zcTbHe6Ax6lHfvTN7JUj7VgO4x8Vl-vk1u0kZcrkKmKWw8K9r0shyut_at5Q0ri6zTewnAv2g1Dc8dauuyd-Sw.sc3';
            $clientId = "5065059336212";

            $response = Http::throw()->asForm()->post('https://accounts.eu1.gigya.com/accounts.login', [
                'loginID' => env('CONNECTLIFE_LOGIN'),
                'password' => env('CONNECTLIFE_PASSWORD'),
                'APIKey' => $apiKey,
                'gmid' => $gmid,
            ]);

            $token = $response['sessionInfo']['cookieValue'] ?? null;

            if (!$token) {
                throw new \Exception('Cannot login to Connectlife. Response: ' . json_encode($response->body()));
            }

            $response = Http::throw()->asForm()->post('https://accounts.eu1.gigya.com/accounts.getJWT', [
                'APIKey' => $apiKey,
                'gmid' => $gmid,
                'login_token' => $token
            ]);

            $response = Http::throw()->post('https://oauth.hijuconn.com/oauth/authorize', [
                'client_id' => $clientId,
                'idToken' => $response['id_token'],
                'response_type' => 'code',
                'redirect_uri' => 'https://api.connectlife.io/swagger/oauth2-redirect.html',
                'thirdType' => 'CDC',
                'thirdClientId' => '06bd3a32a37d49bba4e7589c77bf4fe4',
            ]);

            $response = Http::throw()->asForm()->post('https://oauth.hijuconn.com/oauth/token', [
                'client_id' => $clientId,
                'code' => $response['code'],
                'grant_type' => 'authorization_code',
                'client_secret' => '07swfKgvJhC3ydOUS9YV_SwVz0i4LKqlOLGNUukYHVMsJRF1b-iWeUGcNlXyYCeK',
                'redirect_uri' => 'https://api.connectlife.io/swagger/oauth2-redirect.html',
            ]);

            return $response->json()['access_token'];
        });
    }

    public function status(?string $deviceId = null)
    {
        $properties = [];

        foreach ($this->devices() as $device) {
            if ($deviceId && $device['id'] !== $deviceId) {
                continue;
            }

            $properties[] = Http::throw()
                ->withToken($this->getAccessToken())
                ->get('https://api.connectlife.io/api/v1/appliance/' . $device['id'])
                ->json()[0];
        }

        return $deviceId ? $properties[0] : $properties;
    }

    public function devices(): array
    {
        return Cache::remember('devices', 60 * 60, function () {
            Log::info('Getting devices.');

            $devices = Http::throw()
                ->withToken($this->getAccessToken())
                ->get('https://api.connectlife.io/api/v1/appliance')
                ->json();

            $response = [];
            foreach ($devices as $device) {
                if ($device['status'] === 'Offline' || $device['type'] !== 'AirConditioner') {
                    continue;
                }

                $response[] = $device;
            }

            return $response;
        });
    }
}
