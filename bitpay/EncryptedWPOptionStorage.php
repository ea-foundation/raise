<?php
namespace EAS\Bitpay;

use \Bitpay\Storage\StorageInterface;

if (!defined('ABSPATH')) exit;

class EncryptedWPOptionStorage implements StorageInterface
{
    /**
     * @var string
     */
    private $password;

    /**
     * Initialization Vector
     */
    const IV = '0000000000000000';

    /**
     * @var string
     */
    const METHOD = 'AES-128-CBC';

    /**
     * @var int
     */
    const OPENSSL_RAW_DATA = 1;

    /**
     * @param string $password
     */
    public function __construct($password)
    {
        $this->password = $password;
    }

    /**
     * @inheritdoc
     */
    public function persist(\Bitpay\KeyInterface $key)
    {
        $id = $key->getId();
        $data    = serialize($key);
        $encoded = bin2hex(openssl_encrypt(
            $data,
            self::METHOD,
            $this->password,
            1,
            self::IV
        ));

        update_option($id, $encoded);
    }

    /**
     * @inheritdoc
     */
    public function load($id)
    {
        $encoded = get_option($id);
        $decoded = openssl_decrypt(\Bitpay\Util\Util::binConv($encoded), self::METHOD, $this->password, 1, self::IV);

        if (false === $decoded) {
            throw new \Exception('Could not decode key');
        }

        return unserialize($decoded);
    }
}
