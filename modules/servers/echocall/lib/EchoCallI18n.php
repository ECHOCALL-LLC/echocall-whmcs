<?php
/**
 * Texts of the client area and the admin tab in German, English and French.
 *
 * The module keeps its own catalogue (lang/*.php return arrays) instead of
 * writing into WHMCS's global $_LANG, so nothing can collide with a theme or
 * another module. The language follows the client's WHMCS language and falls
 * back to the language set on the product.
 *
 * @package EchoCall
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

final class EchoCallI18n
{
    private const FILES = ['de' => 'german', 'en' => 'english', 'fr' => 'french'];

    /** @var array<string,array<string,string>> */
    private static array $loaded = [];

    public static function detect(array $params): string
    {
        $candidates = [
            $params['clientsdetails']['language'] ?? '',
            class_exists('\\WHMCS\\Session') ? (string)\WHMCS\Session::get('Language') : '',
        ];
        foreach ($candidates as $name) {
            $code = self::codeFor((string)$name);
            if ($code !== '') {
                return $code;
            }
        }
        return EchoCallModule::language($params);
    }

    /** Maps a WHMCS language name ("german", "french", ...) to de, en or fr. */
    public static function codeFor(string $name): string
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return '';
        }
        foreach (self::FILES as $code => $file) {
            if ($name === $file || $name === $code || str_starts_with($name, $file)) {
                return $code;
            }
        }
        return in_array($name, ['deutsch', 'german-de'], true) ? 'de' : (in_array($name, ['francais', 'français'], true) ? 'fr' : '');
    }

    /** @return array<string,string> */
    public static function strings(string $code): array
    {
        if (!isset(self::FILES[$code])) {
            $code = 'en';
        }
        if (!isset(self::$loaded[$code])) {
            $english = self::load('en');
            self::$loaded[$code] = $code === 'en' ? $english : array_merge($english, self::load($code));
        }
        return self::$loaded[$code];
    }

    public static function t(string $code, string $key, array $replace = []): string
    {
        $text = self::strings($code)[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace('{' . $name . '}', (string)$value, $text);
        }
        return $text;
    }

    /** @return array<string,string> */
    private static function load(string $code): array
    {
        $file = dirname(__DIR__) . '/lang/' . self::FILES[$code] . '.php';
        if (!is_file($file)) {
            return [];
        }
        $strings = require $file;
        return is_array($strings) ? $strings : [];
    }
}
