<?php

declare(strict_types=1);

// =============================================================================
// API Entrypoint (runtime tipo Vercel)
//
// Este archivo existe para compatibilidad con runtimes que esperan un “entrypoint”
// bajo /api. El proyecto en sí usa un router único en public/index.php; por eso
// este archivo simplemente delega la ejecución al entrypoint principal.
//
// Importante:
// - No define rutas propias aquí.
// - No debe contener lógica de negocio, solo delegación.
// =============================================================================
require __DIR__ . '/../public/index.php';
        
