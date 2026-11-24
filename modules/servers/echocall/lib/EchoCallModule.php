<?php
/**
 * Shared helpers of the EchoCall provisioning module: settings, the link
 * between a WHMCS service and its EchoCall customer, the portal address and
 * a small cache on top of WHMCS's own transient data store.
 *
 * @package EchoCall
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use WHMCS\Database\Capsule;

final class EchoCallModule
{
    /** Name of the custom field that stores the EchoCall customer id (unchanged since 1.x). */
    public const SERVICE_FIELD = 'EchoCall Service ID';

    public const LANGUAGES = ['de', 'en', 'fr'];

    // Positions of the product module settings. WHMCS stores them by position,
    // so these numbers must never change between releases.
    public const OPT_API_KEY = 1;
    public const OPT_BASE_URL = 2;
    public const OPT_LANGUAGE = 3;
    public const OPT_WELCOME_MAIL = 4;
    public const OPT_DEBUG = 5;
    public const OPT_PLAN = 6;
    public const OPT_PORTAL_URL = 7;
    public const OPT_UPGRADE_LINK = 8;
    public const OPT_THEME = 9;
    public const OPT_START_CREDIT = 10;
    public const OPT_TOPUP_AMOUNTS = 11;

    public static function option(array $params, int $position, string $default = ''): string
    {
        $value = $params['configoption' . $position] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    public static function flag(array $params, int $position): bool
    {
        $value = strtolower(self::option($params, $position));
        return in_array($value, ['on', 'yes', '1', 'true'], true);
    }

    public static function api(array $params): EchoCallAPI
    {
        return new EchoCallAPI(
            self::option($params, self::OPT_API_KEY),
            self::option($params, self::OPT_BASE_URL, EchoCallAPI::DEFAULT_BASE_URL)
        );
    }

    /** The API client scoped to the customer of this service. */
    public static function customerApi(array $params): EchoCallAPI
    {
        return self::api($params)->asCustomer(self::customerId($params));
    }

    public static function language(array $params): string
    {
        $language = strtolower(self::option($params, self::OPT_LANGUAGE, 'de'));
        return in_array($language, self::LANGUAGES, true) ? $language : 'de';
    }

    /** The plan id from the product settings ("Label|id" or a bare id); null means pay as you go. */
    public static function planId(array $params): ?int
    {
        $raw = self::option($params, self::OPT_PLAN);
        if (str_contains($raw, '|')) {
            $raw = substr($raw, strrpos($raw, '|') + 1);
        }
        $raw = trim($raw);
        return ctype_digit($raw) && (int)$raw > 0 ? (int)$raw : null;
    }

    public static function startCredit(array $params): float
    {
        return self::amount(self::option($params, self::OPT_START_CREDIT, '0'));
    }

    /** Top up amounts in EUR the client may pick, smallest first, at most six. */
    public static function topupAmounts(array $params): array
    {
        $amounts = [];
        foreach (explode(',', self::option($params, self::OPT_TOPUP_AMOUNTS)) as $part) {
            $amount = self::amount($part);
            if ($amount >= 1 && $amount <= 10000) {
                $amounts[number_format($amount, 2, '.', '')] = $amount;
            }
        }
        $amounts = array_values($amounts);
        sort($amounts, SORT_NUMERIC);
        return array_slice($amounts, 0, 6);
    }

    /** Reads "12,50" and "12.50" alike; anything else is 0. */
    public static function amount(string $raw): float
    {
        $raw = str_replace(',', '.', trim($raw));
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $raw)) {
            return 0.0;
        }
        return round((float)$raw, 2);
    }

    /**
     * Module parameters for a service outside a module call (hooks, cron).
     * Only what this module reads: the product settings and the service ids.
     */
    public static function paramsForService(int $serviceId): array
    {
        $service = Capsule::table('tblhosting')->where('id', $serviceId)->first(['id', 'userid', 'packageid', 'domainstatus']);
        if (!$service) {
            throw new EchoCallApiException('Service #' . $serviceId . ' not found.', 0, 'no_service');
        }
        $product = Capsule::table('tblproducts')->where('id', (int)$service->packageid)->first();
        if (!$product || ($product->servertype ?? '') !== 'echocall') {
            throw new EchoCallApiException('Service #' . $serviceId . ' is not an EchoCall product.', 0, 'no_service');
        }
        $params = [
            'serviceid' => (int)$service->id,
            'userid' => (int)$service->userid,
            'pid' => (int)$service->packageid,
            'status' => (string)$service->domainstatus,
            'customfields' => [],
        ];
        for ($i = 1; $i <= 11; $i++) {
            $params['configoption' . $i] = (string)($product->{'configoption' . $i} ?? '');
        }
        $params['configoption1'] = self::storedApiKey($params['configoption1']);
        return $params;
    }

    /** WHMCS keeps password settings encrypted in tblproducts; module calls get them decrypted. */
    public static function storedApiKey(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || str_starts_with($raw, 'eck_')) {
            return $raw;
        }
        if (function_exists('decrypt')) {
            $plain = trim((string)decrypt($raw));
            if (str_starts_with($plain, 'eck_')) {
                return $plain;
            }
        }
        return $raw;
    }

    // ── Service link ─────────────────────────────────────────────────────────

    /** The EchoCall customer id of this service, 0 when none is linked. */
    public static function customerId(array $params): int
    {
        $fromParams = $params['customfields'][self::SERVICE_FIELD] ?? null;
        if (is_string($fromParams) && ctype_digit(trim($fromParams))) {
            return (int)trim($fromParams);
        }
        $fieldId = self::serviceFieldId((int)($params['pid'] ?? 0), false);
        if ($fieldId === 0) {
            return 0;
        }
        $value = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $fieldId)
            ->where('relid', (int)$params['serviceid'])
            ->value('value');
        $value = trim((string)$value);
        return ctype_digit($value) ? (int)$value : 0;
    }

    public static function saveCustomerId(array $params, int $customerId): void
    {
        $fieldId = self::serviceFieldId((int)$params['pid'], true);
        $serviceId = (int)$params['serviceid'];
        $value = $customerId > 0 ? (string)$customerId : '';
        $exists = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $fieldId)
            ->where('relid', $serviceId)
            ->exists();
        if ($exists) {
            Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $fieldId)
                ->where('relid', $serviceId)
                ->update(['value' => $value]);
        } else {
            Capsule::table('tblcustomfieldsvalues')->insert([
                'fieldid' => $fieldId,
                'relid' => $serviceId,
                'value' => $value,
            ]);
        }
    }

    /** The custom field of the product that holds the link, created on first use (admin only). */
    private static function serviceFieldId(int $productId, bool $create): int
    {
        if ($productId <= 0) {
            return 0;
        }
        $id = (int)Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $productId)
            ->where('fieldname', self::SERVICE_FIELD)
            ->value('id');
        if ($id > 0 || !$create) {
            return $id;
        }
        return (int)Capsule::table('tblcustomfields')->insertGetId([
            'type' => 'product',
            'relid' => $productId,
            'fieldname' => self::SERVICE_FIELD,
            'fieldtype' => 'text',
            'description' => 'Set by the EchoCall module. Do not change.',
            'fieldoptions' => '',
            'regexpr' => '',
            'adminonly' => 'on',
            'required' => '',
            'showorder' => '',
            'showinvoice' => '',
            'sortorder' => 0,
        ]);
    }

    // ── Portal and cache ─────────────────────────────────────────────────────

    /** Where the customer signs in: the override from the settings, else the reseller portal. */
    public static function portalUrl(array $params): string
    {
        $override = self::option($params, self::OPT_PORTAL_URL);
        if ($override !== '' && preg_match('#^https://#i', $override)) {
            return $override;
        }
        $cacheKey = 'portal_' . self::keyFingerprint($params);
        $cached = self::cacheGet($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }
        $url = '';
        try {
            $status = self::api($params)->get('/provisioning/onboarding-status');
            $url = (string)($status['reseller']['portalUrl'] ?? '');
        } catch (Throwable $e) {
            return '';
        }
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = '';
        }
        self::cacheSet($cacheKey, $url, 300);
        return $url;
    }

    /** A fingerprint of the API key, so caches of two resellers never mix. */
    public static function keyFingerprint(array $params): string
    {
        return substr(hash('sha256', self::option($params, self::OPT_API_KEY)), 0, 16);
    }

    public static function cacheGet(string $key)
    {
        if (!class_exists('\\WHMCS\\TransientData')) {
            return null;
        }
        $raw = \WHMCS\TransientData::getInstance()->retrieve('echocall_' . $key);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) && array_key_exists('v', $data) ? $data['v'] : null;
    }

    public static function cacheSet(string $key, $value, int $ttl): void
    {
        if (!class_exists('\\WHMCS\\TransientData')) {
            return;
        }
        \WHMCS\TransientData::getInstance()->store(
            'echocall_' . $key,
            (string)json_encode(['v' => $value], JSON_UNESCAPED_SLASHES),
            $ttl
        );
    }

    public static function activity(string $message, int $serviceId = 0, int $clientId = 0): void
    {
        if (function_exists('logActivity')) {
            $suffix = $serviceId > 0 ? " - Service ID: {$serviceId}" : '';
            logActivity('EchoCall: ' . $message . $suffix, $clientId);
        }
    }

    /** A message safe to hand to WHMCS as a module result. */
    public static function failure(Throwable $e): string
    {
        $message = $e instanceof EchoCallApiException ? $e->getMessage() : 'Unexpected error: ' . $e->getMessage();
        return mb_substr(preg_replace('/\s+/', ' ', $message) ?? '', 0, 250);
    }
}
