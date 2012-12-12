<?php

use Behat\Behat\Formatter\HtmlFormatter;


use Behat\Mink\Exception\ExpectationException,
    Behat\Behat\Exception\PendingException,
    Behat\Mink\Exception\ElementNotFoundException;

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext;

use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

class DefaultFeatureContext extends Behat\MinkExtension\Context\MinkContext
{
    protected $phabric;
    static $db;
    static $parameters;
    static $waitTime = 5;

    public function __construct($parameters = array()){
        self::$parameters = $parameters;
        $this->app_path = realpath(dirname(__FILE__)) . '/../../';

        //$this->useContext('RestContext', new RestContext($parameters['RestAPI']));
    }

    /**
     * @When /^I log in$/
     */
    public function iLogIn()
    {
        $this->visit("/logout");
        $this->visit("/");
        $page = $this->getSession()->getPage();
        $page->fillField("email", 'gergo.boros1@arobs.com');
        $page->fillField("password", 'qweasd');
        $page->pressButton("Log in");
        $this->assertPageAddress('/explore');
    }

    /**
     * @When /^I log out$/
     */
    public function iLogOut()
    {
        $this->visit("/logout");
    }

    /**
     * @Given /^I am on home page$/
     */
    public function iAmOnHomePage2()
    {
        $this->visit("/teamAId/13/teamBId/2");
        $this->assertPageAddress('/teamAId/13/teamBId/2');
    }

    /**
     * @Then /^I should see "([^"]*)" text on page$/
     */
    public function iShouldSeeTextOnPage($arg1)
    {
        $this->assertResponseContains($arg1);
    }


    /**
     * @When /^I go to building (\d+)$/
     */
    public function iGoToBuilding($buildingId)
    {
        $this->visit("/building/$buildingId");
    }

    /**
     * @Given /^there are no emails$/
     */
    public function thereAreNoEmails()
    {
        foreach (glob($this->app_path . self::$parameters['emailLog']['path'] . "ZendMail_*.tmp") as $email){
            unlink($email);
        }
    }

    /**
     * @Given /^the "([^"]*)" form is validated$/
     */
    public function theFormIsValidated($formName){
        $this->getMainContext()->getSession()->executeScript('$("#'.$formName.'").valid();');
    }

    /**
     * @Then /^there should be one email containing "([^"]*)"$/
     */
    public function assertShouldBeOneEmailContaining($expected_text)
    {
        $i = 0;
        foreach (glob($this->app_path . self::$parameters['emailLog']['path'] . "ZendMail_*.tmp") as $email){
            $i++;
        }

        if ($i !== 1) {
            $message = sprintf('There are "%s" emails, but 1 expected.', $i);
            throw new ExpectationException($message, $this->getSession());
        }

        $actual = file_get_contents($email);
        $regex   = '/'.preg_quote($expected_text, '/').'/ui';

        if (!preg_match($regex, $actual)) {
            $message = sprintf('The string "%s" was not found in the email.', $expected_text);
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @Given /^there is an valid token "([^"]*)" for user "([^"]*)" with value "([^"]*)"$/
     */
    public function thereIsAnValidTokenForUserWithValue($token_type, $user_email, $token_string)
    {
        $userStorage = BApp\Di::getDi()->get('BAppStorage\User');
        $user = $userStorage->fetchObjectBy(array('email = ?' => $user_email));

        /* @var $tokenStorage BAppStorage\Token */
        $tokenStorage = BApp\Di::getDi()->get('BAppStorage\Token');
        $tokenStorage->deleteBy(array('token = ?' => $token_string));

        /* @var $tokenService BAppService\Token */
        $tokenService = BApp\Di::getDi()->get('BAppService\Token');
        $tokenService->generate(constant('\BAppService\Token::'.$token_type), $user->getId(), null, $token_string);
    }

    /**
     * @Given /^I should see "([^"]*)" with "([^"]*)" value$/
     */
    public function iShouldSeeWithValue($css_element, $value){
        $this->getMainContext()->assertSession()->elementExists('css', $css_element);
        $element = $this->getSession()->getPage()->find('css', $css_element);
        if ($element->getHtml() != $value){
            $message = sprintf('The css element "%s" does not contain the "%s" html value.', $css_element, $value);
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @Given /^I should see the "([^"]*)" element$/
     */
    public function iShouldSeeTheElement($css_element){
        $this->getMainContext()->assertSession()->elementExists('css', $css_element);
    }

    /**
     * @Given /^there are test data for the buildings$/
     */
    public function thereAreTestDataForTheBuildings(){

        /* @var $buildingService \BAppService\Building */
        $buildingService = BApp\Di::getDi()->get('BAppService\Building');

        try {
            $buildingService->populateWithTestData();
        } catch (\BAppService\Building\InvalidDataException $e) {
            throw new ExpectationException($e->getTraceAsString(), $this->getSession());
        } catch (\BAppService\Building\StoragePersistanceException $e) {
            throw new ExpectationException($e->getTraceAsString(), $this->getSession());
        }
    }

