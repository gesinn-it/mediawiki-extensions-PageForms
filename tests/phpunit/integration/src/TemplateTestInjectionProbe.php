<?php

/**
 * Marker class used only to prove that unserialize() in
 * Template::loadTemplateParams() cannot be tricked into instantiating
 * arbitrary objects.
 */
class TemplateTestInjectionProbe {

	/**
	 * @var bool
	 */
	public $triggered = true;
}
