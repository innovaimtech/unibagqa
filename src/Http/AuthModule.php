<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Autenticación
//
// Responsabilidades:
// - Determinar si existe sesión autenticada.
// - Manejar /login (GET/POST) y /logout.
// - Validar credenciales contra la tabla local auth_users.
// - Cargar en sesión: usuario, permisos por área y metadatos de navegación.
//
// Notas:
// - Este proyecto no usa framework: el router central invoca handleAuthRoutes().
// - La sesión define permisos por “área” (ERP / RECEPTION / PRODUCTION / SCALE).
//
// ---
//
// HTTP Module · Authentication
//
// Responsibilities:
// - Detect whether an authenticated session exists.
// - Handle /login (GET/POST) and /logout.
// - Validate credentials against the local auth_users table.
// - Store user identity, area permissions, and navigation metadata in session.
//
// Notes:
// - This project does not use a framework: the main router calls handleAuthRoutes().
// - Session permissions are scoped by “area” (ERP / RECEPTION / PRODUCTION / SCALE).
// =============================================================================

/**
 * Indica si la sesión actual tiene un usuario autenticado.
 *
 * Se soportan dos keys históricas para compatibilidad:
 * - auth_user_id
 * - user_id
 *
 * ---
 *
 * Indicates whether the current session has an authenticated user.
 *
 * Two historical keys are supported for compatibility:
 * - auth_user_id
 * - user_id
 */
function unibagIsAuthenticated(): bool
{
    return (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 0) > 0;
}

/**
 * Cierra sesión y limpia:
 * - Variables de sesión
 * - Cookie de sesión (si aplica)
 * - Cookie CSRF
 *
 * Luego redirige a /login.
 *
 * ---
 *
 * Logs out and clears:
 * - Session variables
 * - Session cookie (if enabled)
 * - CSRF cookie
 *
 * Then redirects to /login.
 */
function unibagHandleLogout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'] ?: '/',
            $params['domain'] ?? '',
            (bool)($params['secure'] ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }
    expireCsrfCookie();
    redirectResponse('/login');
}

/**
 * Maneja POST /login:
 * - Valida CSRF
 * - Verifica credenciales
 * - Regenera session_id
 * - Guarda metadata de usuario + permisos
 * - Redirige al “home” del área seleccionada
 *
 * ---
 *
 * Handles POST /login:
 * - Validates CSRF
 * - Verifies credentials
 * - Regenerates session_id
 * - Stores user metadata + permissions
 * - Redirects to the selected area “home”
 */
