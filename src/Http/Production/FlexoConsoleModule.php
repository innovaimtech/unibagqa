<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Consola Operativa de Flexografía y Bobinas Semielaboradas
// (src/Http/Production/FlexoConsoleModule.php)
// =============================================================================

function renderFlexoConsoleInfoSection(array $__vars): void
{
    echo renderView('production/flexo/info_section', $__vars);
}

function renderFlexoSemielaboradoSubformSection(array $__vars): void
{
    echo renderView('production/flexo/semielaborado_subform', $__vars);
}
