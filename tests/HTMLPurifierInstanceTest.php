<?php

class HTMLPurifierInstanceTest extends PHPUnit_Framework_TestCase
{
    private $config;
    private $purifier;

    public function testCanLoadHTMLPurifier()
    {
        $this->config = HTMLPurifier_Config::createDefault();
        $this->config->set('Core.EscapeNonASCIICharacters', false);
        $this->config->set('URI.DisableResources', true);

        $this->purifier = HTMLPurifier::getInstance($this->config);

        $this->assertPurification('<img src="foo.jpg" />', '');
    }

    /**
     * Asserts a purification. Good for integration testing.
     *
     * @param string $input
     * @param string $expect
     */
    public function assertPurification($input, $expect = null)
    {
        if ($expect === null) {
            $expect = $input;
        }

        $result = $this->purifier->purify($input, $this->config);
        $this->assertEquals($expect, $result);
    }
}
