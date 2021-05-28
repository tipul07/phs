<?php

namespace phs\tests\behat\contexts\admin;

use Behat\Behat\Tester\Exception\PendingException;
use phs\tests\phs\contexts\PHSAbstractContext;

class PHSAdminContext extends PHSAbstractContext
{

    /**
     * @Given /^there are (\d+) coffees left in the machine$/
     */
    public function thereAreCoffeesLeftInTheMachine($arg1)
    {
        throw new PendingException();
    }

    /**
     * @Given /^I have deposited (\d+) dollar$/
     */
    public function iHaveDepositedDollar($arg1)
    {
        throw new PendingException();
    }

    /**
     * @When /^I press the coffee button$/
     */
    public function iPressTheCoffeeButton()
    {
        throw new PendingException();
    }

    /**
     * @Then /^I should be served a coffee$/
     */
    public function iShouldBeServedACoffee()
    {
        throw new PendingException();
    }
}
