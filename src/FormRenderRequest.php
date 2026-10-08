<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\PageForms;

use OutputPage;
use User;
use WebRequest;

/**
 * What the caller of FormPrinter::render() asked for.
 *
 * Complete when it is constructed and read-only afterwards. The state that is built up
 * while the form is rendered lives in FormRenderContext.
 */
final class FormRenderRequest {

	/**
	 * @param bool $formSubmitted
	 * @param bool $sourceIsPage
	 * @param bool $isQuery
	 * @param bool $isEmbedded
	 * @param bool $isAutocreate True when called by #formredlink with "create page".
	 * @param array $autocreateQuery Query parameters from #formredlink
	 * @param string|null $pageName
	 * @param string|null $pageNameFormula
	 * @param int|null $formId
	 * @param string|null $existingPageContent The wikitext of the page being edited, as it was passed in.
	 * @param WebRequest $webRequest
	 * @param User $user
	 * @param OutputPage $out
	 * @param string $scriptPath The URL path of the extension's files.
	 */
	public function __construct(
		public readonly bool $formSubmitted,
		public readonly bool $sourceIsPage,
		public readonly bool $isQuery,
		public readonly bool $isEmbedded,
		public readonly bool $isAutocreate,
		public readonly array $autocreateQuery,
		public readonly ?string $pageName,
		public readonly ?string $pageNameFormula,
		public readonly ?int $formId,
		public readonly ?string $existingPageContent,
		public readonly WebRequest $webRequest,
		public readonly User $user,
		public readonly OutputPage $out,
		public readonly string $scriptPath
	) {
	}
}
