<?php
namespace Eas;

class Validator
{
    /**
     * Validate
     *
     * @param mixed        $value
     * @param string|array $type
     * @param array        $options
     * @return bool 
     */
    public static function validate($value, $type, $options = array())
    {
        if (is_array($type)) {
            switch ($type['type']) {
                case 'url':
                    return self::validateString($value);
                case 'boolean':
                    return self::validateBoolean($value);
                case 'numeric':
                    return self::validateNumeric($value);
                case 'choices':
                    return self::validateChoices($value, $type['choices']);
                case 'string':
                    return self::validateString($value, $type['options']);
                default:
                    return false;
            }
        } else {
            switch ($type) {
                case 'url':
                    return self::validateUrl($value);
                case 'email':
                    return self::validateEmail($value);
                case 'boolean':
                    return self::validateBoolean($value);
                case 'numeric':
                    return self::validateNumeric($value);
                case 'string':
                    return self::validateString($value);
                default:
                    return false;
            }
        }
    }

    /**
     * Validate URL
     *
     * @param string $url
     * @return bool
     */
    public static function validateUrl($url)
    {
        return preg_match("/^https?:\/\/[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $url);
    }

    /**
     * Validate email
     *
     * @param string $email
     * @return bool
     */
    public static function validateEmail($email)
    {
        return !!filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate bool
     *
     * @param string $bool
     * @return bool
     */
    public static function validateBoolean($bool)
    {
        return filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Validate numeric
     *
     * @param string $numeric
     * @return bool
     */
    public static function validateNumeric($numeric)
    {
        return is_numeric($numeric);
    }

    /**
     * Validate numeric
     *
     * @param string $numeric
     * @param array  $options
     * @return bool
     */
    public static function validateString($string, $options = array()) {
        return is_string($string) 
               && (!isset($options['length']) || mb_strlen($string) == $options['length']);
    }

    /**
     * Validate enum
     *
     * @param string $choice
     * @param array  $choices
     * @return bool
     */
    public static function validateChoices($choice, array $choices)
    {
        return in_array($choice, $choices);
    }
}
