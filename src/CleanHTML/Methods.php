<?php

namespace timgws\CleanHTML;

use DOMDocument;
use DOMXPath;

class Methods
{
    /**
     * rename h1 tags to h2 tags.
     *
     * @param DOMDocument $doc
     *
     * @return DOMDocument
     */
    public function renameH1TagsToH2(DOMDocument $doc)
    {
        $xp = new DOMXPath($doc);
        $doc->encoding = 'UTF-8';
        foreach ($xp->query('//h1') as $node) {
            $parent = $node->parentNode;
            $parent = $node;
            $header = $doc->createElement('h2');
            $parent->parentNode->replaceChild($header, $parent);
            $header->appendChild($doc->createTextNode($node->textContent));
        }

        return $doc;
    }

    /**
     * change short <p><strong> pairs to h2 tags.
     *
     * @param DOMDocument $doc
     *
     * @return DOMDocument
     */
    public function changeShortBoldToH2(DOMDocument $doc)
    {
        $xp = new DOMXPath($doc);
        foreach ($xp->query('//p/strong') as $node) {
            $parent = $node->parentNode;
            if ($parent->textContent == $node->textContent &&
                str_word_count($node->textContent) <= 8 &&
                $node->childNodes->item(0)->nodeType == XML_TEXT_NODE
            ) {
                $header = $doc->createElement('h2');
                $parent->parentNode->replaceChild($header, $parent);
                $header->appendChild($doc->createTextNode($node->textContent));
            }
        }

        return $doc;
    }

    /**
     * Get rid of these annoying <h2><strong> tags that get generated after
     * adding new headers.
     *
     * @param DOMDocument $doc
     *
     * @return DOMDocument
     */
    public function removeBoldH2Tags(DOMDocument $doc)
    {
        $xp = new DOMXPath($doc);
        foreach ($xp->query('//h2/strong') as $node) {
            $parent = $node->parentNode;
            if ($parent->textContent == $node->textContent &&
                $node->childNodes->item(0)->nodeType == XML_TEXT_NODE
            ) {
                $header = $doc->createElement('h2');
                $parent->parentNode->replaceChild($header, $parent);
                $header->appendChild($doc->createTextNode($node->textContent));
            }
        }

        return $doc;
    }

    /**
     * remove obscure span stylings.
     *
     * @param DOMDocument $doc
     *
     * @return DOMDocument
     */
    public function removeObscureSpanStylings(DOMDocument $doc)
    {
        $xp = new DOMXPath($doc);
        foreach ($xp->query('//p/span') as $node) {
            $sibling = $node->firstChild;
            do {
                $next = $sibling->nextSibling;
                $node->parentNode->insertBefore($sibling, $node);
            } while ($sibling = $next);
            $node->parentNode->removeChild($node);
        }

        return $doc;
    }

    /**
     * remove obscure paragraphs inside line items (google docs).
     *
     * @note Might break.
     * @TODO Fix me.
     *
     * @param DOMDocument $doc
     * @param bool        $firstRun
     *
     * @return DOMDocument
     */
    public function removeObscureParagraphsInsideLineItems(DOMDocument $doc, $firstRun = false)
    {
        $paths_to_clean = array('li//p', 'li/p');
        if (!$firstRun) {
            // for some reason this sometimes causes issues on the first run

            $paths_to_clean[] = '//li/p';
        }

        foreach ($paths_to_clean as $path) {
            $xp = new DOMXPath($doc);
            foreach ($xp->query($path) as $node) {
                $sibling = $node->firstChild;
                do {
                    $next = $sibling->nextSibling;
                    $node->parentNode->insertBefore($sibling, $node);
                } while ($sibling = $next);
                $node->parentNode->removeChild($node);
            }
        }

        return $doc;
    }

    /**
     * Remove script tags that might exist inside the document.
     *
     * @param DOMDocument $doc
     *
     * @return DOMDocument
     */
    public function removeScriptTags(DOMDocument $doc)
    {
        $xp = new DOMXPath($doc);

        foreach ($xp->query('//script') as $node) {
            $node->parentNode->removeChild($node);
        }

        return $doc;
    }
}
