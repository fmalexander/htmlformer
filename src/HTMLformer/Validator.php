<?php declare(strict_types = 1);

namespace FredrikAlexander\HTMLformer;

use FredrikAlexander\Util\Mapper;
use FredrikAlexander\Exception\IllegalNameException;
use FredrikAlexander\Exception\TypeException;
use FredrikAlexander\Exception\UnknownIndexException;
use FredrikAlexander\Exception\UnknownMethodException;

class Validator
{
    const NATIVE_METHOD = 1;
    const CUSTOM_METHOD = 2;

    /**
     * User input to be validated
     *
     * @var array
     */
    protected $userInput;

    /**
     * Validation rules
     *
     * @var array
     */
    protected $rules;

    /**
     * Validation functions registered by the client
     *
     * @var array
     */
    protected $customMethods;

    /**
     * Keeps track of all validation methods and their origin (custom/native)
     *
     * @var array
     */
    protected $validationMethods;

    /**
     * The last instance of Validator whose validate() method was called
     *
     * @var Validator
     */
    protected static $activeInstance = null;

    public function __construct()
    {
        $this->userInput = [];
        $this->rules = [];
        $this->customMethods = [];
        $methods = (new \ReflectionClass($this))->getMethods();
        $this->validationMethods = [];
        foreach ($methods as $method) {
            if (preg_match('/^validate\w+$/', $method->name)) {
                $name = lcfirst(substr($method->name, 8));
                $this->validationMethods[$name] = self::NATIVE_METHOD;
            }
        }
    }

    public static function getActiveInstance() : Validator
    {
        return self::$activeInstance ?? (self::$activeInstance = new Validator());
    }

    /**
     * Stores the user input to be validated
     *
     * @param array $userInput
     * @return void
     */
    public function addUserInput(array $userInput)
    {
        foreach ($userInput as $key => $value) {
            $this->userInput[$key] = $value;
        }
    }

    /**
     * Returns the data currently stored in user input
     *
     * @param string $key
     * @return mixed
     */
    public function getUserInput(string $key = null)
    {
        if ($key === null) {
            return $this->userInput;
        } elseif (!\array_key_exists($key, $this->userInput)) {
            throw new UnknownIndexException("Unknown array key '$key' given");
        }
        return $this->userInput[$key];
    }

    /**
     * Unsets a value stored in userInput,
     * or empties userInput if no key is given
     *
     * @param string $key
     * @return void
     */
    public function removeUserInput(string $key = null)
    {
        if ($key === null) {
            $this->userInput = [];
        } else {
            unset($this->userInput[$key]);
        }
    }

    /**
     * Adds sets of validation rules for values stored in userInput
     *
     * @param array $rules
     * @return void
     */
    public function addRules(array $rules)
    {
        foreach ($rules as $field => $rulesArray) {
            if (!\is_array($rulesArray)) {
                throw new TypeException("The rules for each field must be in an associative array");
            }
            foreach ($rulesArray as $method => $rule) {
                $this->rules[$field][$method] = $rule;
            }
        }
    }

    /**
     * Returns the current set of validation rules
     *
     * @return array
     */
    public function getRules(string $field = null) : array
    {
        if ($field === null) {
            return $this->rules;
        }
        if (!array_key_exists($field, $this->rules)) {
            throw new UnknownIndexException("There are no rules for '$field'");
        }
        return $this->rules[$field];
    }

    /**
     * Removes the rule set for a given input field,
     * or empties rules if no key is given
     *
     * @param string $field
     * @return void
     */
    public function removeRules(string $field = null)
    {
        if ($field === null) {
            $this->rules = [];
        } else {
            unset($this->rules[$field]);
        }
    }

    /**
     * Returns the currently registered validation methods
     *
     * @return array
     */
    public function getValidationMethods() : array
    {
        return $this->validationMethods;
    }

    /**
     * Adds a custom validation method
     *
     * @param ValidationMethod $method
     * @return void
     */
    public function addCustomMethod(ValidationMethod $method)
    {
        if (array_key_exists(
            $name = $method->getName(),
            $exMeth = $this->getValidationMethods()
        ) && $exMeth[$name] === self::NATIVE_METHOD
        ) {
            throw new IllegalNameException(
                "'$name' is the name of a native method and cannot be used for a custom method"
            );
        }
        $this->customMethods[$name] = $method;
        $this->validationMethods[$method->getName()] = self::CUSTOM_METHOD;
    }

    /**
     * Returns the custom methods currently registered for a Validator instance
     *
     * @return array
     */
    public function getCustomMethod(string $method = null)
    {
        if ($method === null) {
            return $this->customMethods;
        }
        if (!array_key_exists($method, $this->customMethods)) {
            throw new UnknownIndexException("There is no custom method '$method'");
        }
        return $this->customMethods[$method];
    }

    /**
     * Un-registers the given custom method from the Validator instance,
     * or removes all custom methods if no function is given
     *
     * @param string $function
     * @return void
     */
    public function removeCustomMethod(string $method = null)
    {
        if ($method === null) {
            $this->customMethods = [];
        } else {
            unset($this->customMethods[$method]);
        }
    }

    /**
     * Converts the validation rule to the name of the callback used for validation
     *
     * @param string $method
     * @return string
     */
    protected static function convertMethodName(string $method) : string
    {
        return $method = "self::validate" . \ucfirst($method);
    }

