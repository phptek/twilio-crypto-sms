<?php

namespace SMSCryptoApp\Form;

use SilverStripe\Forms\RequiredFields;

/**
 * Basic validation that allows us to a). Check for required fields using SilverStripe's
 * very basic {@link RequiredFields} class and b). by passing an arbitrary callback
 * to the constructor that must return a boolean true for callers to be confident
 * that the relevant form data is valid.
 */
class MessageValidator extends RequiredFields
{
    /**
     * @var callable
     */
    protected $callback;

    /**
     * @param  array    $fields
     * @param  callable $callable
     * @return void
     */
    public function __construct(array $fields, callable $callback)
    {
        $this->callback = $callback;

        parent::__construct($fields);
    }

    /**
     * {@inheritdoc}
     *
     * @param  array   $data
     * @return boolean
     */
    public function php($data) : bool
    {
        if ($valid = parent::php($data) && !call_user_func($this->callback)) {
            $this->validationError(
                'Message',
                'Have you paid via your smartphone app yet? Please try again in a few seconds.',
                'error'
            );

            return false;
        }

        return true;
    }

}
