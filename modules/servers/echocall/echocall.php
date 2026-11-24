<?php
/**
 * EchoCall provisioning module for WHMCS.
 *
 * Sells EchoCall voice agents, chatbots and phone numbers as a WHMCS product.
 * WHMCS keeps the billing (orders, invoices, dunning), EchoCall runs the
 * service. Works with WHMCS 8.13 LTS, 9.0 and 9.1 on PHP 8.2 to 8.4.
 *
 * Install: extract the release ZIP into the WHMCS root, it holds
 * modules/servers/echocall/. Source: https://github.com/ECHOCALL-LLC/echocall-whmcs
 *
 * @package  EchoCall
 * @version  2.0.0
 * @license  Apache-2.0
 * @link     https://github.com/ECHOCALL-LLC/echocall-whmcs
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

require_once __DIR__ . '/lib/EchoCallAPI.php';
require_once __DIR__ . '/lib/EchoCallModule.php';
require_once __DIR__ . '/lib/EchoCallI18n.php';
require_once __DIR__ . '/lib/EchoCallTopups.php';
require_once __DIR__ . '/lib/EchoCallClientArea.php';

function echocall_MetaData(): array
{
    return [
        'DisplayName' => 'EchoCall Voice & Chat',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
        'ServiceSingleSignOnLabel' => false,
        'AdminSingleSignOnLabel' => false,
    ];
}

/**
 * Product settings. WHMCS stores them by position (configoption1 to 11), so
 * the order below must never change. New settings only ever go at the end.
 */
function echocall_ConfigOptions(): array
{
    return [
        'API Key' => [
            'Type' => 'password',
            'Size' => 60,
            'Description' => 'Reseller API key from the EchoCall partner portal (Developer). Starts with eck_live_.',
        ],
        'API Base URL' => [
            'Type' => 'text',
            'Size' => 60,
            'Default' => EchoCallAPI::DEFAULT_BASE_URL,
            'Description' => 'Leave as is unless EchoCall gave you another address.',
        ],
        'Default Language' => [
            'Type' => 'dropdown',
            'Options' => 'de,en,fr',
            'Default' => 'de',
            'Description' => 'Language of new EchoCall accounts and fallback of the client area.',
        ],
        'Welcome Email' => [
            'Type' => 'yesno',
            'Default' => 'on',
            'Description' => 'EchoCall sends the new customer a sign in link.',
        ],
        'Activity Log' => [
            'Type' => 'yesno',
            'Description' => 'Also note successful provisioning steps in the WHMCS activity log. API calls are in Utilities > Logs > Module Log.',
        ],
        'Plan' => [
            'Type' => 'dropdown',
            'Options' => echocall_planOptions(),
            'Description' => 'Optional package of your partner account. Without one the customer pays as they go from their balance. Save the API key first, then reload.',
        ],
        'Portal Login URL' => [
            'Type' => 'text',
            'Size' => 60,
            'Description' => 'Only for your own domain (https://...). Empty uses your partner portal address.',
        ],
        'Upgrade Link' => [
            'Type' => 'yesno',
            'Description' => 'Show a link to the WHMCS upgrade page in the client area.',
        ],
        'Display Theme' => [
            'Type' => 'dropdown',
            'Options' => 'auto,light,dark',
            'Default' => 'auto',
            'Description' => 'auto follows your WHMCS theme.',
        ],
        'Start Credit (EUR)' => [
            'Type' => 'text',
            'Size' => 8,
            'Default' => '0',
            'Description' => 'Credited once when a new EchoCall account is created. 0 = none.',
        ],
        'Top Up Amounts (EUR)' => [
            'Type' => 'text',
            'Size' => 30,
            'Default' => '10,25,50,100',
            'Description' => 'Amounts clients can top up by WHMCS invoice, comma separated, net. Empty = no top ups. EUR clients only.',
        ],
    ];
}

