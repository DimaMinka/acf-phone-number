<?php

namespace Log1x\AcfPhoneNumber;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberToCarrierMapper;
use libphonenumber\PhoneNumberToTimeZonesMapper;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use libphonenumber\NumberParseException;

class PhoneNumber extends \acf_field
{
    /**
     * Field Name
     *
     * @var string
     */
    public $name = 'phone_number';

    /**
     * Field Label
     *
     * @var string
     */
    public $label = 'Phone Number';

    /**
     * The field type.
     *
     * @var string
     */
    public $type = 'phone';

    /**
     * Field Category
     *
     * @var string
     */
    public $category = 'basic';

    /**
     * Settings
     *
     * @var object
     */
    protected $settings;

    /**
     * Create a new instance of AcfPhoneNumber.
     *
     * @param  array $settings
     * @return void
     */
    public function __construct($settings)
    {
        $this->settings = (object) $settings;
        $this->phone = PhoneNumberUtil::getInstance();
        $this->carrier = PhoneNumberToCarrierMapper::getInstance();
        $this->location = PhoneNumberOfflineGeocoder::getInstance();
        $this->timezone = PhoneNumberToTimeZonesMapper::getInstance();

        parent::__construct();
    }

    /**
     * Create the HTML interface for your field.
     *
     * @param  array $field
     * @return void
     */
    public function render_field($field)
    {
        if (is_string($value = $field['value']) && ! empty(trim($value))) {
            $field['value'] = [
                'number' => $value,
                'country' => '',
            ];
        }

        echo sprintf(
            '<input type="tel" name="%s[number]" value="%s" />',
            $field['name'],
            $field['value']['number']
        );

        echo sprintf(
            '<input type="hidden" name="%s[country]" value="%s" />',
            $field['name'],
            $field['value']['country']
        );
    }

    /**
     * This action is called in the admin_enqueue_scripts action on the edit screen where
     * your field is created.
     *
     * @return void
     */
    public function input_admin_enqueue_scripts()
    {
        wp_enqueue_script('acf-' . $this->name, $this->settings->uri . 'dist/js/field.js', ['acf-input'], null);
        wp_enqueue_style('acf-' . $this->name, $this->settings->uri . 'dist/css/field.css', [], null);
    }

    /**
     * This filter is applied to the $value after it is loaded from the database and
     * before it is returned to the template.
     *
     * @param  mixed $value
     * @param  mixed $post_id
     * @param  array $field
     * @return mixed
     */
    public function format_value($value, $post_id, $field)
    {
        try {
            $phone = $this->phone->parse($value['number'], $value['country']);
        } catch (NumberParseException $e) {
            return $value;
        }

        return (object) array_merge($value, [
            'uri' => 'tel:' . $this->phone->format($phone, PhoneNumberFormat::E164),
            'e164' => $this->phone->format($phone, PhoneNumberFormat::E164),
            'rfc3966' => $this->phone->format($phone, PhoneNumberFormat::RFC3966),
            'national' => $this->phone->format($phone, PhoneNumberFormat::NATIONAL),
            'international' => $this->phone->format($phone, PhoneNumberFormat::INTERNATIONAL),
            'carrier' => $this->carrier->getNameForNumber($phone, 'en'),
            'location' => $this->location->getDescriptionForNumber($phone, 'en_US'),
            'timezone' => $this->timezone->getTimeZonesForNumber($phone),
        ]);
    }

    /**
     * This filter is used to perform validation on the value prior to saving.
     *
     * @param  boolean $valid
     * @param  mixed   $value
     * @param  array   $field
     * @param  array   $input
     * @return boolean
     */
    public function validate_value($valid, $value, $field, $input)
    {
        if (! is_array($value) || empty($value['number'])) {
            return 'The phone number entered is not valid.';
        }

        if (empty($value['country'])) {
            return 'The phone number country entered is not valid.';
        }

        try {
            $phone = $this->phone->parse($value['number'], $value['country']);
        } catch (NumberParseException $e) {
            return 'The phone number entered is not valid for this country.';
        }

        return $this->phone->isValidNumber($phone) ?
            $valid : 'The phone number entered is not valid.';
    }
}