function unibagHandleLoginPost(): void
{
    requireCsrf();

    try {
        $trzPdo = Db::trzPdo();
    } catch (Throwable $e) {
        renderDatabaseConnectionError($e);
    }
    $erpPdo = null;
    try {
        $erpPdo = Db::erpPdo();
    } catch (Throwable) {
        $erpPdo = null;
    }

    $username = trim((string)($_POST['user_login'] ?? ''));
    $password = (string)($_POST['user_pass'] ?? '');
    $companyId = isset($_POST['user_company_id']) ? (int)$_POST['user_company_id'] : 20010;
    $erpArea = normalizeErpArea((string)($_POST['erp_area'] ?? 'ERP'));
    $appMode = isset($_POST['appmode']) ? (int)$_POST['appmode'] : 0;
    $plantId = isset($_POST['user_planta_id']) ? (int)$_POST['user_planta_id'] : 0;
    if ($appMode === 0 && $erpArea !== 'ERP') {
        $appMode = match ($erpArea) {
            'PRODUCTION' => 1,
            'SCALE' => 1,
            default => 0,
        };
    }

    $modes = authModeDefinitions();
    $companies = authCompanyDefinitions();
    $plants = authPlantDefinitions();
    $mode = $modes[$appMode] ?? $modes[0];
    $company = $companies[$companyId] ?? $companies[20010];
    $plant = null;

    $user = null;
    if ($erpPdo instanceof PDO) {
        $erpUser = unibagFindAuthorizedErpUser($erpPdo, $username, $password, $companyId, $appMode);
        if (is_array($erpUser)) {
            $user = $erpUser;
        } elseif ($erpArea !== 'ERP') {
            $worker = unibagFindAuthorizedErpWorker($erpPdo, $username, $password);
            if (is_array($worker)) {
                $user = [
                    'id' => (int)($worker['wrk_uid'] ?? 0),
                    'username' => (string)($worker['wrk_rut'] ?? $username),
                    'display_name' => trim((string)($worker['wrk_firstname'] ?? '') . ' ' . (string)($worker['wrk_lastname'] ?? '')),
                    'can_erp' => 0,
                    'can_production' => 1,
                    'can_operator' => 1,
                    'can_warehouse' => 1,
                    'can_marketing' => 0,
                    'erp_worker' => $worker,
                    'erp_user_groups' => [],
                    'erp_appmodes' => [
                        0 => 0,
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 0,
                    ],
                ];
            }
        }
    }


    if (!is_array($user)) {
        ensureAuthSchema($trzPdo);
        $user = unibagFindAuthorizedUser($trzPdo, $username, $password, $erpArea, $appMode);
    }

    if (!is_array($user)) {
        // Credenciales o permisos inválidos: re-render del login con mensaje.
        renderLoginPage('Usuario, clave o modo sin acceso.', [
            'user_login' => $username,
            'user_company_id' => $companyId,
            'erp_area' => $erpArea,
            'appmode' => $appMode,
            'user_planta_id' => $plantId,
        ]);
        exit;
    }

    session_regenerate_id(true);
    $userId = (int)($user['id'] ?? 0);
    $_SESSION['user_id'] = $userId;
    $_SESSION['auth_user_id'] = $userId;
    $_SESSION['auth_username'] = (string)($user['username'] ?? $user['user_name'] ?? $username);
    $_SESSION['auth_display_name'] = (string)($user['display_name'] ?? $user['auth_display_name'] ?? $username);
    $_SESSION['operator_name'] = (string)($_SESSION['auth_display_name'] ?? $username);

    $_SESSION['user_company_id'] = (int)$company['id'];
    $_SESSION['company_name'] = (string)$company['label'];
    $_SESSION['menu_appmode'] = (int)$mode['id'];
    $_SESSION['app_mode_label'] = (string)$mode['label'];

    if (isset($user['erp_worker']) && is_array($user['erp_worker'])) {
        $worker = $user['erp_worker'];
        $_SESSION['wrk_id'] = (int)($worker['id'] ?? 0);
        $_SESSION['wrk_firstname'] = (string)($worker['wrk_firstname'] ?? '');
        $_SESSION['wrk_lastname'] = (string)($worker['wrk_lastname'] ?? '');
        $_SESSION['wrk_alldata'] = $worker;
    }

    if (isset($user['user_firstname'])) {
        $_SESSION['user_firstname'] = (string)$user['user_firstname'];
    }
    if (isset($user['user_lastname'])) {
        $_SESSION['user_lastname'] = (string)$user['user_lastname'];
    }
    if (isset($user['user_name'])) {
        $_SESSION['user_name'] = (string)$user['user_name'];
    }
    if (isset($user['user_type'])) {
        $_SESSION['user_type'] = (string)$user['user_type'];
    }
    if (isset($user['user_mail'])) {
        $_SESSION['user_mail'] = (string)$user['user_mail'];
    }
    if (isset($user['user_code'])) {
        $_SESSION['user_code'] = (string)$user['user_code'];
    }
    if (isset($user['user_groups'])) {
        $_SESSION['user_groups'] = $user['user_groups'];
    }
    if (isset($user['user_appmode_0'])) {
        $_SESSION['user_appmode_0'] = (int)$user['user_appmode_0'];
        $_SESSION['user_appmode_1'] = (int)($user['user_appmode_1'] ?? 0);
        $_SESSION['user_appmode_2'] = (int)($user['user_appmode_2'] ?? 0);
        $_SESSION['user_appmode_3'] = (int)($user['user_appmode_3'] ?? 0);
        $_SESSION['user_appmode_4'] = (int)($user['user_appmode_4'] ?? 0);
    }

    $_SESSION['user_planta_id'] = $plantId;
    $_SESSION['planta_name'] = $erpPdo instanceof PDO && $plantId > 0 ? unibagFindErpPlantaName($erpPdo, $plantId) : '';

    $areaPermissions = userAreaPermissions($user);
    $_SESSION['perm_area_erp'] = $areaPermissions['ERP'] ? 1 : 0;
    $_SESSION['perm_area_reception'] = $areaPermissions['RECEPTION'] ? 1 : 0;
    $_SESSION['perm_area_production'] = $areaPermissions['PRODUCTION'] ? 1 : 0;
    $_SESSION['perm_area_scale'] = $areaPermissions['SCALE'] ? 1 : 0;
    $_SESSION['erp_area'] = $erpArea;
    $_SESSION['erp_area_label'] = erpAreaDefinitions()[$erpArea]['label'] ?? 'ERP';

    // Redirección final al home del área (ERP/Recepción/Producción/etc).
    $erpAreaHome = erpAreaDefinitions()[$erpArea]['home'] ?? '/';
    session_write_close();
    redirectResponse($erpAreaHome);
}