/** "Label|id" options for the plan dropdown, read with the API key already saved on the product. */
function echocall_planOptions(): string
{
    $none = 'Pay as you go (no package)|';
    $productId = (int)($_REQUEST['id'] ?? 0);
    if ($productId <= 0) {
        return $none;
    }
    try {
        $product = \WHMCS\Database\Capsule::table('tblproducts')->where('id', $productId)->first(['configoption1', 'configoption2']);
        $apiKey = EchoCallModule::storedApiKey((string)($product->configoption1 ?? ''));
        if ($apiKey === '') {
            return $none;
        }
        $baseUrl = trim((string)($product->configoption2 ?? '')) ?: EchoCallAPI::DEFAULT_BASE_URL;
        $plans = (new EchoCallAPI($apiKey, $baseUrl))->get('/provisioning/plans');
        $options = [$none];
        foreach (($plans['plans'] ?? []) as $plan) {
            // Commas and pipes would break WHMCS's option list, so the price uses a dot as well.
            $label = trim(str_replace([',', '|'], ' ', (string)($plan['name'] ?? ''))) ?: 'Plan';
            if (isset($plan['priceEur'])) {
                $label .= ' (' . number_format((float)$plan['priceEur'], 2, '.', '') . ' EUR)';
            }
            $options[] = $label . '|' . (int)$plan['id'];
        }
        return implode(',', $options);
    } catch (Throwable $e) {
        return $none;
    }
}

// ─── Provisioning ────────────────────────────────────────────────────────────

function echocall_CreateAccount(array $params): string
{
    try {
        $api = EchoCallModule::api($params);
        if (EchoCallModule::customerId($params) > 0) {
            return 'This service is already linked to EchoCall customer ' . EchoCallModule::customerId($params) . '.';
        }
        $client = $params['clientsdetails'] ?? [];
        $payload = array_filter([
            'email' => (string)($client['email'] ?? ''),
            'firstName' => $client['firstname'] ?? null,
            'lastName' => $client['lastname'] ?? null,
            'company' => $client['companyname'] ?? null,
            'phone' => $client['phonenumberformatted'] ?? ($client['phonenumber'] ?? null),
            'address' => $client['address1'] ?? null,
            'city' => $client['city'] ?? null,
            'postcode' => $client['postcode'] ?? null,
            'country' => $client['countryname'] ?? ($client['country'] ?? null),
            'vatId' => $client['tax_id'] ?? null,
            'language' => EchoCallModule::language($params),
            'sendWelcomeEmail' => EchoCallModule::flag($params, EchoCallModule::OPT_WELCOME_MAIL),
            'planId' => EchoCallModule::planId($params),
        ], static fn ($v) => $v !== null && $v !== '');
        $payload['sendWelcomeEmail'] = EchoCallModule::flag($params, EchoCallModule::OPT_WELCOME_MAIL);

        try {
            $result = $api->post('/provisioning/create', $payload);
            $customerId = (int)($result['userId'] ?? 0);
            $created = true;
        } catch (EchoCallApiException $e) {
            if ($e->status !== 409) {
                throw $e;
            }
            // The email exists already. Link it only if it is one of our own customers.
            $customerId = echocall_findCustomerByEmail($api, (string)$payload['email']);
            if ($customerId === 0) {
                return 'An EchoCall account with this email exists but does not belong to your partner account.';
            }
            $created = false;
        }
        if ($customerId <= 0) {
            return 'EchoCall did not return a customer id.';
        }

        EchoCallModule::saveCustomerId($params, $customerId);
        localAPI('UpdateClientProduct', [
            'serviceid' => (int)$params['serviceid'],
            'serviceusername' => (string)$payload['email'],
        ]);

        $credit = EchoCallModule::startCredit($params);
        if ($created && $credit > 0) {
            try {
                $api->post('/resellers/customers/' . $customerId . '/balance/add', [
                    'amount' => $credit,
                    'description' => 'Start credit, WHMCS service #' . (int)$params['serviceid'],
                ]);
            } catch (Throwable $e) {
                EchoCallModule::activity('Start credit not booked: ' . EchoCallModule::failure($e), (int)$params['serviceid'], (int)$params['userid']);
            }
        }

        echocall_note($params, $created ? "Account {$customerId} created" : "Existing account {$customerId} linked");
        return 'success';
    } catch (Throwable $e) {
        return EchoCallModule::failure($e);
    }
}

