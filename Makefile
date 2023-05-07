SRC_FILES = $(shell find example src -type f -name '*.php')

README.md: $(SRC_FILES) .mddoc.xml.dist
	vendor/bin/mddoc

.PHONY: fix
fix:
	vendor/bin/php-cs-fixer fix
	vendor/bin/phpcbf

.PHONY: lint
lint:
	vendor/bin/php-cs-fixer fix --dry-run
	vendor/bin/phpcs


.PHONY: test
test:
	vendor/bin/phpunit