/**
 * Busca y valida un usuario contra la tabla auth_users (DB TRZ).
 *
 * Reglas:
 * - username + password deben venir no vacíos
 * - password se valida con password_verify() contra password_hash
 * - se exige que el usuario tenga permiso para el “modo” (columna authPermissionColumn)
 * - además se exige permiso para el área solicitada (ERP/RECEPTION/PRODUCTION/SCALE)
 *
 * ---
 *
 * Finds and validates a user against the auth_users table (TRZ DB).
 *
 * Rules:
 * - username + password must be provided (non-empty)
 * - password is checked via password_verify() against password_hash
 * - user must have permission for the selected “mode” (authPermissionColumn)
 * - user must also have permission for the requested area (ERP/RECEPTION/PRODUCTION/SCALE)
 *
 * @return array<string,mixed>|null Usuario autenticado o null si no cumple
 */
function unibagFindAuthorizedUser(PDO $trzPdo, string $username, string $password, string $erpArea = 'ERP', int $appMode = 0): ?array
{
    ensureAuthSchema($trzPdo);

    $username = trim($username);
    $erpArea = normalizeErpArea($erpArea);
    if ($username === '' || $password === '') {
        return null;
    }

    $stmt = $trzPdo->prepare('SELECT * FROM auth_users WHERE username = :username AND is_active = 1 LIMIT 1');
    $stmt->execute(['username' => $username]);
    $found = $stmt->fetch();
    if (!is_array($found) || !password_verify($password, (string)$found['password_hash'])) {
        return null;
    }

    $permissionColumn = authPermissionColumn($appMode);
    $areaPermissions = userAreaPermissions($found);
    if ($permissionColumn === '' || (int)($found[$permissionColumn] ?? 0) !== 1 || !userCanAccessArea($erpArea, $areaPermissions)) {
        return null;
    }

    return $found;
}