function echocall_SuspendAccount(array $params): string
{
    return echocall_lifecycle($params, '/provisioning/suspend', 'suspended');
}

function echocall_UnsuspendAccount(array $params): string
{
    return echocall_lifecycle($params, '/provisioning/unsuspend', 'unsuspended');
}

/** Terminating locks the account and anonymises personal data in EchoCall (GDPR). */
function echocall_TerminateAccount(array $params): string
{
    $result = echocall_lifecycle($params, '/provisioning/terminate', 'terminated');
    if ($result === 'success') {
        EchoCallModule::saveCustomerId($params, 0);
    }
    return $result;
}

function echocall_ChangePackage(array $params): string
{
    $planId = EchoCallModule::planId($params);
    if ($planId === null) {
        // Pay as you go products carry no package. Nothing to change in EchoCall.
        return 'success';
    }
    try {
        $customerId = EchoCallModule::customerId($params);
        if ($customerId <= 0) {
            return 'No EchoCall customer is linked to this service.';
        }
        EchoCallModule::api($params)->post('/provisioning/change-package', [
            'serviceId' => (string)$customerId,
            'planId' => $planId,
        ]);
        echocall_note($params, "Package changed to {$planId}");
        return 'success';
    } catch (Throwable $e) {
        return EchoCallModule::failure($e);
    }
}

function echocall_lifecycle(array $params, string $path, string $done): string
{
    try {
        $customerId = EchoCallModule::customerId($params);
        if ($customerId <= 0) {
            return 'No EchoCall customer is linked to this service.';
        }
        EchoCallModule::api($params)->post($path, ['serviceId' => (string)$customerId]);
        echocall_note($params, "Account {$customerId} {$done}");
        return 'success';
    } catch (Throwable $e) {
        return EchoCallModule::failure($e);
    }
}

/** Looks the email up among this reseller's own customers only. */
function echocall_findCustomerByEmail(EchoCallAPI $api, string $email): int
{
    $email = strtolower(trim($email));
    for ($page = 1; $page <= 50; $page++) {
        $list = $api->get('/resellers/customers', ['page' => $page, 'perPage' => 100]);
        $rows = $list['data'] ?? [];
        foreach ($rows as $row) {
            if (strtolower((string)($row['user']['email'] ?? '')) === $email) {
                return (int)($row['user']['id'] ?? $row['userId'] ?? 0);
            }
        }
        if (count($rows) < 100) {
            break;
        }
    }
    return 0;
}

function echocall_note(array $params, string $message): void
{
    if (EchoCallModule::flag($params, EchoCallModule::OPT_DEBUG)) {
        EchoCallModule::activity($message, (int)$params['serviceid'], (int)($params['userid'] ?? 0));
    }
}

// ─── Admin area ──────────────────────────────────────────────────────────────

function echocall_AdminCustomButtonArray(): array
{
    return [
        'Test Connection' => 'TestConnection',
        'Sync Status' => 'SyncStatus',
        'Relink By Email' => 'Relink',
    ];
}

function echocall_TestConnection(array $params): string
{
    try {
        $status = EchoCallModule::api($params)->get('/provisioning/onboarding-status');
        return ($status['result'] ?? '') === 'success' ? 'success' : 'EchoCall answered without a result.';
    } catch (Throwable $e) {
        return EchoCallModule::failure($e);
    }
}

/** Brings EchoCall in line with the WHMCS status of the service. */
function echocall_SyncStatus(array $params): string
{
    try {
        $customerId = EchoCallModule::customerId($params);
        if ($customerId <= 0) {
            return 'No EchoCall customer is linked to this service.';
        }
        $customer = EchoCallModule::api($params)->get('/resellers/customers/' . $customerId);
        $remote = (string)($customer['user']['accountStatus'] ?? '');
        $local = (string)$params['status'];
        if ($local === 'Active' && $remote === 'suspended') {
            return echocall_UnsuspendAccount($params);
        }
        if ($local === 'Suspended' && $remote !== 'suspended') {
            return echocall_SuspendAccount($params);
        }
        return 'success';
    } catch (Throwable $e) {
        return EchoCallModule::failure($e);
    }
}

