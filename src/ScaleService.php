<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Servicio de Integración con Balanza Digital Industrial (LP-7516 / RS-232 / USB)
 * (src/ScaleService.php)
 * =============================================================================
 *
 * Responsabilidad:
 * Proporcionar una interfaz unificada para obtener el peso en kilogramos (kg) de
 * bobinas y materiales en los módulos de recepción, pesaje y control de calidad.
 *
 * Modos de operación configurables mediante la variable de entorno SCALE_MODE:
 * 1. 'http':
 *    Se comunica vía HTTP GET con el servicio puente local (scale/scale_bridge.ps1)
 *    que escucha en http://127.0.0.1:8765/weight. Este puente interactúa con el
 *    puerto serie COM (RS-232 a USB) de la balanza física LP-7516.
 *
 * 2. 'stub':
 *    Modo de simulación para desarrollo y pruebas locales. Genera lecturas de peso
 *    verosímiles y pseudoaleatorias que varían cada 20 segundos sin necesidad de
 *    tener una balanza conectada físicamente.
 *
 * 3. 'disabled' / 'none' / 'off':
 *    Desactiva la lectura de balanza (usado en entornos donde el peso se digita a mano).
 */
final class ScaleService
{
    /**
     * @param array<string, mixed> $env Variables de configuración del entorno (usualmente $_ENV o parsed .env).
     */
    public function __construct(private array $env)
    {
    }

    /**
     * Obtiene la lectura actual de peso en kilogramos según el modo configurado.
     *
     * @return array{
     *     ok: bool,
     *     weight_kg: float|null,
     *     raw: string|null,
     *     error: string|null
     * } Retorna un arreglo asociativo con el estado del pesaje, el valor en kg, la trama cruda y errores si ocurrieron.
     */
    public function readWeightKg(): array
    {
        $mode = strtolower((string)($this->env['SCALE_MODE'] ?? 'stub'));

        // Caso 1: Balanza deshabilitada explícitamente en el entorno
        if (in_array($mode, ['', 'none', 'off', 'disabled'], true)) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => null, 'error' => 'Balanza deshabilitada (SCALE_MODE).'];
        }

        // Caso 2: Modo producción con puente HTTP hacia el puerto COM de la balanza
        if ($mode === 'http') {
            $url = (string)($this->env['SCALE_HTTP_URL'] ?? 'http://127.0.0.1:8765/weight');
            return $this->readFromHttp($url);
        }

        // Caso 3: Modo simulación / pruebas locales (stub)
        if ($mode !== 'stub') {
            return ['ok' => false, 'weight_kg' => null, 'raw' => null, 'error' => 'Modo de balanza no soportado.'];
        }

        // Algoritmo de simulación determinista por bloques de 20 segundos:
        // Genera pesos en un rango realista (5.000 kg a 45.000 kg) o 0.0 kg si no hay carga
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

    /**
     * Realiza una petición HTTP al puente local (PowerShell scale_bridge.ps1).
     *
     * El puente consulta la balanza por puerto COM usando Win32Serial, parsea la trama
     * y responde con un JSON: {"ok": true, "weight_kg": 12.345, "stable": true, ...}.
     *
     * @param string $url URL del endpoint de pesaje (por defecto http://127.0.0.1:8765/weight).
     * @return array{ok: bool, weight_kg: float|null, raw: string|null, error: string|null}
     */
    private function readFromHttp(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => null, 'error' => 'cURL no está disponible en PHP.'];
        }

        $ch = curl_init($url);
        $headers = [];
        // Compatibilidad para resolución de localhost en Windows
        if (str_contains($url, '127.0.0.1')) {
            $parsedPort = parse_url($url, PHP_URL_PORT) ?: 8765;
            $headers[] = 'Host: localhost:' . $parsedPort;
        }

        // Timeouts estrictos: no bloquear la UI si el puente o la balanza están apagados
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
            CURLOPT_CONNECTTIMEOUT_MS => 800,  // Tiempo máximo para conectar con el puente
            CURLOPT_TIMEOUT_MS => 1200,         // Tiempo máximo total de lectura
            CURLOPT_FAILONERROR => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Validar fallas de red / servicio caído
        if ($raw === false) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => null, 'error' => $err !== '' ? $err : 'No se pudo leer la balanza.'];
        }

        // Validar código de respuesta HTTP
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => 'Respuesta HTTP inválida: ' . $httpCode];
        }

        // Decodificar JSON retornado por el puente
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => 'Respuesta no válida de balanza (se esperaba JSON).'];
        }

        // Verificar si el puente reportó error en el hardware o timeout serie
        if (isset($data['ok']) && $data['ok'] === false) {
            $errorMsg = !empty($data['error']) ? (string)$data['error'] : 'Error en lectura de balanza.';
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => $errorMsg];
        }

        // Verificar que el campo weight_kg esté presente
        if (!array_key_exists('weight_kg', $data) || $data['weight_kg'] === null) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => (string)($data['error'] ?? 'Sin lectura de peso disponible.')];
        }

        $weight = (float)$data['weight_kg'];
        if ($weight < 0) {
            return ['ok' => false, 'weight_kg' => null, 'raw' => (string)$raw, 'error' => 'Peso inválido recibido.'];
        }

        // Retornar lectura limpia y redondeada a 3 decimales (precisión de gramos)
        return ['ok' => true, 'weight_kg' => round($weight, 3), 'raw' => (string)$raw, 'error' => null];
    }
}
