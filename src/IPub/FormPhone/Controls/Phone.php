<?php
/**
 * Phone.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        iPublikuj:FormPhone!
 * @subpackage     Controls
 * @since          1.0.0
 *
 * @date           15.12.15
 */

namespace IPub\FormPhone\Controls;

use Nette;
use Nette\Forms;
use Nette\Localization;
use Nette\Utils;

use IPub;
use IPub\FormPhone;
use IPub\FormPhone\Exceptions;

use IPub\Phone\Phone as PhoneUtils;

use libphonenumber;

/**
 * Form phone control element
 *
 * @package        iPublikuj:FormPhone!
 * @subpackage     Controls
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @property-read string $emptyValue
 * @property-read Nette\Forms\Rules $rules
 */
class Phone extends Forms\Controls\TextInput
{
	/**
	 * Define filed attributes
	 */
	const FIELD_COUNTRY	= 'country';
	const FIELD_NUMBER	= 'number';

	/**
	 * @var IPub\Phone\Phone
	 */
	private $phoneUtils;

	/**
	 * List of allowed countries
	 *
	 * @var array
	 */
	private $allowedCountries = [];

	/**
	 * List of allowed phone types
	 *
	 * @var array
	 */
	private $allowedTypes = [];

	/**
	 * @var string|NULL
	 */
	private $number = NULL;

	/**
	 * @var string|NULL
	 */
	private $country = NULL;

	/**
	 * @var string
	 */
	private $defaultCountry;

	/**
	 * @var bool
	 */
	private static $registered = FALSE;

	/**
	 * @param PhoneUtils $phoneUtils
	 * @param string|NULL $label
	 * @param int|NULL $maxLength
	 */
	public function __construct(PhoneUtils $phoneUtils, $label = NULL, $maxLength = NULL)
	{
		parent::__construct($label, $maxLength);

		$this->phoneUtils = $phoneUtils;
	}

	/**
	 * @param array $countries
	 *
	 * @return $this
	 *
	 * @throws Exceptions\NoValidCountryException
	 */
	public function setAllowedCountries(array $countries = [])
	{
		$this->allowedCountries = [];

		foreach ($countries as $country) {
			$country = $this->validateCountry($country);
			$this->allowedCountries[] = strtoupper($country);
		}

		// Check for auto country detection
		if (in_array('AUTO', $this->allowedCountries)) {
			$this->allowedCountries = ['AUTO'];
		}

		// Remove duplicities
		array_unique($this->allowedCountries);

		return $this;
	}

	/**
	 * @param string $country
	 *
	 * @return $this
	 *
	 * @throws Exceptions\NoValidCountryException
	 */
	public function addAllowedCountry($country)
	{
		$country = $this->validateCountry($country);
		$this->allowedCountries[] = strtoupper($country);

		// Remove duplicities
		array_unique($this->allowedCountries);

		if (strtoupper($country) === 'AUTO') {
			$this->allowedCountries = ['AUTO'];

		} else if (($key = array_search('AUTO', $this->allowedCountries)) && $key !== FALSE) {
			unset($this->allowedCountries[$key]);
		}

		return $this;
	}

	/**
	 * @return array
	 */
	public function getAllowedCountries()
	{
		if (in_array('AUTO', $this->allowedCountries, TRUE) || $this->allowedCountries === []) {
			return $this->phoneUtils->getSupportedCountries();

		} else {
			return $this->allowedCountries;
		}
	}

	/**
	 * @param string|NULL $country
	 *
	 * @return $this
	 *
	 * @throws Exceptions\NoValidCountryException
	 */
	public function setDefaultCountry($country = NULL)
	{
		if ($country === NULL) {
			$this->defaultCountry = NULL;

		} else {
			$country = $this->validateCountry($country);

			$this->defaultCountry = strtoupper($country);
		}

		return $this;
	}