/** Links a service whose link got lost (or came from 1.x) by the client's email. */
function echocall_Relink(array $params): string
{
    try {
        $customerId = echocall_findCustomerByEmail(EchoCallModule::api($params), (string)($params['clientsdetails']['email'] ?? ''));
        if ($customerId === 0) {
            return 'None of your EchoCall customers has the email of this client.';
        }
        EchoCallModule::saveCustomerId($params, $customerId);
        return 'success';
    } catch (Throwable $e) {
        return EchoCallModule::failure($e);
    }
}

/** Extra rows on the service page in the admin area: balance, usage, credit. */
function echocall_AdminServicesTabFields(array $params): array
{
    $customerId = EchoCallModule::customerId($params);
    $esc = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    if ($customerId <= 0) {
        return ['EchoCall' => 'Not linked yet. Run Create, or Relink By Email for an existing account.'];
    }
    $fields = ['EchoCall Customer' => $esc($customerId)];
    try {
        $api = EchoCallModule::api($params);
        $balance = $api->get('/resellers/customers/' . $customerId . '/balance');
        $usage = $api->get('/resellers/customers/' . $customerId . '/usage');
        $fields['EchoCall Balance'] = $esc(number_format((float)($balance['balance'] ?? 0), 2, ',', '.') . ' EUR');

        $lines = [];
        foreach (($usage['usage'] ?? []) as $row) {
            $lines[] = $esc($row['usageType'] ?? '?') . ': ' . $esc(round((float)($row['totalQuantity'] ?? 0), 2))
                . ' (revenue ' . $esc(number_format((float)($row['totalRevenue'] ?? 0), 2, ',', '.'))
                . ' EUR, margin ' . $esc(number_format((float)($row['totalMargin'] ?? 0), 2, ',', '.')) . ' EUR)';
        }
        $fields['Usage This Month'] = $lines ? implode('<br>', $lines) : 'No usage yet.';
    } catch (Throwable $e) {
        $fields['EchoCall Balance'] = $esc(EchoCallModule::failure($e));
    }
    $portal = EchoCallModule::portalUrl($params);
    if ($portal !== '') {
        $fields['Portal'] = '<a href="' . $esc($portal) . '" target="_blank" rel="noopener noreferrer">' . $esc($portal) . '</a>';
    }
    $fields['Add Credit (EUR)'] = '<input type="text" name="echocall_credit" class="form-control input-100" inputmode="decimal" autocomplete="off" placeholder="0,00">'
        . ' <input type="text" name="echocall_credit_reason" class="form-control input-300" maxlength="120" autocomplete="off" placeholder="Reason (shown to the customer)">';
    return $fields;
}

function echocall_AdminServicesTabFieldsSave(array $params): void
{
    $amount = EchoCallModule::amount((string)($_POST['echocall_credit'] ?? ''));
    if ($amount <= 0) {
        return;
    }
    $customerId = EchoCallModule::customerId($params);
    if ($customerId <= 0) {
        return;
    }
    $reason = trim(strip_tags((string)($_POST['echocall_credit_reason'] ?? '')));
    try {
        EchoCallModule::api($params)->post('/resellers/customers/' . $customerId . '/balance/add', [
            'amount' => $amount,
            'description' => $reason !== '' ? mb_substr($reason, 0, 120) : 'Credit from your provider',
        ]);
        EchoCallModule::activity(sprintf('Admin credited %.2f EUR', $amount), (int)$params['serviceid'], (int)$params['userid']);
    } catch (Throwable $e) {
        EchoCallModule::activity('Admin credit failed: ' . EchoCallModule::failure($e), (int)$params['serviceid'], (int)$params['userid']);
    }
}

// ─── Client area ─────────────────────────────────────────────────────────────

function echocall_ClientArea(array $params): array
{
    if (EchoCallClientArea::isActionRequest()) {
        EchoCallClientArea::handleAction($params);
    }
    return EchoCallClientArea::render($params);
}
