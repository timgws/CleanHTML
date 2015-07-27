<?php namespace timgws\CleanHTML;

use DOMDocument, DOMXPath;
use HTMLPurifier_Config, HTMLPurifier;

/**
 * Clean HTML pages, get rid of unnecessary tags.
 *
 * Class CleanHTML
 * @package timgws\CleanHTML
 */
class CleanHTML {
    /**
     * @var string Tags that will always be allowed by default
     */
    private $defaultAllowedTags = 'h1,h2,h3,h4,h5,p,strong,b,ul,ol,li,hr,pre,code';

    /**
     * @var array list of all the options that will by default be initialised with.
     */
    private $defaultOptions = array (
        'images' => false,
        'italics' => false,
        'links' => false,
        'strip' => false,
        'table' => false,
    );

    /**
     * @var string blank HTML with UTF-8 encoding.
     */
    private static $blankHTML = '<!DOCTYPE html><meta charset="utf-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';

    /**
     * @var array When an option is set, add this to the default allowed list.
     */
    private $optionsAdd = array (
        'images' => ',img[src|alt]',
        'links' => ',a[href|target]',
        'italics' => ',em,i',
        'table' => ',table,tr,td'
    );

    /**
     * @var array the local copy of the options that have been set by the developer using this class
     */
    private $options;

    /**
     * @param array|null $options
     * @throws CleanHTMLException
     */
    public function __construct(array $options = null)
    {
        $this->options = $this->defaultOptions;

        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * Set a list of options on the class.
     *
     * @param $settingOptions
     * @throws CleanHTMLException
     */
    public function setOptions($settingOptions)
    {
        $defaultKeys = array_keys($this->defaultOptions);
        $settingKeys = array_keys($settingOptions);

        foreach($settingKeys as $_option)
        {
            if (!in_array($_option, $defaultKeys))
                throw new CleanHTMLException("$_option does not exist as a settable option.");

            $this->options[$_option] = $settingOptions[$_option];
        }
    }

    /**
     * Get the options set on the class.
     *
     * This is used by tests to ensure that setting options works as expected.
     *
     * @return array the options that are on this class.
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Build the allowed tags string, based on the default allowed tags and options that are set.
     *
     * @return string
     */
    private function getAllowedTags()
    {
        $allowedTags = $this->defaultAllowedTags;

        foreach ($this->options as $name => $value) {
            if (isset($this->optionsAdd[$name]) && $value === true) {
                $allowedTags .= ',' . $this->optionsAdd[$name];
            }
        }

        if ($this->options['strip'] === true)
            $allowedTags = '';

        return $allowedTags;
    }

    /**
     * Create a DOMDocument from a HTML string.
     *
     * @param $html
     * @return DOMDocument
     */
    private function preCleanHTML($html)
    {
        // 0: remove duplicate spaces
        $no_spaces = preg_replace('@(\s|&nbsp;){2,}@', ' ', $html);
        $no_spaces = preg_replace("/<(\w*)>(\s|&nbsp;)/", '<\1>', $no_spaces);

        // Try and replace excel new lines as paragraphs :)
        $no_spaces = preg_replace("|(\s*)?<br />(\s*)?<br />|", "<p>", $no_spaces);

        $content = self::$blankHTML;
        $content .= preg_replace("/<(\w*)[^>]*>[\s|&nbsp;]*<\/\\1>/", '', $no_spaces);
        unset($no_spaces);

        return $content;
    }

    private function createDOMDocumentFromHTML($html, $firstRun = true)
    {
        if ($firstRun)
            $content = $this->preCleanHTML($html);

        $doc = new DOMDocument;
        @$doc->loadHTML($content);
        $doc->encoding = 'UTF-8';

        return $doc;
    }

    private function createHTMLPurifier()
    {
        $allowedTags = $this->getAllowedTags();

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.EscapeNonASCIICharacters', false);
        $config->set('CSS.AllowedProperties', array());
        $config->set('Core.Encoding', 'utf-8');
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);
        $config->set('HTML.Allowed', $allowedTags);
        $purifier = new HTMLPurifier($config);

        return $purifier;
    }

