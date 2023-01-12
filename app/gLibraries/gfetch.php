<?php

namespace App\gLibraries;

class gFetch
{
    private $curl;
    private string $response;
    public bool $ok;
    public string $status;
    public string $contentType;
    function __construct($url, $options = [
        'method' => 'GET',
        'body' => [],
        'headers' => []
    ])
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $options['method'] ?? 'GET',
            CURLOPT_POSTFIELDS => json_encode($options['body'] ?? [], JSON_PRETTY_PRINT),
            CURLOPT_HTTPHEADER => $options['headers'],
        ]);
        $this->response = curl_exec($this->curl);
        $this->status = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        $this->ok = $this->status >= 200 && $this->status < 300 ? true : false;
        $this->contentType = curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE);
    }

    public function text(): string
    {
        return $this->response;
    }
    public function json(): array
    {
        return json_decode($this->response, true);
    }
    public function blob(): string
    {
        return $this->response;
    }
    function __destruct()
    {
        curl_close($this->curl);
    }
}