	/**
	 * @param array $types
	 *
	 * @return $this
	 *
	 * @throws Exceptions\NoValidTypeException
	 */
	public function setAllowedPhoneTypes(array $types = [])
	{
		$this->allowedTypes = [];

		foreach ($types as $type) {
			$type = $this->validateType($type);
			$this->allowedTypes[] = strtoupper($type);
		}

		// Remove duplicities
		array_unique($this->allowedTypes);

		return $this;
	}

	/**
	 * @param string $type
	 *
	 * @return $this
	 *
	 * @throws Exceptions\NoValidTypeException
	 */
	public function addAllowedPhoneType($type)
	{
		$type = $this->validateType($type);
		$this->allowedTypes[] = strtoupper($type);

		// Remove duplicities
		array_unique($this->allowedTypes);

		return $this;
	}

	/**
	 * @return array
	 */
	public function getAllowedPhoneTypes()
	{
		return $this->allowedTypes;
	}

	/**
	 * @param string
	 *
	 * @return $this
	 *
	 * @throws Exceptions\InvalidArgumentException
	 * @throws IPub\Phone\Exceptions\NoValidCountryException
	 * @throws IPub\Phone\Exceptions\NoValidPhoneException
	 */
	public function setValue($value)
	{
		if ($value === NULL) {
			$this->country = NULL;
			$this->number = NULL;

			return $this;
		}

		foreach ($this->getAllowedCountries() as $country) {
			if ($this->phoneUtils->isValid($value, $country)) {
				$phone = IPub\Phone\Entities\Phone::fromNumber($value, $country);

				$this->country = $phone->getCountry();
				$this->number = str_replace(' ', '', $phone->getNationalNumber());

				return $this;
			}
		}

		throw new Exceptions\InvalidArgumentException('Provided value is not valid phone number, or is out of list of allowed countries, "' . $value . '" given.');
	}

	/**
	 * @return IPub\Phone\Entities\Phone|NULL
	 */
	public function getValue()
	{
		if ($this->country === NULL || $this->number === NULL) {
			return NULL;
		}

		try {
			// Try to parse number & country
			$number = IPub\Phone\Entities\Phone::fromNumber($this->number, $this->country);

			return $number === NULL ? NULL : $number;

		} catch (IPub\Phone\Exceptions\NoValidCountryException $ex) {
			return NULL;

		} catch (IPub\Phone\Exceptions\NoValidPhoneException $ex) {
			return NULL;
		}
	}

	/**
	 * Loads HTTP data
	 *
	 * @return void
	 */
	public function loadHttpData()
	{
		$country = $this->getHttpData(Forms\Form::DATA_LINE, '[' . static::FIELD_COUNTRY . ']');
		$this->country = ($country === '' || $country === NULL) ? NULL : (string) $country;

		$number = $this->getHttpData(Forms\Form::DATA_LINE, '[' . static::FIELD_NUMBER . ']');
		$this->number = ($number === '' || $number === NULL) ? NULL : (string) $number;
	}

	/**
	 * @return Utils\Html
	 */
	public function getControl()
	{
		return Utils\Html::el()
			->addHtml($this->getControlPart(static::FIELD_COUNTRY) . $this->getControlPart(static::FIELD_NUMBER));
	}