    /**
     * Clean HTML.
     *
     * @param $html
     * @return mixed|string
     */
    function clean($html)
    {
        $cleaningFunctions = new Methods();
        $doc = $this->createDOMDocumentFromHTML($html);

        // 1: remove any of the script tags.
        $doc = $cleaningFunctions->removeScriptTags($doc);

        // 2: First clean of all the obscure tags...
        $output = self::obscureClean($doc, true);
        $output = $this->purify($output);

        // 4: Cool, do one more clean to pick up any p/strong etc tags that might have
        // been missed.
        return $this->finalClean($output);
    }

    private function removeLastNewLine($input) {
        // Remove the newline character at the end of the HTML if there is one there.
        $len = strlen($input);
        if (substr($input, $len-1, 1) == "\n")
            return substr($input, 0, $len-1);

        return $input;
    }

    static function changeQuotes($input) {
        $quotes = array(
                "\xC2\xAB"     => '"', // « (U+00AB) in UTF-8
                "\xC2\xBB"     => '"', // » (U+00BB) in UTF-8
                "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
                "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
                "\xE2\x80\x9A" => "'", // ‚ (U+201A) in UTF-8
                "\xE2\x80\x9B" => "'", // ‛ (U+201B) in UTF-8
                "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
                "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
                "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
                "\xE2\x80\x9F" => '"', // ‟ (U+201F) in UTF-8
                "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
                "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8
            );

        $output = strtr($input, $quotes);
        return $output;
    }

    /**
     * Custom functions for cleaning out elements that are inside documents that should not be allowed in.
     * @param DOMDocument $doc
     * @param bool $first
     * @return mixed|string
     */
    static function obscureClean(DOMDocument $doc, $first = false)
    {
        $cleaningFunctions = new Methods();
        $doc->encoding = 'UTF-8';

        // 1: rename h1 tags to h2 tags
        $doc = $cleaningFunctions->renameH1TagsToH2($doc);

        // 2: change short <p><strong> pairs to h2 tags
        $doc = $cleaningFunctions->changeShortBoldToH2($doc);

        // Get rid of these annoying <h2><strong> tags that get generated after
        // adding new headers. I tried to share code w/ above p/strong, didn't work
        $doc = $cleaningFunctions->removeBoldH2Tags($doc);

        // 3: remove obscure span stylings
        $doc = $cleaningFunctions->removeObscureSpanStylings($doc);

        // 4: remove obscure paragraphs inside line items (google docs)
        // NOTE: Might break. TODO: Fix me.
        $doc = $cleaningFunctions->removeObscureParagraphsInsideLineItems($doc, $first);

        return self::exportHTML($doc);
    }

    static function exportHTML(DOMDocument $doc) {
        // -- Save the contents. Strip out the added tags from loadHTML()
        $xpath = new DOMXPath($doc);
        $doc->encoding = 'UTF-8';
        $everything = $xpath->query("body/*"); // retrieves all elements inside body tag
        $output = '';
        if ($everything->length > 0) { // check if it retrieved anything in there
            foreach ($everything as $thing) {
                $output .= $doc->saveXML($thing) . "\n";
            }
        }

        $output = str_replace("\xc2\xa0",' ',$output); // Nasty UTF-8 &nbsp;
        $output = preg_replace("#(\n\s*){2,}#", "\n", $output); // Replace newlines with one
        $output = preg_replace("#\s\s+$#", "", $output); // Multi-spaces condensed
        return $output;
    }

    /**
     * Purify HTML
     *
     * @param $input
     * @return string
     */
    private function purify($input)
    {
        $purifier = $this->createHTMLPurifier();
        $output = $purifier->purify($input);

        return $output;
    }

    /**
     * @param $input
     * @return string
     */
    private function finalClean($input)
    {
        $doc = new DOMDocument;
        $content = self::$blankHTML . $input;
        @$doc->loadHTML($content);
        $doc->encoding = 'UTF-8';

        $output = self::obscureClean($doc);

        return $this->removeLastNewLine($output);
    }
}