    /**
     * @Then /^the user \"([^\']*)\" should exist$/
     */
    public function theUserShouldExist($user_email)
    {
        try{
            /* @var $userStorage BAppStorage\User */
            $userStorage = BApp\Di::getDi()->get('BAppStorage\User');
            $user = $userStorage->fetchObjectBy(array('email = ?' => $user_email));
        } catch (BApp\Storage\Db\Exception\EntityNotFoundException $e){
            $message = sprintf('The user "%s" was notfound in the database.', $user_email);
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @Then /^the user \"([^\']*)\" should not exist$/
     */
    public function theUserShouldNotExist($user_email)
    {
        try{
            $userStorage = BApp\Di::getDi()->get('BAppStorage\User');
            $user = $userStorage->fetchObjectBy(array('email = ?' => $user_email));

            $message = sprintf('The user "%s" was found in the database.', $user_email);
            throw new ExpectationException($message, $this->getSession());
        } catch (BApp\Storage\Db\Exception\EntityNotFoundException $e){
        }
    }

    /**
     * @When /^I log in as "([^"]*)"$/
     */
    public function iLogInAs($user_email)
    {
        $this->thereIsAnValidTokenForUserWithValue('TOKEN_TOKEN_LOGIN', $user_email, 'BEHAT_LOGIN_TEST');

        $this->visit("/logout");

        $this->visit("/user/token-login/BEHAT_LOGIN_TEST");

        $this->iWaitSecondsForTheReadyStateToComplete(self::$waitTime);
    }

    /**
     * @Given /^the building owner for (\d+) is (\d+)$/
     */
    public function theBuildingOwnerForIs($buildingId, $ownerId){

        /* @var $buildingStorage \BAppStorage\Building */
        $buildingStorage = BApp\Di::getDi()->get('BAppStorage\Building');

        $building = $buildingStorage->fetchObject($buildingId);

        $building->setOwnerId($ownerId);

        $buildingStorage->store($building);
    }

    /**
     * @Given /^I wait "([^"]*)"$/
     */
    public function iWait($time)
    {
        $this->getSession()->wait($time, false);
    }

    /**
     * @Given /^I click "([^"]*)"$/
     */
    public function iClick($css_element)
    {
        $this->getMainContext()->assertSession()->elementExists('css',$css_element);
        $element = $this->getSession()->getPage()->find('css', $css_element);
        $element->click();
    }

    /**
     * @Given /^the element \'([^\']*)\' should contain "([^"]*)"$/
     */
    public function theElementShouldContain($element, $str){
        if ($this->getMainContext()->getSession()->evaluateScript('return $(\''. $element . '\').length == 0')){
            throw new ExpectationException( 'Element like ' . $element
                . ' does not exist', $this->getSession());
        }
        if (strstr($this->getMainContext()->getSession()->evaluateScript('return $(\''. $element . '\').html()'), $str) == false){
            throw new ExpectationException( 'Element ' . $element
                . ' does not contain "' . $str . '"', $this->getSession());
        }
    }
    /**
     * @Given /^I click \'([^\']*)\' a tooltip with \'([^\']*)\' class appears in (\d+) seconds$/
     */
    public function iClickATooltipWithClassAppearsInSeconds($div, $class, $time){
        $this->getSession()->wait($time * 1000, false);
        if ($this->getMainContext()->getSession()->evaluateScript('return $(\'' . $div . '\').length == 0')){
            throw new ExpectationException( 'Element like ' . $div
                . ' does not exists', $this->getSession());
        }
        $id = $this->getMainContext()->getSession()->evaluateScript('return $(\'' . $div . '\').id');
        $this->getMainContext()->getSession()->executeScript('$(\'' . $div . '\').trigger(\'click\');');
        $this->getSession()->wait($time, false);
        if ($this->getMainContext()->getSession()->evaluateScript('return $(\'div[id^="'. $id . '"].' . $class . '\').length == 0')){
            throw new ExpectationException('Popup for ' . $div
                . ' does not exists', $this->getSession());
        }
        if ($this->getMainContext()->getSession()->evaluateScript('return $(\'div[id^="'. $id . '"].' . $class . '\').is(\':visible\') != true;')){
            throw new ExpectationException( $id . '_popup'
                . ' is not visible', $this->getSession());
        }
    }
    /**
     * @Given /^I follow "([^"]*)" and wait "([^"]*)" to be loaded in max "([^"]*)" seconds$/
     */
    public function iFollowAndWaitToBeLoadedInMaxSeconds($link, $url, $sec)
    {
        $this->clickLink($link);

        $this->iWaitSecondsForTheReadyStateToComplete($sec);

        $this->assertPageAddress($url);
    }

    /**
     * @Then /^I wait "([^"]*)" seconds for the ready state to complete$/
     */
    public function iWaitSecondsForTheReadyStateToComplete($sec)
    {
        $this->getMainContext()->getSession()->wait($sec * 1000, 'document.readyState === "complete"');
    }

    /**
     * @Given /^I buy "([^"]*)" oligos using the \"Fake Payment\" payment service$/
     */
    public function iBuyOligosUsingThePaymentService($sum)
    {
        $this->clickLink('Buy more');
        $this->iWaitSecondsForTheReadyStateToComplete(self::$waitTime);
        $this->assertPageAddress("/user/buy-oligos");
        $this->selectOption("oligos_qty", "$sum Oligos");
        $this->getSession()->getPage()->pressButton("Purchase using Fake Payment");

        $this->assertPageAddress("/fake/payment");
    }

    /**
     * @Given /^I redeem "([^"]*)" oligos$/
     */
    public function iRedeemOligos($sum)
    {
        $this->clickLink('Cash out');
        $this->iWaitSecondsForTheReadyStateToComplete(self::$waitTime);
        $this->assertPageAddress("/user/redeem-oligos");
        $this->selectOption("oligos_qty", "$sum Oligos");
        $this->getSession()->getPage()->pressButton("Redeem");
        $this->iWaitSecondsForTheReadyStateToComplete(self::$waitTime);
    }

    /**
     * @When /^I buy the image "([^"]*)"$/
     */
    public function iBuyTheImage($image_id)
    {
        $this->visit('/buy-image/' . $image_id);
        $this->iWaitSecondsForTheReadyStateToComplete(self::$waitTime);
    }
}
