<?php
// app/LineBotService.php

class LineBotService
{
    private string $token;

    public function __construct(string $channelAccessToken)
    {
        $this->token = $channelAccessToken;
    }

    public function pushText(string $to, string $text): array
    {
        return $this->request('/v2/bot/message/push', [
            'to' => $to,
            'messages' => [
                ['type' => 'text', 'text' => $text],
            ],
        ]);
    }

    public function replyText(string $replyToken, string $text): array
    {
        return $this->request('/v2/bot/message/reply', [
            'replyToken' => $replyToken,
            'messages' => [
                ['type' => 'text', 'text' => $text],
            ],
        ]);
    }

    private function request(string $path, array $body): array
    {
        $url = 'https://api.line.me' . $path;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token,
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 10,
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            throw new RuntimeException("LINE API curl error: {$err}");
        }

        $json = json_decode($resp, true);
        if ($code < 200 || $code >= 300) {
            $msg = is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : $resp;
            throw new RuntimeException("LINE API HTTP {$code}: {$msg}");
        }

        return is_array($json) ? $json : ['raw' => $resp];
    }
}
