-include .env
export

# setup for docker-compose-ci build directory
# delete "build" directory to update docker-compose-ci

ifeq (,$(wildcard ./build/))
    $(shell git submodule update --init --remote)
endif

EXTENSION=PageForms

# docker images
MW_VERSION?=1.43
PHP_VERSION?=8.3
DB_TYPE?=mysql
DB_IMAGE?="mysql:8"

# extensions
SMW_VERSION?=6.0.1
DT_VERSION?=4.0.3

# composer
# Enables "composer update" inside of extension
COMPOSER_EXT?=true

# nodejs
# Enables node.js related tests and "npm install"
NODE_JS?=true

# check for build dir and git submodule init if it does not exist
include build/Makefile

.PHONY: composer-phan
composer-phan: .init
ifdef COMPOSER_EXT
	$(show-current-target)
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phan $(COMPOSER_PARAMS)"
endif

.PHONY: composer-phpcs
composer-phpcs: .init
ifdef COMPOSER_EXT
	$(show-current-target)
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phpcs $(COMPOSER_PARAMS)"
endif

.PHONY: npm-eslint
npm-eslint: .init
ifdef NODE_JS
	$(show-current-target)
ifdef ESLINT_PARAMS
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && npx eslint $(ESLINT_PARAMS)"
else
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && npm run eslint"
endif
endif

.PHONY: npm-qunit
npm-qunit: .init
ifdef NODE_JS
	$(show-current-target)
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && npx qunit --require ./tests/node-qunit/setup.js $(QUNIT_PARAMS)"
endif

# Suppress git "dubious ownership" warning for the volume-mounted extension folder.
# Required after every fresh container start (make install wipes git's global config).
.PHONY: .git-safe-dir
.git-safe-dir: .init
	$(compose-exec-wiki) bash -c "git config --global --add safe.directory $(EXTENSION_FOLDER) 2>/dev/null || true"

# PHP development cycle: lint (full) + phpunit
# Optional:
#   FILTER=PFFormCacheTest   restricts phpunit via --filter
.PHONY: php-test
php-test: .git-safe-dir
ifdef COMPOSER_EXT
	$(show-current-target)
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer lint"
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phpunit$(if $(FILTER), -- --filter $(FILTER),)"
endif

# JS development cycle: eslint + banana-checker + qunit
# Optional:
#   FILE=libs/PF_formInput.js   restricts eslint to one file;
#                               also derives the matching qunit test file
#                               (libs/PF_foo.js → tests/node-qunit/PF_foo.test.js)
.PHONY: js-test
js-test: .git-safe-dir
ifdef NODE_JS
	$(show-current-target)
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && $(if $(FILE),npx eslint $(FILE),npm run eslint) && npm run banana-checker"
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && npx qunit --require ./tests/node-qunit/setup.js $(if $(FILE),$(patsubst libs/%.js,tests/node-qunit/%.test.js,$(FILE)),'tests/node-qunit/**/*.test.js')"
endif

# Full development cycle without reinstalling: lint + phpcs + phpunit + eslint + qunit
# Equivalent to 'make ci' but skips 'make install'. Run before committing.
.PHONY: dev-test
dev-test: .git-safe-dir
ifdef COMPOSER_EXT
	$(show-current-target)
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer lint"
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phpcs"
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phan"
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && composer phpunit"
endif
ifdef NODE_JS
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && npm run analyze"
	$(compose-exec-wiki) bash -c "cd $(EXTENSION_FOLDER) && npx qunit --require ./tests/node-qunit/setup.js 'tests/node-qunit/**/*.test.js'"
endif


