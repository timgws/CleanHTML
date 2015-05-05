<?php
namespace timgws\CleanHTML;

Class CleanHTML {
    private $html;

    private $defaultAllowedTags = 'h1,h2,h3,h4,h5,p,strong,b,ul,ol,li,hr,pre,code';

    private $defaultOptions = array (
        'images' => false,
        'italics' => false,
        'links' => false,
        'strip' => false,
        'table' => false,
    );

    private $options;

    public function __construct($options = null)
    {
        $this->options = $this->defaultOptions;

        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

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

    function CleanHTML ($contents) {
        $this->html = $contents;
    }

    function Clean(array $options = null) {
        // 0: remove duplicate spaces
        $no_spaces = preg_replace('@(\s|&nbsp;){2,}@', ' ', $this->html);
        $no_spaces = preg_replace("/<(\w*)>(\s|&nbsp;)/", '<\1>', $no_spaces);

        // Try and replace excel new lines as paragraphs :)
        $no_spaces = preg_replace("|(\s*)?<br />(\s*)?<br />|", "<p>", $no_spaces);

        $content  = '<!DOCTYPE html><meta charset="utf-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
        $content .= preg_replace("/<(\w*)[^>]*>[\s|&nbsp;]*<\/\\1>/", '', $no_spaces);
        unset($no_spaces);

        $doc = new DOMDocument;
        @$doc->loadHTML($content);
        $doc->encoding = 'UTF-8';
        $xp = new DOMXPath($doc);

        // 1: remove any of the script tags.
        foreach ($xp->query('//script') as $node) {
            $node->parentNode->removeChild($node);
        }

        // 2: First clean of all the obscure tags...
        $output = self::obscureClean($doc, true);

        //
        //$output = $this->html;

        $allowedTags = 'h1,h2,h3,h4,h5,p,strong,b,ul,ol,li,hr,pre,code';

        // TODO: create a default array, and merge the results.
        if (is_array($options)) {
            if (isset($options['images']) && $options['images'] == true)
                $allowedTags .= ',img[src|alt]';

            if (isset($options['links']) && $options['links']  == true)
                $allowedTags .= ',a[href|target]';

            if (isset($options['italics']) && $options['italics']  == true)
                $allowedTags .= ',em,i';

            if (isset($options['table']) && $options['table']  == true)
                $allowedTags .= ',table,tr,td';

            if (isset($options['strip']) && $options['strip']  == true)
                $allowedTags = '';
        }

        // 3: Send the tidy html to htmlpurifier
        if (file_exists('lib/HTMLPurifier/HTMLPurifier.auto.php')) {
            require_once 'lib/HTMLPurifier/HTMLPurifier.auto.php';
            $config = HTMLPurifier_Config::createDefault();
            $config->set('Core.EscapeNonASCIICharacters', false);
            $config->set('CSS.AllowedProperties', array());
            $config->set('Core.Encoding', 'utf-8');
            $config->set('AutoFormat.RemoveEmpty', true);
            $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);
            $config->set('HTML.Allowed', $allowedTags);
            $purifier = new HTMLPurifier($config);
            $output = $purifier->purify($output);
        }

        // 4: Cool, do one more clean to pick up any p/strong etc tags that might have
        // been missed.
        $doc = new DOMDocument;
        $content = '<!DOCTYPE html><meta charset="utf-8"><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$output;
        @$doc->loadHTML($content);
        $doc->encoding = 'UTF-8';
        $xp = new DOMXPath($doc);

        $output = self::obscureClean($doc);

        // Remove the newline character at the end of the HTML if there is one there.
        $len = strlen($output);
        if (substr($output, $len-1, 1) == "\n")
            $output = substr($output, 0, $len-1);

        return $output;
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

    static function obscureClean(DOMDocument $doc, $first = false) {
        // 1: rename h1 tags to h2 tags
        $xp = new DOMXPath($doc);
        $doc->encoding = 'UTF-8';
        foreach ($xp->query('//h1') as $node) {
            $parent = $node->parentNode;
            $parent = $node;
            $header = $doc->createElement('h2');
            $parent->parentNode->replaceChild($header, $parent);
            $header->appendChild($doc->createTextNode( $node->textContent ));
        }

        // 2: change short <p><strong> pairs to h2 tags
        $xp = new DOMXPath($doc);
        foreach ($xp->query('//p/strong') as $node) {
            $parent = $node->parentNode;
            if ($parent->textContent == $node->textContent &&
                    str_word_count($node->textContent) <= 8 &&
                    $node->childNodes->item(0)->nodeType == XML_TEXT_NODE) {
                $header = $doc->createElement('h2');
                $parent->parentNode->replaceChild($header, $parent);
                $header->appendChild($doc->createTextNode( $node->textContent ));
            }
        }

        // Get rid of these annoying <h2><strong> tags that get generated after
        // adding new headers. I tried to share code w/ above p/strong, didn't work
        $xp = new DOMXPath($doc);
        foreach ($xp->query('//h2/strong') as $node) {
            $parent = $node->parentNode;
            if ($parent->textContent == $node->textContent &&
                    $node->childNodes->item(0)->nodeType == XML_TEXT_NODE) {
                $header = $doc->createElement('h2');
                $parent->parentNode->replaceChild($header, $parent);
                $header->appendChild($doc->createTextNode( $node->textContent ));
            }
        }

        // 3: remove obscure span stylings
        foreach ($xp->query('//p/span') as $node) {
            $sibling = $node->firstChild;
            do {
                $next = $sibling->nextSibling;
                $node->parentNode->insertBefore($sibling, $node);
            } while ($sibling = $next);
            $node->parentNode->removeChild($node);
        }

        // -- Save the contents. Strip out the added tags from loadHTML()
        $xp = new DOMXPath($doc);
        $doc->encoding = 'UTF-8';
        $everything = $xp->query("body/*"); // retrieves all elements inside body tag
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

    static function cleanDOMNode(DOMNode &$domNode) {
        foreach ($domNode->childNodes as $node)
        {
            if($node->hasChildNodes()) {
                self::cleanDOMNode($node);
            }
        }    
    }

    // The following functions are borrowed from WordPress... Thanks guys!
    /**
      * Newline preservation help function for wpautop
      *
      * @since 3.1.0
      * @access private
      *
      * @param array $matches preg_replace_callback matches array
      * @return string
      */
    static function _autop_newline_preservation_helper( $matches ) {
        return str_replace("\n", "<WPPreserveNewline />", $matches[0]);
    }

    /**
      * Replaces double line-breaks with paragraph elements.
      *
      * A group of regex replaces used to identify text formatted with newlines and
      * replace double line-breaks with HTML paragraph tags. The remaining
      * line-breaks after conversion become <<br />> tags, unless $br is set to '0'
      * or 'false'.
      *
      * @since 0.71
      *
      * @from WordPress (trunk) r24026
      *
      * @param string $pee The text which has to be formatted.
      * @param bool $br Optional. If set, this will convert all remaining line-breaks after paragraphing. Default true.
      * @return string Text which has been converted into correct paragraph tags.
      */
    static function autop($pee, $br = true) {
        $pre_tags = array();

        if ( trim($pee) === '' )
            return '';

        $pee = $pee . "\n"; // just to make things a little easier, pad the end

        if ( strpos($pee, '<pre') !== false ) {
            $pee_parts = explode( '</pre>', $pee );
            $last_pee = array_pop($pee_parts);
            $pee = '';
            $i = 0;

            foreach ( $pee_parts as $pee_part ) {
                $start = strpos($pee_part, '<pre');

                // Malformed html?
                if ( $start === false ) {
                    $pee .= $pee_part;
                    continue;
                }

                $name = "<pre wp-pre-tag-$i></pre>";
                $pre_tags[$name] = substr( $pee_part, $start ) . '</pre>';

                $pee .= substr( $pee_part, 0, $start ) . $name;
                $i++;
            }

            $pee .= $last_pee;
        }

        $pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
        // Space things out a little
        $allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|noscript|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
        $pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
        $pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
        $pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
        if ( strpos($pee, '<object') !== false ) {
            $pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
            $pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
        }
        $pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
        // make paragraphs, including one at the end
        $pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
        $pee = '';
        foreach ( $pees as $tinkle )
            $pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
        $pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
        $pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $pee);
        $pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
        $pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
        $pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
        $pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
        $pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
        $pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
        if ( $br ) {
            $pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', 'CleanHTML::_autop_newline_preservation_helper', $pee);
            $pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
            $pee = str_replace('<WPPreserveNewline />', "\n", $pee);
        }
        $pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
        $pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
        $pee = preg_replace( "|\n</p>$|", '</p>', $pee );

        if ( !empty($pre_tags) )
            $pee = str_replace(array_keys($pre_tags), array_values($pre_tags), $pee);

        return $pee;
    }

}
