<?php

class PFParserFunctionHelpers {

	/**
	 * Handle the free parameter case for the forminput and formlink parser functions
	 */
	public static function handleFreeParameter( $paramName, $value, &$inQueryArr, &$hasReturnTo ): void {
		$value = html_entity_decode( $value );
		$value = urlencode( $value );
		parse_str( "$paramName=$value", $arr );
		$inQueryArr = PFUtils::arrayMergeRecursiveDistinct( $inQueryArr, $arr );
		if ( $paramName == 'returnto' ) {
			$hasReturnTo = true;
		}
	}
}
