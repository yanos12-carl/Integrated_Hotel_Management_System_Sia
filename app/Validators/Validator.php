<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\HttpException;

/**
 * Server-side validation. Rules: "required|string|min:3|max:60|email|int|numeric|bool|array|in:a,b|regex:/../|nullable".
 * Returns only the declared fields (trimmed/cast). Throws HttpException 422 with per-field messages.
 */
final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        $clean  = [];
        foreach ($rules as $field => $ruleString) {
            $rs    = explode('|', $ruleString);
            $label = str_replace('_', ' ', $field);
            $val   = $data[$field] ?? null;
            if (is_string($val)) {
                $val = trim($val);
            }
            if ($val === null || $val === '' || $val === []) {
                if (in_array('required', $rs, true)) {
                    $errors[$field] = "The $label field is required.";
                }
                $clean[$field] = null;
                continue;
            }
            $isInt = in_array('int', $rs, true);
            foreach ($rs as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $err = match ($name) {
                    'string'  => is_string($val) ? null : "The $label must be text.",
                    'int'     => filter_var($val, FILTER_VALIDATE_INT) !== false ? null : "The $label must be a whole number.",
                    'numeric' => is_numeric($val) ? null : "The $label must be a number.",
                    'bool'    => in_array($val, [true, false, 0, 1, '0', '1', 'true', 'false'], true) ? null : "The $label must be true or false.",
                    'email'   => (is_string($val) && filter_var($val, FILTER_VALIDATE_EMAIL)) ? null : "The $label must be a valid email address.",
                    'array'   => is_array($val) ? null : "The $label must be a list.",
                    'in'      => in_array((string) $val, explode(',', (string) $arg), true) ? null : "The $label is not an allowed value.",
                    'regex'   => (is_string($val) && preg_match((string) $arg, $val)) ? null : "The $label has an invalid format.",
                    'min'     => self::size($val, $isInt) >= (float) $arg ? null : "The $label must be at least $arg" . ($isInt || is_numeric($val) && !is_string($val) ? '.' : ' characters.'),
                    'max'     => self::size($val, $isInt) <= (float) $arg ? null : "The $label must not exceed $arg" . ($isInt ? '.' : ' characters.'),
                    default   => null,
                };
                if ($err !== null) {
                    $errors[$field] = $err;
                    break;
                }
            }
            if (!isset($errors[$field])) {
                $clean[$field] = match (true) {
                    $isInt                             => (int) $val,
                    in_array('numeric', $rs, true)     => (float) $val,
                    in_array('bool', $rs, true)        => in_array($val, [true, 1, '1', 'true'], true),
                    default                            => $val,
                };
            }
        }
        if ($errors) {
            throw HttpException::unprocessable($errors);
        }
        return $clean;
    }

    private static function size(mixed $v, bool $isInt): float
    {
        if ($isInt || is_int($v) || is_float($v)) {
            return (float) $v;
        }
        return is_array($v) ? (float) count($v) : (float) mb_strlen((string) $v);
    }
}
