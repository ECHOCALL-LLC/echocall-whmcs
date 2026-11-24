<?php
/**
 * The client area of an EchoCall service: the pages and the few actions a
 * client may run from WHMCS.
 *
 * Every EchoCall call made here runs as the customer of this service
 * (X-EchoCall-Customer), so the hub limits it to that customer's own agents,
 * chatbots and numbers. Actions come from a fixed list, carry a per service
 * token bound to the WHMCS session and only run on active services. Anything
 * beyond that list is done in the EchoCall portal, which the client reaches
 * with one click.
 *
 * @package EchoCall
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

final class EchoCallClientArea
{
    public const PAGES = ['overview', 'agents', 'chatbots', 'numbers', 'knowledge', 'integrations', 'wordpress'];

    private const ACTIONS = [
        'agent.status', 'chatbot.status', 'chatbot.embed',
        'number.assign', 'number.label',
        'knowledge.add', 'knowledge.delete',
        'topup.create',
    ];

    // ── Pages ────────────────────────────────────────────────────────────────

    public static function render(array $params): array
    {
        $lang = EchoCallI18n::detect($params);
        $t = EchoCallI18n::strings($lang);
        $serviceId = (int)$params['serviceid'];
        $page = (string)($_GET['ecpage'] ?? 'overview');
        if (!in_array($page, self::PAGES, true)) {
            $page = 'overview';
        }

        $base = 'clientarea.php?action=productdetails&id=' . $serviceId;
        $theme = EchoCallModule::option($params, EchoCallModule::OPT_THEME, 'auto');
        $vars = [
            't' => $t,
            // The product name of the hoster, so a white label offer never shows our brand.
            'title' => self::productName($params),
            'lang' => $lang,
            'page' => $page,
            'baseUrl' => $base,
            'assetBase' => 'modules/servers/echocall/assets',
            'version' => EchoCallAPI::VERSION,
            'theme' => in_array($theme, ['auto', 'dark', 'light'], true) ? $theme : 'auto',
            'actionToken' => self::token($serviceId),
            // WHMCS's own CSRF token, sent along in case the installation checks it on POST.
            'whmcsToken' => function_exists('generate_token') ? (string)generate_token('plain') : '',
            'serviceStatus' => (string)$params['status'],
            'portalUrl' => EchoCallModule::portalUrl($params),
            'error' => '',
            'nav' => self::nav($t, $base, $page),
        ];

        if ((string)$params['status'] !== 'Active') {
            return self::view(['page' => 'status'] + $vars);
        }
        if (EchoCallModule::customerId($params) <= 0) {
            $vars['error'] = $t['error_not_linked'];
            return self::view(['page' => 'status'] + $vars);
        }

        try {
            $api = EchoCallModule::customerApi($params);
            $vars += self::pageData($page, $api, $params, $lang);
        } catch (EchoCallApiException $e) {
            $vars['error'] = match (true) {
                $e->errorCode === 'customer_suspended' => $t['error_suspended'],
                $e->errorCode === 'invalid_input', $e->status === 404 => $t['error_not_found'],
                default => $t['error_unreachable'],
            };
            EchoCallModule::activity('Client area page ' . $page . ' failed: ' . EchoCallModule::failure($e), $serviceId, (int)$params['userid']);
        }

        return self::view($vars);
    }

    /**
     * All module variables live under {$ec} so none of them can replace a
     * variable of the WHMCS page around it (for example its CSRF {$token}).
     */
    private static function view(array $vars): array
    {
        return ['templatefile' => 'templates/clientarea/main', 'vars' => ['ec' => $vars]];
    }

    private static function pageData(string $page, EchoCallAPI $api, array $params, string $lang): array
    {
        switch ($page) {
            case 'agents':
                return ['agents' => self::rows($api->get('/agents', ['perPage' => 100]))];

            case 'chatbots':
                return ['chatbots' => self::rows($api->get('/chatbots', ['perPage' => 100]))];

            case 'numbers':
                $numbers = array_map(static function (array $n): array {
                    // The list carries the plain agent id, the picker uses agent_<id>.
                    $n['agentRef'] = !empty($n['voiceAgentId']) ? 'agent_' . (int)$n['voiceAgentId'] : '';
                    return $n;
                }, self::rows($api->get('/phone-numbers', ['perPage' => 100])));
                return [
                    'numbers' => $numbers,
                    'assignable' => self::rows($api->get('/phone-numbers/assignable-agents')),
                ];

            case 'knowledge':
                $kind = ($_GET['kind'] ?? '') === 'chatbot' ? 'chatbot' : 'agent';
                $ref = (string)($_GET['ref'] ?? '');
                $ref = self::ref($kind, $ref);
                $owner = $api->get(($kind === 'agent' ? '/agents/' : '/chatbots/') . $ref);
                $items = array_map(static function (array $item): array {
                    $item['label'] = (string)($item['title'] ?? '') !== '' ? (string)$item['title']
                        : (string)($item['sourceUrl'] ?? $item['fileName'] ?? mb_substr((string)($item['contentSnippet'] ?? ''), 0, 120));
                    return $item;
                }, self::rows($api->get(($kind === 'agent' ? '/agents/' : '/chatbots/') . $ref . '/knowledge')));
                return [
                    'kind' => $kind,
                    'ref' => $ref,
                    'ownerName' => (string)($owner['name'] ?? $owner['data']['name'] ?? ''),
                    'items' => $items,
                ];

            case 'integrations':
                return ['integrations' => self::rows($api->get('/integrations'))];

            case 'wordpress':
                // The plugin loads the chatbots itself with an API key. This list shows the client which
                // ones it will offer and the shortcode for each. The release ZIP bundles the plugin; a
                // module copied from the repository has no bundle and links the latest plugin release.
                $bundled = is_file(dirname(__DIR__) . '/wordpress-plugin/echocall-chat-widget.zip');
                return [
                    'pluginLocal' => $bundled,
                    'pluginUrl' => $bundled
                        ? 'modules/servers/echocall/wordpress-plugin/echocall-chat-widget.zip'
                        : 'https://github.com/ECHOCALL-LLC/echocall-wordpress/releases/latest/download/echocall-chat-widget.zip',
                    'chatbots' => self::rows($api->get('/chatbots', ['perPage' => 100])),
                ];

            default:
                return self::overview($api, $params, $lang);
        }
    }

    private static function overview(EchoCallAPI $api, array $params, string $lang): array
    {
        $limits = $api->get('/users/me/limits');
        $usage = $api->get('/users/me/usage');
        $count = static function (array $list): int {
            return (int)($list['pagination']['total'] ?? count($list['data'] ?? []));
        };
        $amounts = EchoCallModule::topupAmounts($params);
        $upgrade = EchoCallModule::flag($params, EchoCallModule::OPT_UPGRADE_LINK)
            ? 'upgrade.php?type=package&id=' . (int)$params['serviceid'] : '';

        return [
            'balance' => self::money((float)($limits['balanceEur'] ?? 0), $lang),
            'balanceLow' => (float)($limits['balanceEur'] ?? 0) < 5,
            'planName' => (string)($limits['plan']['name'] ?? ''),
            'voiceRemaining' => $limits['voiceMinutesRemaining'] ?? null,
            'chatRemaining' => $limits['chatConversationsRemaining'] ?? null,
            'voiceUsed' => (int)round((float)($usage['voiceMinutesUsed'] ?? 0)),
            'chatUsed' => (int)($usage['chatSessionsUsed'] ?? 0),
            'agentCount' => $count($api->get('/agents', ['perPage' => 1])),
            'chatbotCount' => $count($api->get('/chatbots', ['perPage' => 1])),
            'numberCount' => $count($api->get('/phone-numbers', ['perPage' => 1])),
            'topupAmounts' => array_map(fn ($a) => ['value' => number_format($a, 2, '.', ''), 'label' => self::money($a, $lang)], $amounts),
            'topups' => array_map(function ($row) use ($lang) {
                $row['amountLabel'] = self::money($row['amount'], $lang);
                return $row;
            }, EchoCallTopups::recent((int)$params['serviceid'])),
            'upgradeUrl' => $upgrade,
        ];
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public static function isActionRequest(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ec_action']);
    }

    /** Runs one action and answers with JSON. Never returns. */
    public static function handleAction(array $params): never
    {
        $lang = EchoCallI18n::detect($params);
        $serviceId = (int)$params['serviceid'];
        $action = (string)($_POST['ec_action'] ?? '');

        try {
            if (!in_array($action, self::ACTIONS, true)) {
                self::respond(400, ['error' => EchoCallI18n::t($lang, 'error_generic')]);
            }
            $sent = (string)($_POST['ec_token'] ?? '');
            if ($sent === '' || !hash_equals(self::token($serviceId), $sent)) {
                self::respond(403, ['error' => EchoCallI18n::t($lang, 'error_session')]);
            }
            if ((string)$params['status'] !== 'Active') {
                self::respond(409, ['error' => EchoCallI18n::t($lang, 'status_inactive_body')]);
            }
            $data = self::run($action, $params, $lang);
            self::respond(200, ['ok' => true] + $data);
        } catch (EchoCallApiException $e) {
            EchoCallModule::activity('Client action ' . $action . ' failed: ' . EchoCallModule::failure($e), $serviceId, (int)$params['userid']);
            $known = [
                'agent_not_found' => 'error_agent_not_found',
                'agent_not_ready' => 'error_agent_not_ready',
                'invalid_amount' => 'error_generic',
                'currency' => 'topup_eur_only',
                'too_many_open' => 'topup_too_many',
                'customer_suspended' => 'error_suspended',
                'invalid_input' => 'error_input',
            ];
            $key = $known[$e->errorCode] ?? ($e->status === 400 ? 'error_input' : 'error_generic');
            self::respond($e->status >= 400 && $e->status < 500 ? 422 : 502, ['error' => EchoCallI18n::t($lang, $key)]);
        } catch (Throwable $e) {
            EchoCallModule::activity('Client action ' . $action . ' failed: ' . $e->getMessage(), $serviceId, (int)$params['userid']);
            self::respond(500, ['error' => EchoCallI18n::t($lang, 'error_generic')]);
        }
    }

    private static function run(string $action, array $params, string $lang): array
    {
        $in = $_POST;
        if ($action === 'topup.create') {
            $amount = EchoCallModule::amount((string)($in['amount'] ?? ''));
            $invoiceId = EchoCallTopups::createInvoice($params, $amount);
            return ['redirect' => 'viewinvoice.php?id=' . $invoiceId];
        }

        $api = EchoCallModule::customerApi($params);
        switch ($action) {
            case 'agent.status':
                $api->patch('/agents/' . self::ref('agent', (string)($in['id'] ?? '')), ['status' => self::status($in)]);
                return ['message' => EchoCallI18n::t($lang, 'saved'), 'reload' => true];

            case 'chatbot.status':
                $api->patch('/chatbots/' . self::ref('chatbot', (string)($in['id'] ?? '')), ['status' => self::status($in)]);
                return ['message' => EchoCallI18n::t($lang, 'saved'), 'reload' => true];

            case 'chatbot.embed':
                $code = $api->get('/chatbots/' . self::ref('chatbot', (string)($in['id'] ?? '')) . '/embed-code');
                return ['code' => (string)($code['data']['scriptCode'] ?? '')];

            case 'number.assign':
                $agent = trim((string)($in['agent'] ?? ''));
                $agentId = $agent === '' ? null : (int)substr(self::ref('agent', $agent), 6);
                $api->patch('/phone-numbers/' . self::ref('number', (string)($in['id'] ?? '')), ['voiceAgentId' => $agentId]);
                return ['message' => EchoCallI18n::t($lang, 'saved')];

            case 'number.label':
                $label = trim(strip_tags((string)($in['label'] ?? '')));
                if (mb_strlen($label) > 100) {
                    throw new EchoCallApiException('Label too long.', 400, 'invalid_input');
                }
                $api->patch('/phone-numbers/' . self::ref('number', (string)($in['id'] ?? '')), ['label' => $label]);
                return ['message' => EchoCallI18n::t($lang, 'saved'), 'reload' => true];

            case 'knowledge.add':
                $kind = ($in['kind'] ?? '') === 'chatbot' ? 'chatbot' : 'agent';
                $url = trim((string)($in['url'] ?? ''));
                if (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
                    throw new EchoCallApiException('Invalid URL.', 400, 'invalid_input');
                }
                $ref = self::ref($kind, (string)($in['id'] ?? ''));
                $body = $kind === 'agent' ? ['type' => 'url', 'url' => $url] : ['type' => 'url', 'content' => $url];
                $api->post(($kind === 'agent' ? '/agents/' : '/chatbots/') . $ref . '/knowledge', $body);
                return ['message' => EchoCallI18n::t($lang, 'knowledge_added'), 'reload' => true];

            case 'knowledge.delete':
                $kind = ($in['kind'] ?? '') === 'chatbot' ? 'chatbot' : 'agent';
                $ref = self::ref($kind, (string)($in['id'] ?? ''));
                $item = self::ref('item', (string)($in['item'] ?? ''));
                $api->delete(($kind === 'agent' ? '/agents/' : '/chatbots/') . $ref . '/knowledge/' . $item);
                return ['message' => EchoCallI18n::t($lang, 'knowledge_deleted'), 'reload' => true];
        }
        throw new EchoCallApiException('Unknown action.', 400, 'invalid_input');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** A random token per service, kept in the WHMCS session. */
    public static function token(int $serviceId): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (!isset($_SESSION['echocall_tokens']) || !is_array($_SESSION['echocall_tokens'])) {
            $_SESSION['echocall_tokens'] = [];
        }
        if (empty($_SESSION['echocall_tokens'][$serviceId])) {
            $_SESSION['echocall_tokens'][$serviceId] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['echocall_tokens'][$serviceId];
    }

    /** Validates an id from the request: agent_12 for agents, a plain number otherwise. */
    private static function ref(string $kind, string $raw): string
    {
        $raw = trim($raw);
        if ($kind === 'agent') {
            if (preg_match('/^(agent_)?([1-9]\d{0,15})$/', $raw, $m)) {
                return 'agent_' . $m[2];
            }
        } elseif (preg_match('/^[1-9]\d{0,15}$/', $raw)) {
            return $raw;
        }
        throw new EchoCallApiException('Invalid id.', 400, 'invalid_input');
    }

    private static function status(array $in): string
    {
        $status = (string)($in['status'] ?? '');
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new EchoCallApiException('Invalid status.', 400, 'invalid_input');
        }
        return $status;
    }

    /** The list of a paged answer ({data: [...]}) or a plain array answer. */
    private static function rows(array $answer): array
    {
        $rows = array_key_exists('data', $answer) && is_array($answer['data']) ? $answer['data'] : $answer;
        return array_values(array_filter($rows, 'is_array'));
    }

    private static function productName(array $params): string
    {
        try {
            $name = isset($params['model']) && is_object($params['model']) ? (string)($params['model']->product->name ?? '') : '';
        } catch (Throwable $e) {
            $name = '';
        }
        return $name !== '' ? $name : 'EchoCall';
    }

    public static function money(float $amount, string $lang): string
    {
        $formatted = $lang === 'en'
            ? number_format($amount, 2, '.', ',')
            : number_format($amount, 2, ',', $lang === 'fr' ? "\u{202F}" : '.');
        return $lang === 'en' ? '€' . $formatted : $formatted . "\u{00A0}€";
    }

    private static function nav(array $t, string $base, string $page): array
    {
        $items = [];
        foreach (['overview', 'agents', 'chatbots', 'numbers', 'integrations', 'wordpress'] as $key) {
            $items[] = [
                'key' => $key,
                'label' => $t['nav_' . $key] ?? $key,
                'url' => $base . '&ecpage=' . $key,
                'active' => $page === $key || ($page === 'knowledge' && $key === (($_GET['kind'] ?? '') === 'chatbot' ? 'chatbots' : 'agents')),
            ];
        }
        return $items;
    }

    private static function respond(int $status, array $body): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
