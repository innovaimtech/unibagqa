<?php

declare(strict_types=1);

final class ScaleService
{
    public function __construct(private array $env)
    {
    }

    public function readWeightKg(): array
    {
        $mode = strtolower((string)($this->env['SCALE_MODE'] ?? 'stub'));

        if (in_array($mode, ['', 'none', 'off', 'disabled'], true)) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => null, 'error' => 'Balanza deshabilitada (SCALE_MODE).'];
        }

        if ($mode === 'http') {
            $url = (string)($this->env['SCALE_HTTP_URL'] ?? 'http://127.0.0.1:8765/weight');
            return $this->readFromHttp($url);
        }

        if ($mode !== 'stub') {
            return ['ok' => false, 'weight_kg' => null, 'raw' => null, 'error' => 'Modo de balanza no soportado.'];
        }

        $bucket = intdiv(time(), 20);
        $n = (int)(crc32('stub:' . (string)$bucket) & 0x7fffffff);
        $pick = $n % 10;
        if ($pick <= 2) {
            $weight = 0.0;
        } else {
            $weight = round((5000 + ($n % 40000)) / 1000, 3);
        }
        return ['ok' => true, 'weight_kg' => $weight, 'raw' => null, 'error' => null];
    }

    private function readFromHttp(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => null, 'error' => 'cURL no está disponible en PHP.'];
        }

        $ch = curl_init($url);
        $headers = [];
        if (str_contains($url, '127.0.0.1')) {
            $parsedPort = parse_url($url, PHP_URL_PORT) ?: 8765;
            $headers[] = 'Host: localhost:' . $parsedPort;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
            CURLOPT_CONNECTTIMEOUT_MS => 800,
            CURLOPT_TIMEOUT_MS => 1200,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => null, 'error' => $err !== '' ? $err : 'No se pudo leer la balanza.'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => 'Respuesta HTTP inválida: ' . $httpCode];
        }

        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => 'Respuesta no válida de balanza (se esperaba JSON).'];
        }

        if (isset($data['ok']) && $data['ok'] === false) {
            $errorMsg = !empty($data['error']) ? (string)$data['error'] : 'Error en lectura de balanza.';
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => $errorMsg];
        }

        if (!array_key_exists('weight_kg', $data) || $data['weight_kg'] === null) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => (string)($data['error'] ?? 'Sin lectura de peso disponible.')];
        }

        $weight = (float)$data['weight_kg'];
        if ($weight < 0) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => 'Peso inválido recibido.'];
        }

        return ['ok' => true, 'weight_kg' => round($weight, 3), 'raw' => (string)$raw, 'error' => null];
    }
}
