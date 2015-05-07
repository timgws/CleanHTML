<?php
use \timgws\CleanHTML\CleanHTML;

class CleanHTMLTest extends PHPUnit_Framework_TestCase {
    private $simpleTest = '<img src="http://google.com">';

    /**
     * Nothing crazy, just a simple test to make sure that the class inits
     */
    public function testInitCleanHTML()
    {
        $cleanhtml = new CleanHTML();
        $this->assertArrayHasKey('images', $cleanhtml->getOptions());
    }

    /**
     * @expectedException timgws\CleanHTML\CleanHTMLException
     */
    public function testInitCleanHTMLWithInvalidSettings()
    {
        $cleanhtml = new CleanHTML(array(
            '_images' => false
        ));
    }

    public function testInitCleanHTMLWithValidSettings()
    {
        $cleanhtml = $this->createCleanHTML();

        $options = $cleanhtml->getOptions();
        $this->assertEquals(false, $options['images']);
    }

    public function testCleaningImage()
    {
        $cleanhtml = $this->createCleanHTML();
        $output = $cleanhtml->clean($this->simpleTest);

        $this->assertEquals('', $output);
    }

    public function testLibraryClaims()
    {
        $this->doSimpleContentTest(
            'Removed additional &nbsp; spaces    from HTML',
            '<p>Removed additional spaces from HTML</p>'
        );

        $this->doSimpleContentTest(
            'Remove many<br /><br /><br />, turning the text into paragraph tags!',
            "<p>Remove many</p>\n<p>, turning the text into paragraph tags!</p>"
        );

        $this->doSimpleContentTest(
            'Removes any <script></script> tags, <script>alert("YOU SHALL NOT PASS!");</script>',
            '<p>Removes any  tags, </p>'
        );

        $this->doSimpleContentTest(
            '<p>Renames any <h1>h1</h1> <strong>tags to</strong> <h2>h2</h2></p>',
            "<p>Renames any </p>\n<h2>h1</h2>\n<strong>tags to</strong>\n<h2>h2</h2>"
        );

        $this->doSimpleContentTest(
            'Changes <p><strong>p > strong</strong> tags to <h2>just h2</h2>',
            "<p>Changes </p>\n<p><strong>p &gt; strong</strong> tags to </p>\n<h2>just h2</h2>"
        );

        $this->doSimpleContentTest(
            '<p>Replaces <h2><strong>h2 > strong</strong></h2> <strong>with just</strong> <h2>h2 tags</h2></p>',
            "<p>Replaces </p>\n<h2>h2 &gt; strong</h2>\n<strong>with just</strong>\n<h2>h2 tags</h2>"
        );

        $this->doSimpleContentTest(
            '<p><span>I blame Microsoft Word for this horribleness.</span></p>',
            '<p>I blame Microsoft Word for this horribleness.</p>'
        );

        $this->doSimpleContentTest(
            '<p>I will</p><p></p><p>turn into</p><h3><strike>NICE HTML</strike></h3><p>with all the rubbish</p><p><blink>GONE</blink></p>',
            "<p>I will</p>\n<p>turn into</p>\n<h3>NICE HTML</h3>\n<p>with all the rubbish</p>\n<p>GONE</p>"
        );

        $this->doSimpleContentTest(
            '<code>Code is not allowed by default</code>',
            ''
        );

        $this->doSimpleContentTest(
            '     &nbsp;<b>&nbsp;</b>    <p><span>I blame Microsoft Word for this horribleness.</span></p>',
            '<p>I blame Microsoft Word for this horribleness.</p>'
        );
    }

    public function testLinksAllowedIfSet() {
        $this->doSimpleContentTest(
            '<p><a href="http://www.google.com.au">And no links to Google</code></p>',
            '<p>And no links to Google</p>'
        );

        $this->doSimpleContentTest(
            '<p><a href="http://www.google.com.au">And links to Google (and other sites) if explicitly allowed</a></p>',
            null,
            array('links' => true)
        );
    }

    public function testUTFDodge()
    {
        $cleanHTML = $this->createCleanHTML();
        $newContent = $cleanHTML->changeQuotes('<p>This is ‘a test’</p>');
        $this->assertEquals("<p>This is 'a test'</p>", $newContent);
    }

    private function createCleanHTML() {
        return new CleanHTML(array(
            'images' => false
        ));
    }

    public function testAutoP()
    {
        $input = '<p>
 <strong>
  <span style="font-size: 14px">
   <span style="color: #006400">
     <span style="font-size: 14px">
      <span style="font-size: 16px">
       <span style="color: #006400">
        <span style="font-size: 14px">
         <span style="font-size: 16px">
          <span style="color: #006400">This is a </span>
         </span>
        </span>
       </span>
      </span>
     </span>
    </span>
    <span style="color: #006400">
     <span style="font-size: 16px">
      <span style="color: #b22222">Test</span>
     </span>
    </span>
   </span>
  </span>
 </strong>
</p>';

        $test   = CleanHTML::autop($input);
        $output = new CleanHTML();
        $newhtml = $output->Clean($test);

        $this->assertEquals('<h2>This is a Test</h2>', $newhtml);

        $newhtml = $output->Clean('<pre href="http://google.com">testing 321</pre>');
        $this->assertEquals('<pre>testing 321</pre>', $newhtml);
    }

    private function doSimpleContentTest($actual, $expected, $options = null)
    {
        $cleanHTML = new CleanHTML($options);

        if ($expected === null)
            $expected = $actual;

        $output = $cleanHTML->clean($actual);
        $this->assertEquals($output, $expected);
    }
}