	/**
	 * @param string $key
	 *
	 * @return Utils\Html
	 *
	 * @throws Exceptions\InvalidArgumentException
	 */
	public function getControlPart($key = NULL)
	{
		$name = $this->getHtmlName();

		// Try to get translator
		$translator = $this->getTranslator();

		if ($translator instanceof Localization\ITranslator && method_exists($translator, 'getLocale') === TRUE) {
			try {
				$locale = $translator->getLocale();

			} catch (\Exception $ex) {
				$locale = 'en_US';
			}

		} else {
			$locale = 'en_US';
		}

		if ($key === static::FIELD_COUNTRY) {
			$control = Forms\Helpers::createSelectBox(
				array_reduce($this->getAllowedCountries(), function (array $result, $row) use ($locale) {
					$countryName = FormPhone\Locale\Locale::getDisplayRegion(
						FormPhone\Locale\Locale::countryCodeToLocale($row),
						$locale
					);

					$result[$row] = Utils\Html::el('option')
						->setText('+' . $this->phoneUtils->getCountryCodeForCountry($row) . ' (' . $countryName . ')')
						->addAttributes([
							'data-mask' => preg_replace('/[0-9]/', '9', $this->phoneUtils->getExampleNationalNumber($row)),
						])
						->value($row);

					return $result;
				}, []),
				[
					'selected?' => $this->country === NULL ? $this->defaultCountry : $this->country,
				]
			);

			$control->addAttributes([
				'name' => $name . '[' . static::FIELD_COUNTRY . ']',
				'id'   => $this->getHtmlId() . '-' . static::FIELD_COUNTRY,

				'data-ipub-forms-phone' => '',
				'data-settings'         => json_encode([
					'field' => $name . '[' . static::FIELD_NUMBER . ']'
				])
			]);

			if ($this->isDisabled()) {
				$control->disabled(TRUE);
			}

			return $control;

		} else if ($key === static::FIELD_NUMBER) {
			$input = parent::getControl();

			$control = Utils\Html::el('input');

			$control->addAttributes([
				'name'  => $name . '[' . static::FIELD_NUMBER . ']',
				'id'    => $this->getHtmlId() . '-' . static::FIELD_NUMBER,
				'value' => $this->number,
				'type'  => 'text',

				'data-nette-empty-value' => Utils\Strings::trim($this->translate($this->emptyValue)),
				'data-nette-rules'       => $input->{'data-nette-rules'},
			]);

			if ($this->isDisabled()) {
				$control->disabled(TRUE);
			}

			return $control;
		}

		throw new Exceptions\InvalidArgumentException('Part ' . $key . ' does not exist.');
	}

	/**
	 * @return NULL
	 */
	public function getLabelPart()
	{
		return NULL;
	}

	/**
	 * @param string $country
	 *
	 * @return string
	 *
	 * @throws Exceptions\NoValidCountryException
	 */
	protected function validateCountry($country)
	{
		// Country code have to be upper-cased
		$country = strtoupper($country);

		if ((strlen($country) === 2 && ctype_alpha($country) && ctype_upper($country) && in_array($country, $this->phoneUtils->getSupportedCountries())) || $country === 'AUTO') {
			return $country;

		} else {
			throw new Exceptions\NoValidCountryException('Provided country code "' . $country . '" is not valid. Provide valid country code or AUTO for automatic detection.');
		}
	}

	/**
	 * @param string $type
	 *
	 * @return string
	 *
	 * @throws Exceptions\NoValidTypeException
	 */
	protected function validateType($type)
	{
		// Phone type have to be upper-cased
		$type = strtoupper($type);

		if (defined('\IPub\Phone\Phone::TYPE_' . $type)) {
			return $type;

		} else {
			throw new Exceptions\NoValidTypeException('Provided phone type "' . $type . '" is not valid. Provide valid phone type.');
		}
	}

	/**
	 * @param PhoneUtils $phoneUtils
	 * @param string $method
	 */
	public static function register(PhoneUtils $phoneUtils, $method = 'addPhone')
	{
		// Check for multiple registration
		if (self::$registered) {
			throw new Nette\InvalidStateException('Phone control already registered.');
		}

		self::$registered = TRUE;

		$class = function_exists('get_called_class') ? get_called_class() : __CLASS__;
		Forms\Container::extensionMethod(
			$method, function (Forms\Container $form, $name, $label = NULL, $maxLength = NULL) use ($class, $phoneUtils) {
			$component = new $class($phoneUtils, $label, $maxLength);
			$form->addComponent($component, $name);

			return $component;
		}
		);
	}
}
