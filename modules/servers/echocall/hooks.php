<?php
/**
 * WHMCS hooks of the EchoCall module.
 *
 *  - InvoicePaid       books a paid top up onto the EchoCall balance
 *  - InvoiceCancelled  closes the open top up of a cancelled invoice
 *  - DailyCronJob      books paid top ups a hook missed
 *  - EmailPreSend      adds {$echocall_portal_url} to product emails
 *
 * Unsuspending after payment is left to WHMCS itself (Automation Settings >
 * Enable Unsuspension), which only lifts suspensions caused by overdue
 * invoices. Lifting every suspension on any payment would also undo manual
 * suspensions, for example for abuse.
 *
 * @package EchoCall
 * @version 2.0.0
 * @license Apache-2.0
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

require_once __DIR__ . '/lib/EchoCallAPI.php';
require_once __DIR__ . '/lib/EchoCallModule.php';
require_once __DIR__ . '/lib/EchoCallTopups.php';

add_hook('InvoicePaid', 1, function (array $vars): void {
    $invoiceId = (int)($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return;
    }
    try {
        EchoCallTopups::onInvoicePaid($invoiceId);
    } catch (Throwable $e) {
        // The daily cron tries again. Never break WHMCS's payment flow.
        EchoCallModule::activity('Top up hook failed for invoice #' . $invoiceId . ': ' . EchoCallModule::failure($e));
    }
});

add_hook('InvoiceCancelled', 1, function (array $vars): void {
    $invoiceId = (int)($vars['invoiceid'] ?? 0);
    if ($invoiceId <= 0) {
        return;
    }
    try {
        EchoCallTopups::onInvoiceCancelled($invoiceId);
    } catch (Throwable $e) {
        EchoCallModule::activity('Top up cancel hook failed for invoice #' . $invoiceId . ': ' . EchoCallModule::failure($e));
    }
});

add_hook('DailyCronJob', 1, function (): void {
    try {
        EchoCallTopups::retryOpen();
    } catch (Throwable $e) {
        EchoCallModule::activity('Top up cron failed: ' . EchoCallModule::failure($e));
    }
});

/**
 * Product emails (for example the welcome email) can link the sign in page
 * with {$echocall_portal_url}.
 */
add_hook('EmailPreSend', 1, function (array $vars): array {
    $serviceId = (int)($vars['relid'] ?? 0);
    if ($serviceId <= 0 || (string)($vars['messagename'] ?? '') === '') {
        return [];
    }
    try {
        $type = \WHMCS\Database\Capsule::table('tblemailtemplates')
            ->where('name', (string)($vars['messagename'] ?? ''))
            ->value('type');
        if ($type !== 'product') {
            return [];
        }
        $params = EchoCallModule::paramsForService($serviceId);
        $url = EchoCallModule::portalUrl($params);
        return $url !== '' ? ['echocall_portal_url' => $url] : [];
    } catch (Throwable $e) {
        // Not an EchoCall service, or EchoCall not reachable: send the email unchanged.
        return [];
    }
});
