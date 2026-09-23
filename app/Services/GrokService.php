<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

class GrokService
{
    private const API_URL = 'https://api.x.ai/v1/responses';

    private const MODEL = 'grok-4.6';

    private string $apiKey;

    public function __construct()
    {
        /*
         * XAI_API_KEY is provided by Apache through:
         *
         * SetEnv XAI_API_KEY "your-key"
         *
         * in:
         *
         * C:\xampp\apache\conf\httpd.conf
         */
        $apiKey = getenv('XAI_API_KEY');

        /*
         * Fallback for environments that populate $_ENV.
         */
        if ($apiKey === false || trim((string) $apiKey) === '') {
            $apiKey = $_ENV['XAI_API_KEY'] ?? '';
        }

        $this->apiKey = trim((string) $apiKey);

        if ($this->apiKey === '') {
            throw new RuntimeException(
                'XAI_API_KEY is not configured.'
            );
        }
    }

    /**
     * Send the conversation to Grok.
     *
     * @param array<int, array{
     *     role:string,
     *     content:string
     * }> $conversation
     */
    public function sendMessage(
        array $conversation,
        string $systemPrompt
    ): string {
        if (empty($conversation)) {
            throw new InvalidArgumentException(
                'Conversation cannot be empty.'
            );
        }

        $input = [];

        /*
         * System instructions.
         */
        $input[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        /*
         * Add the existing DentalLink conversation.
         */
        foreach ($conversation as $message) {
            $role = (string) ($message['role'] ?? '');
            $content = trim(
                (string) ($message['content'] ?? '')
            );

            if ($content === '') {
                continue;
            }

            /*
             * Only allow roles that we explicitly create.
             */
            if (!in_array(
                $role,
                ['user', 'assistant'],
                true
            )) {
                continue;
            }

            $input[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        /*
         * We need at least:
         *
         * system
         * user
         */
        if (count($input) < 2) {
            throw new InvalidArgumentException(
                'No valid user message was provided.'
            );
        }

        $payload = [
            'model' => self::MODEL,

            /*
             * For a clinic chatbot, keep the request/response
             * from being stored by xAI.
             *
             * Your own DentalLink database still stores the
             * conversation through the messages table.
             */
            'store' => false,

            'input' => $input,
        ];

        $jsonPayload = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
        );

        $ch = curl_init(self::API_URL);

        if ($ch === false) {
            throw new RuntimeException(
                'Unable to initialize cURL.'
            );
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],

            CURLOPT_POSTFIELDS => $jsonPayload,

            /*
             * Connection timeout.
             */
            CURLOPT_CONNECTTIMEOUT => 10,

            /*
             * Maximum time waiting for Grok.
             */
            CURLOPT_TIMEOUT => 90,

            /*
             * Verify TLS certificates.
             */
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $curlError = curl_error($ch);

            curl_close($ch);

            throw new RuntimeException(
                'Unable to connect to Grok API: ' . $curlError
            );
        }

        $httpCode = (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        $data = json_decode(
            $response,
            true
        );

        if (!is_array($data)) {
            throw new RuntimeException(
                'Grok returned an invalid JSON response.'
            );
        }

        /*
         * HTTP error from xAI.
         */
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMessage = 'Unknown Grok API error.';

            if (
                isset($data['error']) &&
                is_array($data['error']) &&
                isset($data['error']['message'])
            ) {
                $errorMessage = (string) $data['error']['message'];
            }

            throw new RuntimeException(
                'Grok API returned HTTP ' .
                $httpCode .
                ': ' .
                $errorMessage
            );
        }

        /*
         * Preferred output field.
         */
        if (
            isset($data['output_text']) &&
            is_string($data['output_text'])
        ) {
            $text = trim($data['output_text']);

            if ($text !== '') {
                return $text;
            }
        }

        /*
         * Fallback parser for Responses API output.
         */
        if (
            isset($data['output']) &&
            is_array($data['output'])
        ) {
            foreach ($data['output'] as $outputItem) {

                if (
                    !is_array($outputItem) ||
                    !isset($outputItem['content']) ||
                    !is_array($outputItem['content'])
                ) {
                    continue;
                }

                foreach ($outputItem['content'] as $contentItem) {

                    if (
                        !is_array($contentItem)
                    ) {
                        continue;
                    }

                    if (
                        isset($contentItem['type']) &&
                        $contentItem['type'] === 'output_text' &&
                        isset($contentItem['text']) &&
                        is_string($contentItem['text'])
                    ) {
                        $text = trim(
                            $contentItem['text']
                        );

                        if ($text !== '') {
                            return $text;
                        }
                    }
                }
            }
        }

        throw new RuntimeException(
            'Grok returned no usable text response.'
        );
    }
}