    /**
     * Calls a validation method with an input value and a validation rule
     *
     * @param string $method
     * @param string $input
     * @param [type] $rule
     * @return boolean
     */
    protected static function callValidationMethod(string $method, string $input, $rule) : bool
    {
        $regMethods = self::getActiveInstance()->getValidationMethods();
        if (!array_key_exists($method, $regMethods)) {
            throw new UnknownMethodException("Unknown validation method $method");
        }
        if ($regMethods[$method] === self::NATIVE_METHOD) {
            $method = self::convertMethodName($method);
            return call_user_func($method, $input, $rule);
        }
        return self::getActiveInstance()
            ->getCustomMethod()[$method]
            ->validate($input, $rule);
    }

    /**
     * Calls all validation methods stored in rules
     *
     * @return array
     */
    public function validate() : array
    {
        self::$activeInstance = $this;
        return Mapper::arrayMapAssoc(
            function ($field, $rules) : array {
                $input = self::getActiveInstance()->getUserInput($field);
                return Mapper::arrayMapAssoc(
                    function ($method, $rule, $input) : bool {
                        return self::callValidationMethod($method, $input, $rule);
                    },
                    $rules,
                    $input
                );
            },
            $this->rules
        );
    }

    /**
     * Valid if input is not an empty string
     *
     * @param string $input
     * @param boolean $is_required
     * @return boolean
     */
    protected static function validateRequired(string $input, bool $is_required) : bool
    {
        if ($is_required) {
            return $input !== '';
        }
        return true;
    }

    /**
     * Valid if input length >= $minlength
     *
     * @param string $input
     * @param integer $minlength
     * @return boolean
     */
    protected static function validateMinlength(string $input, int $minlength) : bool
    {
        return mb_strlen($input) >= $minlength;
    }

    /**
     * Valid if input length <= $maxlength
     */
    protected static function validateMaxlength(string $input, int $maxlength) : bool
    {
        return mb_strlen($input) <= $maxlength;
    }

    /**
     * Valid if input converts to a numeric value >= $min
     *
     * @param string $input
     * @param float $min
     * @return boolean
     */
    protected static function validateMin(string $input, float $min) : bool
    {
        return $input >= $min;
    }

    /**
     * Valid if input converts to a numeric value <= $max
     *
     * @param string $input
     * @param float $max
     * @return boolean
     */
    protected static function validateMax(string $input, float $max) : bool
    {
        return $input <= $max;
    }

    /**
     * Valid if input consists of only digits
     *
     * @param string $input
     * @param boolean $is_digits
     * @return boolean
     */
    protected static function validateDigits(string $input, bool $is_digits) : bool
    {
        if ($is_digits) {
            return (bool) preg_match('/^[0-9]+$/', $input);
        }
        return true;
    }

    /**
     * Valid if input is a number/numeric string
     *
     * @param string $input
     * @param boolean $is_number
     * @return boolean
     */
    protected static function validateNumber(string $input, bool $is_number) : bool
    {
        if ($is_number) {
            return is_numeric($input);
        }
        return true;
    }

    /**
     * Valid if input matches the regular expression $pattern
     *
     * @param string $input
     * @param string $pattern
     * @return boolean
     */
    protected static function validatePattern(string $input, string $pattern) : bool
    {
        return (bool) preg_match($pattern, $input);
    }

    /**
     * Valid if input matches the value of another input field
     *
     * @param string $input
     * @param string $field_to_match
     * @return boolean
     */
    protected static function validateEqualTo(string $input, string $field_to_match) : bool
    {
        return $input === self::getActiveInstance()->getUserInput($field_to_match);
    }

    /**
     * Valid if input matches none of the values in $forbidden (can be string or array)
     *
     * @param string $input
     * @param [type] $forbidden
     * @return boolean
     */
    protected static function validateNot(string $input, $forbidden) : bool
    {
        if (is_array($forbidden)) {
            foreach ($forbidden as $forbiddenString) {
                if (!is_string($forbiddenString)) {
                    throw new TypeException("\$forbidden must contain only strings");
                }
            }
            return !(in_array($input, $forbidden));
        }
        return $input !== $forbidden;
    }

    /**
     * Valid if input matches a simple regular expression for e-mail addresses
     *
     * @param string $input
     * @param boolean $is_email
     * @return boolean
     */
    protected static function validateEmail(string $input, bool $is_email) : bool
    {
        if ($is_email) {
            return (bool) preg_match('/^[A-z0-9._%+-]+@[A-z0-9.-]+\.[A-z]{2,}$/', $input);
        }
        return true;
    }

    /**
     * Valid if input matches a simple regular expression for urls
     *
     * @param string $input
     * @param boolean $is_url
     * @return boolean
     */
    protected static function validateUrl(string $input, bool $is_url) : bool
    {
        if ($is_url) {
            return (bool) preg_match(
                '/^(ht|f)tp(s?)\:\/\/[0-9a-zA-Z]([-.\w]*[0-9a-zA-Z])*(:(0-9)*)*(\/?)([a-zA-Z0-9\-\.\?\,\'\/\\\+&amp;%\$#_]*)?$/',
                $input
            );
        }
    }

    /**
     * Valid if input matches a given date format
     *
     * @param string $input
     * @param string $format
     * @return boolean
     */
    protected static function validateDate(string $input, string $format) : bool
    {
        $date = \DateTime::createFromFormat($format, $input);
        return $date ? ($date->format($format)) === $input : false;
    }

    /**
     * Valid if input is a date later than or equal to $mindate
     *
     * @param string $input
     * @param string $mindate
     * @return boolean
     */
    protected static function validateMindate(string $input, string $mindate) : bool
    {
        return (strtotime($input) >= strtotime($mindate));
    }

    /**
     * Valid if input is a date earlier than or equal to $maxdate
     *
     * @param string $input
     * @param string $maxdate
     * @return boolean
     */
    protected static function validateMaxdate(string $input, string $maxdate) : bool
    {
        return (strtotime($input) <= strtotime($maxdate));
    }
}
