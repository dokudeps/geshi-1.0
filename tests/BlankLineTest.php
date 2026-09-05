<?php

use PHPUnit\Framework\TestCase;

/**
 * Blank lines must not put any text into the output
 *
 * Whatever is used to keep an empty line from collapsing ends up in the
 * clipboard when the code block is selected or copied, so it may not be a
 * character.
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
     * Plain <pre> output renders blank lines just fine, no filler needed at all
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
        $this->assertStringNotContainsString(GeSHi::BLANK_LINE_FILLER, $output);
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
     * Lines wrapped in a block level element would collapse when empty, those
     * get a filler - but one that does not add any text
     *
     * @dataProvider provideWrappingSetups
     * @param int $line_numbers
     * @param array $highlight
     */
    public function testWrappedLines($line_numbers, $highlight)
    {
        $geshi = new GeSHi($this->source(), 'bash');
        $geshi->enable_classes();
        $geshi->set_header_type(GESHI_HEADER_PRE);
        $geshi->enable_line_numbers($line_numbers);
        if ($highlight) $geshi->highlight_lines_extra($highlight);
        $output = $geshi->parse_code();

        $this->assertStringContainsString(GeSHi::BLANK_LINE_FILLER, $output);
        $this->assertStringNotContainsString('&nbsp;', $output);
        $this->assertSame($this->source(), $this->plainText($output));
    }

    /**
     * @return array
     */
    public function provideWrappingSetups()
    {
        class_exists('GeSHi'); // make sure the constants are defined
        return array(
            'line numbers' => array(GESHI_NORMAL_LINE_NUMBERS, null),
            'fancy line numbers' => array(GESHI_FANCY_LINE_NUMBERS, null),
            'highlighted lines' => array(GESHI_NO_LINE_NUMBERS, array(1, 2, 3, 4, 5)),
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
     * A line of whitespace is content, it must not be swallowed by the filler
     *
     * Bare whitespace only ever reaches the output inside a <pre>, where it keeps
     * both its height and its content. indent() turns it into entities for the
     * other header types.
     *
     * @dataProvider providePreservingHeaders
     * @param int $header_type
     */
    public function testWhitespaceOnlyLine($header_type)
    {
        $geshi = new GeSHi("aaaa a=42\n    \nbbbb b=56", 'bash');
        $geshi->enable_classes();
        $geshi->set_header_type($header_type);
        $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
        $output = $geshi->parse_code();

        $this->assertStringNotContainsString(GeSHi::BLANK_LINE_FILLER, $output);
        $this->assertSame("aaaa a=42\n    \nbbbb b=56", $this->plainText($output));
    }

    /**
     * @return array
     */
    public function providePreservingHeaders()
    {
        class_exists('GeSHi'); // make sure the constants are defined
        return array(
            'pre' => array(GESHI_HEADER_PRE),
            'pre valid' => array(GESHI_HEADER_PRE_VALID),
        );
    }

    /**
     * Strip all markup from the given output, undoing the entity encoding
     *
     * List items are the only block elements in the output that stand for a
     * line, everything else is separated by newlines already.
     *
     * @param string $output
     * @return string
     */
    protected function plainText($output)
    {
        $text = trim($output);
        $text = preg_replace('/^<[^>]+>|<[^>]+>$/', '', $text);
        $text = str_replace('</li>', "\n", $text);
        $text = trim(strip_tags($text), "\n");
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }
}