function unibagFindAuthorizedErpUser(PDO $erpPdo, string $username, string $password, int $companyId, int $appMode): ?array
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return null;
    }

    try {
        $stmt = $erpPdo->prepare(
            'SELECT u.*
             FROM user u
             INNER JOIN user_companies uc ON u.id = uc.user_id
             WHERE u.user_login = :login
               AND u.user_pass = :pass
               AND u.user_status = 1
               AND uc.company_id = :company_id
             LIMIT 1'
        );
        $stmt->execute([
            ':login' => $username,
            ':pass' => md5($password),
            ':company_id' => $companyId,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
    } catch (Throwable) {
        return null;
    }

    $flagKey = 'user_appmode_' . $appMode;
    if ($appMode >= 0 && $appMode <= 4 && (int)($row[$flagKey] ?? 0) !== 1) {
        return null;
    }

    $groups = [];
    try {
        $gstmt = $erpPdo->prepare(
            'SELECT ug.group_id
             FROM user_group ug
             INNER JOIN `group` g ON ug.group_id = g.id
             WHERE ug.user_id = :user_id
               AND g.group_status = 1'
        );
        $gstmt->execute([':user_id' => (int)$row['id']]);
        foreach ($gstmt->fetchAll() as $gRow) {
            $gid = (int)($gRow['group_id'] ?? 0);
            if ($gid > 0) {
                $groups[] = $gid;
            }
        }
    } catch (Throwable) {
        $groups = [];
    }

    $displayName = trim((string)($row['user_firstname'] ?? '') . ' ' . (string)($row['user_lastname'] ?? ''));
    if ($displayName === '') {
        $displayName = (string)($row['user_login'] ?? $username);
    }

    return [
        'id' => (int)($row['id'] ?? 0),
        'username' => (string)($row['user_login'] ?? $username),
        'display_name' => $displayName,
        'user_firstname' => (string)($row['user_firstname'] ?? ''),
        'user_lastname' => (string)($row['user_lastname'] ?? ''),
        'user_name' => (string)($row['user_login'] ?? $username),
        'user_type' => (string)($row['user_type'] ?? ''),
        'user_mail' => (string)($row['user_mail'] ?? ''),
        'user_code' => (string)($row['user_code'] ?? ''),
        'user_appmode_0' => (int)($row['user_appmode_0'] ?? 0),
        'user_appmode_1' => (int)($row['user_appmode_1'] ?? 0),
        'user_appmode_2' => (int)($row['user_appmode_2'] ?? 0),
        'user_appmode_3' => (int)($row['user_appmode_3'] ?? 0),
        'user_appmode_4' => (int)($row['user_appmode_4'] ?? 0),
        'user_groups' => $groups,
        'can_erp' => (int)($row['user_appmode_0'] ?? 0) === 1 ? 1 : 0,
        'can_production' => ((int)($row['user_appmode_1'] ?? 0) === 1 || (int)($row['user_appmode_2'] ?? 0) === 1) ? 1 : 0,
        'can_warehouse' => (int)($row['user_appmode_3'] ?? 0) === 1 ? 1 : 0,
        'can_marketing' => (int)($row['user_appmode_4'] ?? 0) === 1 ? 1 : 0,
        'can_operator' => ((int)($row['user_appmode_1'] ?? 0) === 1 || (int)($row['user_appmode_2'] ?? 0) === 1) ? 1 : 0,
    ];
}

function unibagFindAuthorizedErpWorker(PDO $erpPdo, string $rut, string $password): ?array
{
    $rut = str_replace('.', '', trim($rut));
    if ($rut === '' || $password === '') {
        return null;
    }
    try {
        $stmt = $erpPdo->prepare(
            "SELECT *
             FROM workers
             WHERE wrk_status > 0
               AND REPLACE(wrk_rut,'.','') LIKE :rut_like
               AND wrk_axx_pass = :pass
               AND wrk_axx_pass != ''
             LIMIT 1"
        );
        $stmt->execute([
            ':rut_like' => $rut . '%',
            ':pass' => $password,
        ]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function unibagFindErpPlantaName(PDO $erpPdo, int $plantId): string
{
    if ($plantId <= 0) {
        return '';
    }
    try {
        $stmt = $erpPdo->prepare('SELECT planta_name FROM plantas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $plantId]);
        $row = $stmt->fetch();
        return is_array($row) ? trim((string)($row['planta_name'] ?? '')) : '';
    } catch (Throwable) {
        return '';
    }
}

/**
 * Maneja GET /login:
 * - Si ya está autenticado, redirige al home del área actual.
 * - Si no, renderiza el formulario de login.
 *
 * ---
 *
 * Handles GET /login:
 * - If already authenticated, redirects to the current area home.
 * - Otherwise, renders the login form.
 */
function unibagHandleLoginGet(): void
{
    if (unibagIsAuthenticated()) {
        $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
        $areaHome = erpAreaDefinitions()[$currentArea]['home'] ?? '/';
        redirectResponse($areaHome);
    }
    renderLoginPage();
}

/**
 * Router del módulo Auth.
 *
 * ---
 *
 * Auth module router.
 *
 * @return bool true si la ruta fue manejada; false para seguir con otros módulos
 */
function handleAuthRoutes(string $path, string $method): bool
{
    if ($path === '/logout') {
        unibagHandleLogout();
        return true;
    }

    if ($path === '/login' && $method === 'POST') {
        unibagHandleLoginPost();
        return true;
    }

    if ($path === '/login' && $method === 'GET') {
        unibagHandleLoginGet();
        return true;
    }

    return false;
}

/**
 * Guardia de acceso por sesión y permisos de área.
 *
 * - Si no hay sesión: redirige a /login.
 * - Si el usuario no tiene permiso para el área detectada por la URL: redirige
 *   al primer home permitido por sus permisos.
 *
 * ---
 *
 * Access guard based on session and area permissions.
 *
 * - If there is no authenticated session: redirects to /login.
 * - If the user lacks permission for the area inferred from the URL: redirects
 *   to the first allowed home based on their permissions.
 */
function unibagEnforceAuthenticatedAreaAccess(string $path): void
{
    if (!unibagIsAuthenticated()) {
        redirectResponse('/login');
    }

    $sessionAreaPermissions = sessionAreaPermissions();
    $requestedArea = detectRequestedArea($path);
    if (!userCanAccessArea($requestedArea, $sessionAreaPermissions)) {
        redirectResponse(firstAllowedAreaHome($sessionAreaPermissions));
    }
}
