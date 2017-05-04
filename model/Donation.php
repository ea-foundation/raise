<?php
namespace Eas;

use Validator;

class Donation
{
    /** @var array */
    protected $data;

    /** @var array */
    protected $requiredFields = array(
        'form'      => 'string',
        'mode'      => array(
                           'type'    => 'choices',
                           'choices' => array('sandbox', 'live'),
                       ),
        'language'  => array(
                           'type'    => 'string',
                           'options' => array(
                                'length' => 2,
                            ),
                       ),
        'url'       => 'url',
        'amount'    => 'numeric',
        'currency'  => array(
                           'type'    => 'string',
                           'options' => array(
                                'length' => 3,
                            ),
                       ),
        'frequency' => array(
                           'type'    => 'choices',
                           'choices' => array('once', 'monthly'),
                       ),
        'email'     => 'email',
        'name'      => 'string',
        'type'      => 'string', // Legacy, see payment
        'payment'   => array(
                           'type'    => 'choices',
                           'choices' => array(
                                'Stripe',
                                'PayPal',
                                'Skrill',
                                'BitPay',
                                'GoCardless',
                                'Bank Transfer',
                            ),
                       ),
        'time'      => 'string',
    );

    /** @var array */
    protected $optionalFields = array(
        'purpose'     => 'string',
        'anonymous'   => 'boolean',
        'mailinglist' => 'boolean',
        'tax_receipt' => 'boolean',
        'address'     => 'string',
        'city'        => 'string',
        'zip'         => 'string',
        'country'     => 'string',
        'comment'     => 'string',
    );

    /**
     * Constructor
     *
     * @param array  $data Data from form
     */
    public function __construct(array $data)
    {
        // Get default values
        $defaultValues      = array_fill_keys(array_keys($this->requiredFields), null);
        $this->data         = $data + $defaultValues;

        // Handle legacy variable type
        if (isset($this->data['payment'])) {
            $this->data['type'] = $this->data['payment'];
        }
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        // Return undefined
        return undefined;
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * To array
     *
     * @return array
     */
    public function toArray()
    {
        $fields = $this->requiredFields + $this->optionalFields;
        return array_filter($this->data, function($val, $key) use ($fields) {
            return $val && array_key_exists($key, $fields);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Is valid?
     *
     * @return bool
     */
    public function isValid()
    {
        // Validate data
        $fields = $this->requiredFields + $this->optionalFields;
        return array_reduce(array_keys($data), function($carry, $key) use ($data, $fields) {
            return $carry && Validator::validate($data[$key], $fields[$key]);
        }, true);
    }

    protected function getFieldType($name)
    {
        if (array_key_exists($name, $this->requiredFields)) {
            return $this->requiredFields[$name];
        }

        if (array_key_exists($name, $this->optionalFields)) {
            return $this->optionalFields[$name];
        }

        return null;
    }
}
