<?php

use PHPUnit\Framework\TestCase;

/**
 * Blank lines should stay blank unless the markup requires filling them
 *
 * @link https://github.com/dokuwiki/dokuwiki/issues/2614
 */
class BlankLineTest extends TestCase
{
    /**
     * @return string source code with a blank line and a whitespace only line
     */
    protected function source()
    {
        return "aaaa a=42\n\nbbbb b=56\n    \ncccc c=23";
    }

    /**
     * Plain <pre> output renders blank lines just fine, no filler needed
     *
     * @dataProvider providePreHeaders
     * @param int $header_type
     */
    public function testPlainPre($header_type)
    {
        // whitespace only lines are handled by indent() for some header types,
        // so only check the truly empty line here
        $geshi = new GeSHi("aaaa a=42\n\nbbbb b=56", 'bash');
        $geshi->enable_classes();
        $geshi->set_header_type($header_type);
        $output = $geshi->parse_code();

        $this->assertStringNotContainsString('&nbsp;', $output);
    }

    /**
     * @return array
     */
    public function providePreHeaders()
    {
        class_exists('GeSHi'); // make sure the constants are defined
        return array(
            'pre' => array(GESHI_HEADER_PRE),
            'pre valid' => array(GESHI_HEADER_PRE_VALID),
            'pre table' => array(GESHI_HEADER_PRE_TABLE),
        );
    }

    /**
     * The code has to survive a round trip through the highlighter unchanged
     *
     * This is how DokuWiki uses GeSHi and what makes copy&paste of code blocks work.
     */
    public function testRoundTrip()
    {
        $geshi = new GeSHi($this->source(), 'bash');
        $geshi->enable_classes();
        $geshi->set_header_type(GESHI_HEADER_PRE);
        $output = $geshi->parse_code();

        $this->assertSame($this->source(), $this->plainText($output));
    }

    /**
     * Lines wrapped in a block level element would collapse when empty,
     * those still need a filler
     */
    public function testHighlightedLines()
    {
        $geshi = new GeSHi($this->source(), 'bash');
        $geshi->enable_classes();
        $geshi->set_header_type(GESHI_HEADER_PRE);
        $geshi->highlight_lines_extra(array(1, 2, 3));
        $output = $geshi->parse_code();

        $this->assertStringContainsString('<span class="xtra ln-xtra">&nbsp;</span>', $output);
    }

    /**
     * Line numbers put every line into its own list item, those need a filler
     */
    public function testLineNumbers()
    {
        $geshi = new GeSHi($this->source(), 'bash');
        $geshi->enable_classes();
        $geshi->set_header_type(GESHI_HEADER_PRE);
        $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
        $output = $geshi->parse_code();

        $this->assertStringContainsString('<div class="de1">&nbsp;</div>', $output);
    }

    /**
     * Strip all markup from the given output, undoing the entity encoding
     *
     * @param string $output
     * @return string
     */
    protected function plainText($output)
    {
        $text = preg_replace('/^<[^>]+>|<[^>]+>$/', '', trim($output));
        $text = strip_tags($text);
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }
}
