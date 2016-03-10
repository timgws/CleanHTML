<?php

use \timgws\CleanHTML\CleanHTML;
use \timgws\CleanHTML\ReplaceParagraphElements as RPE;

class ReplaceParagraphElementsTest extends PHPUnit_Framework_TestCase
{
    public function testCleanPeeParts()
    {
        $input = array('</pre>Clean!');
        $output = RPE::cleanPeeParts($input);

        $this->assertEquals('</pre>Clean!', $output);
    }

    /**
     * Test a conditional inside autop that specifically relates to objects.
     */
    public function testObjectScrubbing()
    {
        $input = '<p><object><param value="" name=""></object>';
        $output = RPE::autop($input);

        $this->assertEquals('<p><object><param value="" name=""></object></p>'."\n", $output);
    }

    public function getStringAutoP($messedUp = false)
    {
        return '<p>
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
    </span>'.($messedUp ? '<br /><br /><br />' : '').'
    <span style="color: #006400">
     <span style="font-size: 16px">
      <span style="color: #b22222">Test</span>
     </span>'.($messedUp ? '<pre>lol</pre>' : '').'
    </span>
   </span>
  </span>
 </strong>
</p>'.($messedUp ? '<pre href="sdf  ">lol</pre>' : '');
    }

    private function runMessedUpTest($messedUp = false)
    {
        $input = $this->getStringAutoP($messedUp);

        $test = RPE::autop($input);
        $output = new CleanHTML();

        return $output->Clean($test);
    }

    public function testMessedUpHTML()
    {
        $this->assertEquals('<h2>This is a Test</h2>', $this->runMessedUpTest());
        $this->assertEquals("<h2>This is a </h2>\n<p>Test\n</p>", $this->runMessedUpTest(true));
    }
}
