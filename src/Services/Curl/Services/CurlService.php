<?php

namespace Microcrud\Services\Curl\Services;

use Microcrud\Services\Curl\Exceptions\CurlException;
use Illuminate\Support\Facades\Http;

class CurlService
{
    private $requestType = 'Curl';
    private $type;
    private $status_code = 403;
    private $url;
    private $headers = [];
    private $params = [];

    private $response = [];
    private $request = [];

    const GET = 'GET';
    const POST = 'POST';

    public function get($url)
    {
        return $this->invokeHttpRequest(self::GET, $url);
    }

    public function post($url)
    {
        return $this->invokeHttpRequest(self::POST, $url);
    }

    protected function invokeHttpRequest($type, $url)
    {
        if ($this->requestType === 'Http') {
            return $this->invokeHttpClient($type, $url);
        } else {
            return $this->invokeCurlRequest($type, $url);
        }
    }
    protected function invokeCurlRequest($type, $url)
    {
        $ch = curl_init();
        if (!empty($this->params)) {
            if ($type == self::POST) {
                $postData = json_encode($this->params);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($postData)
                ]);
            } else {
                $url = $url . '?' . http_build_query($this->params);
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($this->headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) {
            throw new CurlException("cURL Error: {$err}", $statusCode);
        }
        $this->setStatusCode($statusCode);
        $this->response = json_decode($response, true);
        if ($this->response === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new CurlException("Invalid JSON response: " . json_last_error_msg(), $statusCode);
        }
        return $this;
    }

    protected function invokeHttpClient($type, $url)
    {
        try {
            $response = null;
            if ($type == self::POST) {
                $response = Http::withHeaders($this->headers)->post($url, $this->params);
            } else {
                $response = Http::withHeaders($this->headers)->get($url, $this->params);
            }
            if ($response->successful()) {
                $this->response = $response->json();
            } else {
                $this->setStatusCode($response->status());
            }
        } catch (\Exception $e) {
            throw new CurlException("Error: {$e->getMessage()}", 403);
        }
        return $this;
    }

    public function setHeader(array $headers)
    {
        $this->headers = $headers;
        return $this;
    }

    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    protected function getParams()
    {
        return $this->params;
    }

    public function setStatusCode($status_code)
    {
        $this->status_code = $status_code;
        return $this;
    }

    public function getStatusCode()
    {
        return $this->status_code;
    }

    public function getResponse($key = null)
    {
        if ($key) {
            if (array_key_exists($key, $this->response)) {
                return $this->response[$key];
            } else {
                throw new CurlException('Invalid response key: ' . $key);
            }
        }
        return $this->response;
    }

    public function setRequestType($type)
    {
        $this->requestType = $type;
        return $this;
    }

    public function getUrl($key, $replacements = [])
    {
        $base_car_info_url = env('BASE_CAR_INFO_URL', '');
        $base_ocpp_url = env('OCPP_SERVICE_URL', '');
        $payme_url = env('PAYME_SUBSCRIBE_URL', '');
        $urls = [
            'car_info' => $base_car_info_url,
            'ocpp-start-charging' => $base_ocpp_url . 'charging-station/:id/remote-start-transaction',
            'payme-subscribe' => $payme_url,
        ];
        if (!array_key_exists($key, $urls)) {
            throw new \Exception("Url key not found!");
        }
        $url = $urls[$key];
        if (count($replacements)) {
            foreach ($replacements as $key => $value) {
                $url = str_replace(':' . $key, $value, $url);
            }
        }
        return $url;
    }
}
