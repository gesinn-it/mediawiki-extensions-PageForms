<?php

declare( strict_types=1 );

use MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\PageForms\FormDefinition\FormDefinitionException
 */
class FormDefinitionExceptionTest extends TestCase {

	public function testTheMessageIsPlainTextWithoutTheDetail(): void {
		$e = new FormDefinitionException( "Error with <b>'x'</b> & more" );

		$this->assertSame( "Error with <b>'x'</b> & more", $e->getMessage() );
		$this->assertSame( "Error with <b>'x'</b> & more", $e->getErrorText() );
		$this->assertNull( $e->getDetail() );
	}

	public function testTheMessageAddsTheDetailOnANewLine(): void {
		$e = new FormDefinitionException( 'Something is wrong:', '{{{field|<i>' );

		$this->assertSame( "Something is wrong:\n{{{field|<i>", $e->getMessage() );
		$this->assertSame( '{{{field|<i>', $e->getDetail() );
	}

	public function testTheHtmlEscapesTheErrorText(): void {
		$e = new FormDefinitionException( "'tag' is <script>bad</script> & worse" );

		$this->assertSame(
			'<div class="error">\'tag\' is &lt;script&gt;bad&lt;/script&gt; &amp; worse</div>',
			$e->getErrorHtml()
		);
	}

	public function testTheHtmlShowsTheEscapedDetailInAPreElement(): void {
		$e = new FormDefinitionException( 'Wrong:', '<img src=x onerror=alert(1)> & "q"' );

		$this->assertSame(
			"<div class=\"error\">Wrong:</div>\n<pre>&lt;img src=x onerror=alert(1)&gt; &amp; &quot;q&quot;</pre>",
			$e->getErrorHtml()
		);
	}
}
