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
            $url = (string)($this->env['SCALE_HTTP_URL'] ?? 'http://localhost:8765/weight');
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
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_FAILONERROR => false,
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
        if (!is_array($data) || !isset($data['weight_kg'])) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => 'Respuesta inválida de balanza (se esperaba JSON con weight_kg).'];
        }

        $weight = (float)$data['weight_kg'];
        if ($weight < 0) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => 'Peso inválido recibido.'];
        }

        return ['ok' => true, 'weight_kg' => round($weight, 3), 'raw' => (string)$raw, 'error' => null];
    }
}
