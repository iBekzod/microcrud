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
    const PATCH = 'PATCH';
    const DELETE = 'DELETE';
    const PUT = 'PUT';

    public function get($url)
    {
        return $this->invokeHttpRequest(self::GET, $url);
    }
    public function post($url)
    {
        return $this->invokeHttpRequest(self::POST, $url);
    }
    public function patch($url)
    {
        return $this->invokeHttpRequest(self::PATCH, $url);
    }
    public function delete($url)
    {
        return $this->invokeHttpRequest(self::DELETE, $url);
    }
    public function put($url)
    {
        return $this->invokeHttpRequest(self::PUT, $url);
    }

    protected function invokeHttpRequest($type, $url)
    {
        if ($this->requestType === 'Http') {
            return $this->invokeHttpClient($type, $url);
        } else {
            return $this->invokeCurlRequest($type, $url);
        }
    }
    protected function invokeCurlRequest($method, $url)
    {
        $curl = curl_init();
        $data = $this->params;
        $headers = $this->headers;
        if ($method == self::POST) {
            curl_setopt($curl, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($data))
                ]);
            }
        } else if (in_array($method, [self::GET, self::PATCH, self::DELETE, self::PUT])) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($data)) {
                if($method == self::GET){
                    $url = $url . '?' . http_build_query($data);
                }else{
                    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                }
            }
        } else {
            throw new \Exception('Invalid method');
        }
        if (empty($headers)) {
            $headers = [
                'Content-Type: application/json'
            ];
            if (!empty($data)) {
                $headers[] = 'Content-Length: ' . strlen(json_encode($data));
            }
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
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
        $base_url = env('BASE_URL', '');
        $urls = [
            'base_url' => $base_url,
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
