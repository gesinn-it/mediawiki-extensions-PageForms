<?php

/**
 * Marker class used only to prove that unserialize() in
 * PFTemplate::loadTemplateParams() cannot be tricked into instantiating
 * arbitrary objects.
 */
class PFTemplateTestInjectionProbe {

	/**
	 * @var bool
	 */
	public $triggered = true;
}
