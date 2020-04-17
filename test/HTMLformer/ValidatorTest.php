<?php declare(strict_types = 1);

namespace FredrikAlexander\HTMLformer;

use FredrikAlexander\Exception\IllegalNameException;
use FredrikAlexander\Exception\TypeException;
use FredrikAlexander\Exception\UnknownIndexException;
use FredrikAlexander\Exception\UnknownMethodException;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private $validator;
    private $userInput;
    private $rules;

    public function setUp() : void
    {
        $this->validator = new Validator();
        $this->userInput = [
            "name" => "Joe",
            "age" => "39",
            "dog" => "Snoopy",
            "date" => "1993-06-02"
        ];
        $this->rules = [
            'name' => [
                'required' => true,
                'pattern' => '/^[\w\s]+$/',
                'not' => 'Homer',
            ],
            'age' => [
                'required' => true,
                'digits' => true,
                'min' => 15
            ],
            'date' => [
                'required' => true,
                'date' => 'Y-m-d',
                'mindate' => '2020-01-01',
            ]
        ];
    }

    private function emptyValues()
    {
        $this->validator->removeUserInput();
        $this->validator->removeRules();
        $this->validator->removeCustomMethod();
    }

    private function setDefaultValues()
    {
        $this->emptyValues();
        $this->validator->addUserInput($this->userInput);
        $this->validator->addRules($this->rules);
    }

    private function addCustomMethodEquals()
    {
        $this->validator->addCustomMethod(
            new class implements ValidationMethod {
                public function getName() : string
                {
                    return "equals";
                }

                public function validate(string $input, $num) : bool 
                {
                    return ((int) $input) === $num;
                }
            }
        );
    }

    private function addCustomMethodFiveMoreThan()
    {
        $this->validator->addCustomMethod(
            new class implements ValidationMethod {
                public function getName() : string
                {
                    return "fiveMoreThan";
                }

                public function validate(string $input, $compField) : bool
                {
                    $comp = Validator::getActiveInstance()->getUserInput($compField);
                    return ((int) $input) === ((int) $comp + 5);
                }
            }
        );
    }

    public function testAddUserInput()
    {
        $this->validator->addUserInput($this->userInput);

        $this->assertEquals(
            $this->userInput,
            $this->validator->getUserInput(),
            "Input not properly stored"
        );
    }


    public function testAddRules()
    {

        $this->validator->addRules($this->rules);
        $this->assertEquals(
            $this->rules,
            $this->validator->getRules(),
            "Rules not properly set"
        );
    }

    public function testAddRulesWrongFormat()
    {
        try {
            $this->validator->addRules(
                ["name" => 5, "string" => "bla"]
            );
        } catch (\Exception $e) {
            $this->assertInstanceOf(
                TypeException::class,
                $e,
                "TypeException expected, " . get_class($e) . " thrown"
            );
            return;
        }
        $this->fail("TypeException expected, none thrown");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateUnknownMethod()
    {
        $this->setDefaultValues();
        $this->validator->addRules(
            [
                "name" => [
                    "required" => true,
                    "methodX" => false,
                    "not" => "Homer"
                ]
            ]
        );
        try {
            $this->validator->validate();
        } catch (\Exception $e) {
            $this->assertInstanceOf(
                UnknownMethodException::class,
                $e,
                "UnknownMethodException expected, " . get_class($e) . " thrown"
            );
            return;
        }
        $this->fail("UnknownMethodException expected, none thrown");
    }

    public function testGetUserInputUnknownIndex()
    {
        try {
            $this->validator->getUserInput("bla");
        } catch (\Exception $e) {
            $this->assertInstanceOf(
                UnknownIndexException::class,
                $e,
                "UnknownIndexException expected, " . get_class($e) . " thrown"
            );
            return;
        }
        $this->fail("UnknownIndexException expected, none thrown");
    }

    public function testRemoveSingleKey()
    {
        $this->setDefaultValues();
        $this->validator->removeUserInput("dog");

        $this->assertArrayNotHasKey(
            "dog",
            $this->validator->getUserInput(),
            "Specified input not removed correctly"
        );
    }

    public function testRemoveAllInput()
    {
        $this->setDefaultValues();
        $this->validator->removeUserInput();

        $this->assertEquals(
            [],
            $this->validator->getUserInput(),
            "userInput not properly emptied"
        );
    }

    public function testAddCustomMethodEquals()
    {
        $this->addCustomMethodEquals();

        $this->assertArrayHasKey(
            "equals",
            $this->validator->getCustomMethod(),
            "key for custom method not found in array customMethods"
        );
        $this->assertInstanceOf(
            ValidationMethod::class,
            $this->validator->getCustomMethod()["equals"],
            "Add function not callable"
        );
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateCustomMethodEquals()
    {
        $this->setDefaultValues();
        $this->addCustomMethodEquals();
        $this->validator->addRules([
            "age" => ["equals" => 39],
            "dog" => ["equals" => 2]
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["age"]["equals"], "age failed");
        $this->assertFalse($result["dog"]["equals"], "dog failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateCustomMethodFiveMoreThan()
    {
        $this->setDefaultValues();
        $this->addCustomMethodFiveMoreThan();
        $this->validator->addUserInput(["comp" => "34"]);
        $this->validator->addRules([
            "age" => ["fiveMoreThan" => "comp"],
            "name" => ["fiveMoreThan" => "dog"]
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["age"]["fiveMoreThan"], "age failed");
        $this->assertFalse($result["name"]["fiveMoreThan"], "name failed");
    }

    /**
     * @depends testAddCustomMethodEquals
     */
    public function testRemoveCustomMethod()
    {
        $this->validator->removeCustomMethod("equals");

        $this->assertArrayNotHasKey(
            "equals",
            $this->validator->getCustomMethod(),
            "custom method not removed"
        );
    }

    public function testAddCustomMethodIllegalName()
    {
        try {
            $this->validator->addCustomMethod(
                new class implements ValidationMethod {
                    public function getName() : string
                    {
                        return "required";
                    }

                    public function validate(string $input, $required) : bool
                    {
                        return $input !== "";
                    }
                }
            );
        } catch (\Exception $e) {
            $this->assertInstanceOf(
                IllegalNameException::class,
                $e,
                "IllegalNameException expected, " . get_class($e) . " thrown"
            );
            return;
        }
        $this->fail("IllegalNameException expected, none thrown");
    }

    public function testGetRulesUnknownIndex()
    {
        $this->emptyValues();

        try {
            $this->validator->getRules("bla");
        } catch (\Exception $e) {
            $this->assertInstanceOf(
                UnknownIndexException::class,
                $e,
                "UnknownIndexException expected, " . get_class($e) . " thrown"
            );
            return;
        }
        $this->fail("UnknownIndexException expected, none thrown");
    }

    public function testGetUnknownCustomMethod()
    {
        $this->emptyValues();

        try {
            $this->validator->getCustomMethod("bla");
        } catch (\Exception $e) {
            $this->assertInstanceOf(
                UnknownIndexException::class,
                $e,
                "UnknownIndexException expected, " . get_class($e) . " thrown"
            );
            return;
        }
        $this->fail("UnknownIndexException expected, none thrown");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateSampleAllValid()
    {
        $this->setDefaultValues();
        $this->validator->addUserInput(["date" => "2020-06-02"]);
        $val = $this->validator->validate();

        $this->assertEquals(
            [
                'name' => [
                    'required' => true,
                    'pattern' => true,
                    'not' => true
                ],
                'age' => [
                    'required' => true,
                    'digits' => true,
                    'min' => true
                ],
                'date' => [
                    'required' => true,
                    'date' => true,
                    'mindate' => true
                ]
            ],
            $val,
            "Validation not performed properly"
        );
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateUnknownInput()
    {
        $this->setDefaultValues();
        $this->validator->addRules(["unknown" => ["required" => true]]);

        try{
            $this->validator->validate();
        } catch (\Exception $e) {
            $this->assertTrue(
                $e instanceof UnknownIndexException,
                "UnknownIndexException expected, " . get_class($e) . " thrown"
            );
            return;
        }
        $this->fail("UnknownIndexException expected, none thrown");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateRequired()
    {
        $this->emptyValues();
        $this->validator->addUserInput(["name" => "", "dog" => "Snoopy"]);
        $this->validator->addRules([
            "name" => ["required" => true],
            "dog" => ["required" => true]
        ]);

        $result = $this->validator->validate();

        $this->assertFalse($result["name"]["required"], "Invalid failed");
        $this->assertTrue($result["dog"]["required"], "Valid failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateMinlength()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "name" => "",
            "dog" => "Snoopy",
            "cat" => "Garfield",
            "city" => "Freiburg",
            "letter" => ""
        ]);
        $this->validator->addRules([
            "name" => ["minlength" => 5],
            "dog" => ["minlength" => 7],
            "cat" => ["minlength" => 5],
            "city" => ["minlength" => 8],
            "letter" => ["minlength" => 0]
        ]);

        $result = $this->validator->validate();

        $this->assertFalse($result["name"]["minlength"], "name failed");
        $this->assertFalse($result["dog"]["minlength"], "dog failed");
        $this->assertTrue($result["cat"]["minlength"], "cat failed");
        $this->assertTrue($result["city"]["minlength"], "city failed");
        $this->assertTrue($result["letter"]["minlength"], "letter failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateMaxlength()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "name" => "",
            "dog" => "Snoopy",
            "cat" => "Garfield",
            "city" => "Nürnberg",
            "letter" => "A"
        ]);
        $this->validator->addRules([
            "name" => ["maxlength" => 5],
            "dog" => ["maxlength" => 7],
            "cat" => ["maxlength" => 5],
            "city" => ["maxlength" => 8],
            "letter" => ["maxlength" => 0]
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["name"]["maxlength"], "name failed");
        $this->assertTrue($result["dog"]["maxlength"], "dog failed");
        $this->assertFalse($result["cat"]["maxlength"], "cat failed");
        $this->assertTrue($result["city"]["maxlength"], "city failed");
        $this->assertFalse($result["letter"]["maxlength"], "letter failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateMin()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "nr1" => "0",
            "nr2" => "17",
            "nr3" => "-13",
            "nr4" => "-13",
            "nr5" => "1e3",
            "nr6" => "6.0",
            "nr7" => "1.5",
            "nr8" => "1.5"
        ]);
        $this->validator->addRules([
            "nr1" => ["min" => 5],
            "nr2" => ["min" => 17],
            "nr3" => ["min" => -12],
            "nr4" => ["min" => -14],
            "nr5" => ["min" => 1e2],
            "nr6" => ["min" => 6],
            "nr7" => ["min" => 2],
            "nr8" => ["min" => 1.7]
        ]);

        $result = $this->validator->validate();

        $this->assertFalse($result["nr1"]["min"], "nr1 failed");
        $this->assertTrue($result["nr2"]["min"], "nr2 failed");
        $this->assertFalse($result["nr3"]["min"], "nr3 failed");
        $this->assertTrue($result["nr4"]["min"], "nr4 failed");
        $this->assertTrue($result["nr5"]["min"], "nr5 failed");
        $this->assertTrue($result["nr6"]["min"], "nr6 failed");
        $this->assertFalse($result["nr7"]["min"], "nr7 failed");
        $this->assertFalse($result["nr8"]["min"], "nr8 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateMax()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "nr1" => "0",
            "nr2" => "17",
            "nr3" => "-13",
            "nr4" => "-13",
            "nr5" => "1e3",
            "nr6" => "6.0",
            "nr7" => "1.5",
            "nr8" => "1.5"
        ]);
        $this->validator->addRules([
            "nr1" => ["max" => 5],
            "nr2" => ["max" => 17],
            "nr3" => ["max" => -12],
            "nr4" => ["max" => -14],
            "nr5" => ["max" => 1e2],
            "nr6" => ["max" => 6],
            "nr7" => ["max" => 2],
            "nr8" => ["max" => 1.7]
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["nr1"]["max"], "nr1 failed");
        $this->assertTrue($result["nr2"]["max"], "nr2 failed");
        $this->assertTrue($result["nr3"]["max"], "nr3 failed");
        $this->assertFalse($result["nr4"]["max"], "nr4 failed");
        $this->assertFalse($result["nr5"]["max"], "nr5 failed");
        $this->assertTrue($result["nr6"]["max"], "nr6 failed");
        $this->assertTrue($result["nr7"]["max"], "nr7 failed");
        $this->assertTrue($result["nr8"]["max"], "nr8 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateDigits()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "nr1" => "0123456789",
            "nr2" => "one",
            "nr3" => "-13",
            "nr4" => "1.5",
            "nr5" => "1klnj9",
            "nr6" => "187f665467"
        ]);
        $this->validator->addRules([
            "nr1" => ["digits" => true],
            "nr2" => ["digits" => true],
            "nr3" => ["digits" => true],
            "nr4" => ["digits" => true],
            "nr5" => ["digits" => true],
            "nr6" => ["digits" => true]
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["nr1"]["digits"], "nr1 failed");
        $this->assertFalse($result["nr2"]["digits"], "nr2 failed");
        $this->assertFalse($result["nr3"]["digits"], "nr3 failed");
        $this->assertFalse($result["nr4"]["digits"], "nr4 failed");
        $this->assertFalse($result["nr5"]["digits"], "nr5 failed");
        $this->assertFalse($result["nr6"]["digits"], "nr6 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateNumber()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "nr1" => "0123456789",
            "nr2" => "one",
            "nr3" => "-13",
            "nr4" => "1.5",
            "nr5" => "1klnj9",
            "nr6" => "187f665467",
            "nr7" => "1e04",
            "nr8" => "-3.654",
        ]);
        $this->validator->addRules([
            "nr1" => ["number" => true],
            "nr2" => ["number" => true],
            "nr3" => ["number" => true],
            "nr4" => ["number" => true],
            "nr5" => ["number" => true],
            "nr6" => ["number" => true],
            "nr7" => ["number" => true],
            "nr8" => ["number" => true]
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["nr1"]["number"], "nr1 failed");
        $this->assertFalse($result["nr2"]["number"], "nr2 failed");
        $this->assertTrue($result["nr3"]["number"], "nr3 failed");
        $this->assertTrue($result["nr4"]["number"], "nr4 failed");
        $this->assertFalse($result["nr5"]["number"], "nr5 failed");
        $this->assertFalse($result["nr6"]["number"], "nr6 failed");
        $this->assertTrue($result["nr7"]["number"], "nr7 failed");
        $this->assertTrue($result["nr8"]["number"], "nr8 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidatePattern()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "wordTrue" => "Hello",
            "wordFalse" => "n0 word",
            "digitsTrue" => "1420857",
            "digitsFalse" => "0.1420857",
            "lowercaseTrue" => "onlylowercase",
            "lowercaseFalse" => "hasAlsoUpperCase"
        ]);
        $this->validator->addRules([
            "wordTrue" => ["pattern" => '/^\w+$/'],
            "wordFalse" => ["pattern" => '/^\w+$/'],
            "digitsTrue" => ["pattern" => '/^\d+$/'],
            "digitsFalse" => ["pattern" => '/^\d+$/'],
            "lowercaseTrue" => ["pattern" => '/^[a-z]+$/'],
            "lowercaseFalse" => ["pattern" => '/^[a-z]+$/'],
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["wordTrue"]["pattern"], "wordTrue failed");
        $this->assertFalse($result["wordFalse"]["pattern"], "wordFalse failed");
        $this->assertTrue($result["digitsTrue"]["pattern"], "digitsTrue failed");
        $this->assertFalse($result["digitsFalse"]["pattern"], "digitsFalse failed");
        $this->assertTrue($result["lowercaseTrue"]["pattern"], "lowercaseTrue failed");
        $this->assertFalse($result["lowercaseFalse"]["pattern"], "lowercaseFalse failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateEqualTo()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "password1" => "asdf",
            "password2" => "asdf",
            "password3" => "asdg"
        ]);
        $this->validator->addRules([
            "password2" => ["equalTo" => "password1"],
            "password3" => ["equalTo" => "password1"]
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["password2"]["equalTo"], "password2 failed");
        $this->assertFalse($result["password3"]["equalTo"], "password3 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateNot()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "string1" => "Hello",
            "string2" => "bla",
            "string3" => "1420857",
            "string4" => "",
            "string5" => " Hello",
            "string6" => "Hello ",
            "string7" => "Wuff",
            "string8" => "Hello",
            "string9" => "Goodbye"
        ]);
        $this->validator->addRules([
            "string1" => ["not" => "Hello"],
            "string2" => ["not" => "Hello"],
            "string3" => ["not" => "Hello"],
            "string4" => ["not" => "Hello"],
            "string5" => ["not" => "Hello"],
            "string6" => ["not" => "Hello"],
            "string7" => ["not" => ["Hello", "Goodbye"]],
            "string8" => ["not" => ["Hello", "Goodbye"]],
            "string9" => ["not" => ["Hello", "Goodbye"]]
        ]);

        $result = $this->validator->validate();

        $this->assertFalse($result["string1"]["not"], "string1 failed");
        $this->assertTrue($result["string2"]["not"], "string2 failed");
        $this->assertTrue($result["string3"]["not"], "string3 failed");
        $this->assertTrue($result["string4"]["not"], "string4 failed");
        $this->assertTrue($result["string5"]["not"], "string5 failed");
        $this->assertTrue($result["string6"]["not"], "string6 failed");
        $this->assertTrue($result["string7"]["not"], "string7 failed");
        $this->assertFalse($result["string8"]["not"], "string8 failed");
        $this->assertFalse($result["string9"]["not"], "string9 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateEmail()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "email1" => "fredrik.alexander@gmx.de",
            "email2" => "bla@blubb.bzh",
            "email3" => "test_blubb@bla.dk",
            "email4" => "test-email@e-mail.co.uk",
            "email5" => " Hello",
            "email6" => "hello @doof.by",
            "email7" => "@noemail",
            "email8" => "thisisnoemail@",
            "email9" => ""
        ]);
        $this->validator->addRules([
            "email1" => ["email" => true],
            "email2" => ["email" => true],
            "email3" => ["email" => true],
            "email4" => ["email" => true],
            "email5" => ["email" => true],
            "email6" => ["email" => true],
            "email7" => ["email" => true],
            "email8" => ["email" => true],
            "email9" => ["email" => true]
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["email1"]["email"], "email1 failed");
        $this->assertTrue($result["email2"]["email"], "email2 failed");
        $this->assertTrue($result["email3"]["email"], "email3 failed");
        $this->assertTrue($result["email4"]["email"], "email4 failed");
        $this->assertFalse($result["email5"]["email"], "email5 failed");
        $this->assertFalse($result["email6"]["email"], "email6 failed");
        $this->assertFalse($result["email7"]["email"], "email7 failed");
        $this->assertFalse($result["email8"]["email"], "email8 failed");
        $this->assertFalse($result["email9"]["email"], "email9 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateUrl()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "url1" => "https://fredrikalexander.de",
            "url2" => "https://mlb.com",
            "url3" => "https://google.com/?q=bla",
            "url4" => "http://www.mail.co.uk/index.html?abc=def&bla=blubb",
            "url5" => "Hello",
            "url6" => "hello@doof.by",
            "url7" => "nourl.",
            "url8" => "thisisnourl. de",
            "url9" => ""
        ]);
        $this->validator->addRules([
            "url1" => ["url" => true],
            "url2" => ["url" => true],
            "url3" => ["url" => true],
            "url4" => ["url" => true],
            "url5" => ["url" => true],
            "url6" => ["url" => true],
            "url7" => ["url" => true],
            "url8" => ["url" => true],
            "url9" => ["url" => true]
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["url1"]["url"], "url1 failed");
        $this->assertTrue($result["url2"]["url"], "url2 failed");
        $this->assertTrue($result["url3"]["url"], "url3 failed");
        $this->assertTrue($result["url4"]["url"], "url4 failed");
        $this->assertFalse($result["url5"]["url"], "url5 failed");
        $this->assertFalse($result["url6"]["url"], "url6 failed");
        $this->assertFalse($result["url7"]["url"], "url7 failed");
        $this->assertFalse($result["url8"]["url"], "url8 failed");
        $this->assertFalse($result["url9"]["url"], "url9 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateDate()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "date1.1" => "2020-06-02",
            "date1.2" => "2020-1-1",
            "date1.3" => "2020-02-30",
            "date2.1" => "2020-6-2",
            "date2.2" => "2020-01-01",
            "date2.3" => "2020-2-30",
            "date3.1" => "02.06.93",
            "date3.2" => "2.6.93",
            "date3.3" => "30.02.20",
            "date4.1" => "Jun 2nd, 1993",
            "date4.2" => "June 2 1993",
            "date4.3" => "June 2, 1993",
        ]);
        $this->validator->addRules([
            "date1.1" => ["date" => "Y-m-d"],
            "date1.2" => ["date" => "Y-m-d"],
            "date1.3" => ["date" => "Y-m-d"],
            "date2.1" => ["date" => "Y-n-j"],
            "date2.2" => ["date" => "Y-n-j"],
            "date2.3" => ["date" => "Y-n-j"],
            "date3.1" => ["date" => "d.m.y"],
            "date3.2" => ["date" => "d.m.y"],
            "date3.3" => ["date" => "d.m.y"],
            "date4.1" => ["date" => "M jS, Y"],
            "date4.2" => ["date" => "M jS, Y"],
            "date4.3" => ["date" => "M jS, Y"],
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["date1.1"]["date"], "date1.1 failed");
        $this->assertFalse($result["date1.2"]["date"], "date1.2 failed");
        $this->assertFalse($result["date1.3"]["date"], "date1.3 failed");

        $this->assertTrue($result["date2.1"]["date"], "date2.1 failed");
        $this->assertFalse($result["date2.2"]["date"], "date2.2 failed");
        $this->assertFalse($result["date2.3"]["date"], "date2.3 failed");

        $this->assertTrue($result["date3.1"]["date"], "date3.1 failed");
        $this->assertFalse($result["date3.2"]["date"], "date3.2 failed");
        $this->assertFalse($result["date3.3"]["date"], "date3.3 failed");

        $this->assertTrue($result["date4.1"]["date"], "date4.1 failed");
        $this->assertFalse($result["date4.2"]["date"], "date4.2 failed");
        $this->assertFalse($result["date4.3"]["date"], "date4.3 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateMinDate()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "date1" => "1993-06-02",
            "date2" => "1993-06-03",
            "date3" => "02.05.1993",
            "date4" => "01.02.2020",
            "date5" => "03.02.2020",
            "date6" => "03.01.2020",
        ]);
        $this->validator->addRules([
            "date1" => ["mindate" => "1993-06-02"],
            "date2" => ["mindate" => "1993-06-02"],
            "date3" => ["mindate" => "1993-06-02"],
            "date4" => ["mindate" => "01.02.2020"],
            "date5" => ["mindate" => "01.02.2020"],
            "date6" => ["mindate" => "01.02.2020"],
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["date1"]["mindate"], "date1 failed");
        $this->assertTrue($result["date2"]["mindate"], "date2 failed");
        $this->assertFalse($result["date3"]["mindate"], "date3 failed");
        $this->assertTrue($result["date4"]["mindate"], "date4 failed");
        $this->assertTrue($result["date5"]["mindate"], "date5 failed");
        $this->assertFalse($result["date6"]["mindate"], "date6 failed");
    }

    /**
     * @depends testAddRules
     * @depends testAddUserInput
     */
    public function testValidateMaxDate()
    {
        $this->emptyValues();
        $this->validator->addUserInput([
            "date1" => "1993-06-02",
            "date2" => "1993-06-03",
            "date3" => "02.05.1993",
            "date4" => "01.02.2020",
            "date5" => "03.02.2020",
            "date6" => "03.01.2020",
        ]);
        $this->validator->addRules([
            "date1" => ["maxdate" => "1993-06-02"],
            "date2" => ["maxdate" => "1993-06-02"],
            "date3" => ["maxdate" => "1993-06-02"],
            "date4" => ["maxdate" => "01.02.2020"],
            "date5" => ["maxdate" => "01.02.2020"],
            "date6" => ["maxdate" => "01.02.2020"],
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result["date1"]["maxdate"], "date1 failed");
        $this->assertFalse($result["date2"]["maxdate"], "date2 failed");
        $this->assertTrue($result["date3"]["maxdate"], "date3 failed");
        $this->assertTrue($result["date4"]["maxdate"], "date4 failed");
        $this->assertFalse($result["date5"]["maxdate"], "date5 failed");
        $this->assertTrue($result["date6"]["maxdate"], "date6 failed");
    }
}
