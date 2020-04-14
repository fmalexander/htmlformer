<?php declare(strict_types = 1);

namespace FredrikAlexander\HTMLformer;

interface ValidationMethod
{
    /**
     * Returns the method name to be passed to a Validator object as a validation rule
     *
     * @return string
     */
    public function getName() : string;

    /**
     * Performs the validation when called by Validator::validate(),
     * returns true if valid, false if not
     *
     * @param string $input The user input string to be validated
     * @param mixed $rule the validation criterion used by the function
     * @return boolean
     */
    public function validate(string $input, $rule) : bool;
}
