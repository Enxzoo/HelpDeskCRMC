<?php
/**
 * Shared Groq transport for structured concern analysis.
 */
class GroqAiClient
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const TIMEOUT_SECONDS = 12;
    private const MAX_REQUEST_ATTEMPTS = 2;

    private string $apiKey;
    private ?Closure $transport;

    public function __construct(?string $apiKey = null, ?Closure $transport = null)
    {
        $this->apiKey = $apiKey ?? (defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
        $this->transport = $transport;
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    public function complete(array $requestBody): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Groq API key is not configured.');
        }

        $requestBody = array_merge($requestBody, [
            'model' => defined('GROQ_TRIAGE_MODEL') ? GROQ_TRIAGE_MODEL : 'openai/gpt-oss-20b',
            'temperature' => 0,
            'reasoning_effort' => 'low',
            'max_completion_tokens' => 1024,
            'stream' => false,
        ]);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];
        $jsonBody = json_encode($requestBody, JSON_THROW_ON_ERROR);

        for ($attempt = 1; $attempt <= self::MAX_REQUEST_ATTEMPTS; $attempt++) {
            if ($this->transport !== null) {
                $response = ($this->transport)(self::API_URL, $requestBody, $headers);
                if (!is_array($response) || !is_int($response['status'] ?? null)
                    || !is_string($response['body'] ?? null)) {
                    throw new RuntimeException('Groq transport returned an invalid response.');
                }
                $httpCode = $response['status'];
                $body = $response['body'];
            } else {
                $ch = curl_init(self::API_URL);
                if ($ch === false) {
                    throw new RuntimeException('Unable to initialize the Groq request.');
                }
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $jsonBody,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
                ]);
                $body = curl_exec($ch);
                $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_errno($ch);
                curl_close($ch);
                if ($body === false) {
                    throw new RuntimeException('Groq network request failed (cURL code ' . $curlError . ').');
                }
            }

            if ($httpCode === 200) {
                $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                $choice = $result['choices'][0] ?? null;
                $text = $choice['message']['content'] ?? null;
                if (($choice['finish_reason'] ?? null) !== 'stop'
                    || !empty($choice['message']['refusal'])
                    || !is_string($text) || trim($text) === '') {
                    throw new RuntimeException('Groq did not return a complete analysis.');
                }
                return trim($text);
            }

            // Do not log provider bodies: they can echo submitted data or credentials.
            if ($httpCode < 500 || $httpCode > 599 || $attempt === self::MAX_REQUEST_ATTEMPTS) {
                throw new RuntimeException('Groq returned HTTP ' . $httpCode . '.');
            }
            usleep(250000);
        }

        throw new RuntimeException('Groq request failed after retries.');
    }
}
