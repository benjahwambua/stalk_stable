<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * Authentication + role-based authorization.
 *
 * Super users bypass module restrictions. Every other user is limited
 * to the modules assigned to their role. This is enforced here so a
 * user cannot bypass the sidebar by typing a module URL directly.
 */
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

function currentUserRole(): string
{
    return trim((string)($_SESSION['role'] ?? 'Staff'));
}

function isSuperUser(): bool
{
    return (int)($_SESSION['is_super'] ?? 0) === 1;
}

function roleModules(): array
{
    return [
        'Admin' => [
            'products', 'categories', 'brands',
            'customers', 'suppliers', 'purchases', 'sales',
            'customer_payments', 'supplier_payments', 'deliveries', 'stock_movements', 'expenses',
            'reports', 'analytics', 'supply_chain', 'mpesa'
        ],
        'Manager' => [
            'products', 'categories', 'brands',
            'customers', 'suppliers', 'purchases', 'sales',
            'customer_payments', 'supplier_payments', 'deliveries', 'stock_movements', 'expenses',
            'reports', 'analytics', 'supply_chain', 'mpesa'
        ],
        'Sales' => [
            'customers', 'sales', 'customer_payments', 'deliveries', 'reports'
        ],
        'Cashier' => [
            'customers', 'sales', 'customer_payments'
        ],
        'Storekeeper' => [
            'products', 'categories', 'brands', 'suppliers',
            'purchases', 'stock_movements', 'deliveries', 'supply_chain'
        ],
        'Driver' => [
            'deliveries'
        ],
        'Staff' => [],
    ];
}

function canAccessModule(string $module): bool
{
    if (isSuperUser()) {
        return true;
    }

    $role = currentUserRole();
    $permissions = roleModules();

    return in_array($module, $permissions[$role] ?? [], true);
}

function requireModuleAccess(string $module): void
{
    if (canAccessModule($module)) {
        return;
    }

    http_response_code(403);
    exit('Access denied. Your user role does not have permission to access this module.');
}

/**
 * Automatically enforce the permission for any page under /modules/{module}/.
 * Individual module pages do not need to remember to add another guard.
 */
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$modulesMarker = '/modules/';
$markerPosition = strpos($requestPath, $modulesMarker);

if ($markerPosition !== false) {
    $afterMarker = substr($requestPath, $markerPosition + strlen($modulesMarker));
    $module = strtolower((string)strtok($afterMarker, '/'));

    if ($module !== '') {
        requireModuleAccess($module);
    }
}
