<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use Html;
use OOUI;
use Sanitizer;

/**
 * Assembles the HTML fragments for multiple-instance template sections
 * within a PageForms form: the wrapper, per-instance table, single instance
 * div, and the closing adder block.
 */
class MultipleTemplateHtmlBuilder {

	/**
	 * Creates the opening HTML for a multiple-instance template wrapper.
	 * @param \PFTemplateInForm $tif
	 * @return string
	 */
	public function multipleTemplateStartHTML( \PFTemplateInForm $tif ): string {
		$text = "\t" . '<div class="multipleTemplateWrapper">' . "\n";
		$attrs = [ 'class' => 'multipleTemplateList' ];
		if ( $tif->getMinInstancesAllowed() !== null ) {
			$attrs['minimumInstances'] = $tif->getMinInstancesAllowed();
		}
		if ( $tif->getMaxInstancesAllowed() !== null ) {
			$attrs['maximumInstances'] = $tif->getMaxInstancesAllowed();
		}
		if ( $tif->getDisplayedFieldsWhenMinimized() != null ) {
			$attrs['data-displayed-fields-when-minimized'] = $tif->getDisplayedFieldsWhenMinimized();
		}
		$text .= "\t" . Html::openElement( 'div', $attrs ) . "\n";
		return $text;
	}

	/**
	 * Creates the HTML for the inner table for every instance of a
	 * multiple-instance template in the form.
	 * @param bool $formIsDisabled
	 * @param string $mainText
	 * @return string
	 */
	public function multipleTemplateInstanceTableHTML( bool $formIsDisabled, string $mainText ): string {
		if ( $formIsDisabled ) {
			$addAboveButton = $removeButton = '';
		} else {
			$addAboveButton = Html::element( 'a', [
				'class' => "addAboveButton",
				'title' => wfMessage( 'pf_formedit_addanotherabove' )->text()
			] );
			$removeButton = Html::element( 'a', [
				'class' => "removeButton",
				'title' => wfMessage( 'pf_formedit_remove' )->text()
			] );
		}

		$text = <<<END
			<table class="multipleTemplateInstanceTable">
			<tr>
			<td class="instanceRearranger"></td>
			<td class="instanceMain">$mainText</td>
			<td class="instanceAddAbove">$addAboveButton</td>
			<td class="instanceRemove">$removeButton</td>
			</tr>
			</table>
END;

		return $text;
	}

	/**
	 * Creates the HTML for a single instance of a multiple-instance template.
	 * @param \PFTemplateInForm $templateInForm
	 * @param bool $formIsDisabled
	 * @param string &$section
	 * @return string
	 */
	public function multipleTemplateInstanceHTML(
		\PFTemplateInForm $templateInForm,
		bool $formIsDisabled,
		string &$section
	): string {
		global $wgPageFormsCalendarHTML;

		$wgPageFormsCalendarHTML[$templateInForm->getTemplateName()] = str_replace( '[num]', "[cf]", $section );

		// Add the character "a" onto the instance number of this input
		// in the form, to differentiate the inputs the form starts out
		// with from any inputs added by the Javascript.
		$section = str_replace( '[num]', "[{$templateInForm->getInstanceNum()}a]", $section );
		// @TODO - this replacement should be
		// case- and spacing-insensitive.
		// Also, keeping the "id=" attribute should not be
		// necessary; but currently it is, for "show on select".
		$section = preg_replace_callback(
			'/ id="(.*?)"/',
			static function ( array $matches ): string {
				$id = htmlspecialchars( $matches[1], ENT_QUOTES );
				return " id=\"$id\" data-origID=\"$id\" ";
			},
			$section
		);

		$text = "\t\t" . Html::rawElement( 'div',
			[
				// The "multipleTemplate" class is there for
				// backwards-compatibility with any custom CSS on people's
				// wikis before PF 2.0.9.
				'class' => "multipleTemplateInstance multipleTemplate"
			],
			$this->multipleTemplateInstanceTableHTML( $formIsDisabled, $section )
		) . "\n";

		return $text;
	}

	/**
	 * Creates the end of the HTML for a multiple-instance template —
	 * including the sections necessary for adding additional instances.
	 * @param \PFTemplateInForm $templateInForm
	 * @param bool $formIsDisabled
	 * @param string $section
	 * @return string
	 */
	public function multipleTemplateEndHTML(
		\PFTemplateInForm $templateInForm,
		bool $formIsDisabled,
		string $section
	): string {
		global $wgPageFormsTabIndex;

		$text = "\t\t" . Html::rawElement( 'div',
			[
				'class' => "multipleTemplateStarter",
				'style' => "display: none",
			],
			$this->multipleTemplateInstanceTableHTML( $formIsDisabled, $section )
		) . "\n";

		$attributes = [
			'tabIndex' => $wgPageFormsTabIndex,
			'classes' => [ 'multipleTemplateAdder' ],
			'label' => Sanitizer::decodeCharReferences( $templateInForm->getAddButtonText() ),
			'icon' => 'add'
		];
		if ( $formIsDisabled ) {
			$attributes['disabled'] = true;
			$attributes['classes'] = [];
		}
		$button = new OOUI\ButtonWidget( $attributes );
		$text .= <<<END
	</div><!-- multipleTemplateList -->
		<p>$button</p>
		<div class="pfErrorMessages"></div>
	</div><!-- multipleTemplateWrapper -->
</fieldset>
END;
		return $text;
	}
}
