<?php

declare(strict_types=1);

function unibagIsAuthenticated(): bool
{
    return (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 0) > 0;
}

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

function unibagHandleLoginPost(): void
{
    requireCsrf();

    try {
        $trzPdo = Db::trzPdo();
    } catch (Throwable $e) {
        renderDatabaseConnectionError($e);
    }
    ensureAuthSchema($trzPdo);

    $username = trim((string)($_POST['user_login'] ?? ''));
    $password = (string)($_POST['user_pass'] ?? '');
    $companyId = isset($_POST['user_company_id']) ? (int)$_POST['user_company_id'] : 1;
    $erpArea = normalizeErpArea((string)($_POST['erp_area'] ?? 'ERP'));
    $appMode = 0;
    $plantId = 0;

    $modes = authModeDefinitions();
    $companies = authCompanyDefinitions();
    $plants = authPlantDefinitions();
    $mode = $modes[$appMode] ?? $modes[0];
    $company = $companies[$companyId] ?? $companies[1];
    $plant = null;

    $user = unibagFindAuthorizedUser($trzPdo, $username, $password, $erpArea, $appMode);

    if (!is_array($user)) {
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
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['auth_user_id'] = (int)$user['id'];
    $_SESSION['auth_username'] = (string)$user['username'];
    $_SESSION['auth_display_name'] = (string)$user['display_name'];
    $_SESSION['operator_name'] = (string)$user['display_name'];
    $_SESSION['menu_appmode'] = (int)$mode['id'];
    $_SESSION['app_mode_label'] = (string)$mode['label'];
    $_SESSION['user_company_id'] = (int)$company['id'];
    $_SESSION['company_name'] = (string)$company['label'];
    $_SESSION['user_planta_id'] = $plant !== null ? (int)$plant['id'] : 0;
    $_SESSION['plant_name'] = $plant !== null ? (string)$plant['label'] : '';
    $areaPermissions = userAreaPermissions($user);
    $_SESSION['perm_area_erp'] = $areaPermissions['ERP'] ? 1 : 0;
    $_SESSION['perm_area_reception'] = $areaPermissions['RECEPTION'] ? 1 : 0;
    $_SESSION['perm_area_production'] = $areaPermissions['PRODUCTION'] ? 1 : 0;
    $_SESSION['erp_area'] = $erpArea;
    $_SESSION['erp_area_label'] = erpAreaDefinitions()[$erpArea]['label'] ?? 'ERP';

    $erpAreaHome = erpAreaDefinitions()[$erpArea]['home'] ?? '/';
    session_write_close();
    redirectResponse($erpAreaHome);
}

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

function unibagHandleLoginGet(): void
{
    if (unibagIsAuthenticated()) {
        $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
        $areaHome = erpAreaDefinitions()[$currentArea]['home'] ?? '/';
        redirectResponse($areaHome);
    }
    renderLoginPage();
}

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
