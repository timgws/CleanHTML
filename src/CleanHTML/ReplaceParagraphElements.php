<?php namespace timgws\CleanHTML;

use DOMDocument, DOMXPath;
use HTMLPurifier_Config, HTMLPurifier;

class ReplaceParagraphElements {

    /**
     * Pre tags should not be touched by autop.
     * Replace pre tags with placeholders and bring them back after autop.
     */
    static function cleanPeeParts($pee_parts)
    {
        $iteration = 0;

        $pee = '';
        foreach ( $pee_parts as $pee_part ) {
            $start = strpos($pee_part, '<pre');

            // Malformed html?
            if ( $start === false ) {
                $pee .= $pee_part;
                continue;
            }

            $name = "<pre wp-pre-tag-$iteration></pre>";
            $pre_tags[$name] = substr( $pee_part, $start ) . '</pre>';

            $pee .= substr( $pee_part, 0, $start ) . $name;
            $iteration++;
        }

        return $pee;
    }

    /**
     * Return a list of all the blocks that we are going to add a new line after
     *
     * @return string
     */
    static function allBlocks()
    {
        return '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|noscript|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
    }

    /**
     * Add new lines after a number of different blocks
     *
     * This will ensure that the HTML we output isn't in just one long line.
     * We will also get rid of multiple new lines, as well.
     *
     * @see self::autop
     * @param string $pee the input from autop
     * @return string
     *
     */
    static private function spaceOutBlocks($pee)
    {
        $pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
        // Space things out a little
        $allblocks = self::allBlocks();
        $pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
        $pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
        $pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
        if ( strpos($pee, '<object') !== false ) {
            $pee = self::cleanUpObjectTag($pee);
        }
        $pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates

        return $pee;
    }

    /**
     * Remove spaces that might have been put between object & param tags.
     *
     * @param string $pee
     * @return string
     */
    static private function cleanUpObjectTag($pee)
    {
        $pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
        $pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);

        return $pee;
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

        $pee = self::cleanBeforeAutoP($pee);
        $pee = self::spaceOutBlocks($pee);

        // make paragraphs, including one at the end
        $allblocks = self::allBlocks();
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
            $pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', function() {return str_replace("\n", "<WPPreserveNewline />", $matches[0]);}, $pee);
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

    // The following functions are borrowed from WordPress... Thanks guys!
    /**
     * Clean up <pre> tags before running autop.
     * @param $pee
     * @return string
     */
    static private function cleanBeforeAutoP($pee)
    {
        if ( trim($pee) === '' )
            return '';

        $pee = $pee . "\n"; // just to make things a little easier, pad the end

        if ( strpos($pee, '<pre') !== false ) {
            $pee_parts = explode( '</pre>', $pee );
            $last_pee = array_pop($pee_parts);

            $pee = ReplaceParagraphElements::cleanPeeParts($pee_parts) . $last_pee;
        }

        return $pee;
    }
}