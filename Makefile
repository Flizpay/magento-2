# FlizPay Payment for Magento — development commands
#
# Package targets run against this repository alone. Magento targets need a
# Magento installation; point MAGENTO_ROOT at it:
#
#   make test-unit MAGENTO_ROOT=../magento-store
#
# When MAGENTO_ROOT contains the bin/clinotty wrapper (the sibling
# magento-store Docker environment), commands automatically run inside its
# PHP container. Set MAGENTO_EXEC="" to force direct execution.

MAGENTO_ROOT ?= ../magento-store
MODULE_PATH  ?= app/code/FlizPay/Payment
MAGENTO_EXEC ?= $(shell [ -x "$(MAGENTO_ROOT)/bin/clinotty" ] && echo ./bin/clinotty)

.DEFAULT_GOAL := help

.PHONY: help validate validate-xml lint test-unit test-integration test \
	analyse phpcs format compile check magento-root

##@ Package checks (no Magento required)

validate: ## Validate the Composer package
	composer validate --no-check-publish

validate-xml: ## Validate every tracked XML file
	@git ls-files -z '*.xml' '*.xml.dist' | xargs -0 -n1 php -r \
		'if (@simplexml_load_file($$argv[1]) === false) { fwrite(STDERR, "Invalid XML: {$$argv[1]}\n"); exit(1); } echo "OK ", $$argv[1], "\n";' --

lint: ## Check PHP syntax of every tracked PHP file
	@git ls-files -z -co --exclude-standard --deduplicate '*.php' \
		| xargs -0 -n1 sh -c '[ ! -f "$$0" ] || php -l "$$0" > /dev/null' \
		&& echo "No PHP syntax errors."

##@ Tests (require MAGENTO_ROOT)

test-unit: magento-root ## Run the unit test suite
	cd $(MAGENTO_ROOT) && $(MAGENTO_EXEC) vendor/bin/phpunit \
		-c $(MODULE_PATH)/phpunit.xml.dist

test-integration: magento-root ## Run the Magento integration test suite
	cd $(MAGENTO_ROOT) && $(MAGENTO_EXEC) bash -c \
		'cd dev/tests/integration && ../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../$(MODULE_PATH)/Test/Integration'

test: test-unit test-integration ## Run unit and integration tests

##@ Code quality (require MAGENTO_ROOT)

analyse: magento-root ## Run PHPStan static analysis
	cd $(MAGENTO_ROOT) && $(MAGENTO_EXEC) vendor/bin/phpstan analyse \
		$(MODULE_PATH)/Api \
		$(MODULE_PATH)/Block \
		$(MODULE_PATH)/Controller \
		$(MODULE_PATH)/Gateway \
		$(MODULE_PATH)/Model \
		$(MODULE_PATH)/Service \
		$(MODULE_PATH)/Test/Unit \
		$(MODULE_PATH)/registration.php \
		--level=6 --no-progress

phpcs: magento-root ## Run Magento coding standards
	cd $(MAGENTO_ROOT) && $(MAGENTO_EXEC) vendor/bin/phpcs \
		--standard=$(MODULE_PATH)/phpcs.xml.dist \
		--runtime-set ignore_warnings_on_exit 1 $(MODULE_PATH)

format: magento-root ## Format PHP with Magento PHPCBF
	cd $(MAGENTO_ROOT) && $(MAGENTO_EXEC) vendor/bin/phpcbf \
		--standard=$(MODULE_PATH)/phpcs.xml.dist $(MODULE_PATH)

compile: magento-root ## Compile Magento dependency injection
	cd $(MAGENTO_ROOT) && $(MAGENTO_EXEC) bin/magento setup:di:compile

##@ Aggregates

check: validate validate-xml lint test-unit ## Package checks plus unit tests

##@ Help

help: ## Show this help
	@awk 'BEGIN { \
			FS = ":.*##"; \
			printf "\nFlizPay Payment for Magento\n"; \
			printf "\nUsage:\n  make \033[36m<target>\033[0m [MAGENTO_ROOT=path] [MODULE_PATH=path] [MAGENTO_EXEC=cmd]\n"; \
		} \
		/^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5); next } \
		/^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2 }' \
		$(MAKEFILE_LIST)
	@printf '\nDefaults:\n'
	@printf '  MAGENTO_ROOT = %s\n' '$(MAGENTO_ROOT)'
	@printf '  MODULE_PATH  = %s\n' '$(MODULE_PATH)'
	@printf '  MAGENTO_EXEC = %s\n\n' '$(if $(MAGENTO_EXEC),$(MAGENTO_EXEC),<run directly>)'

magento-root:
	@if [ ! -d "$(MAGENTO_ROOT)" ]; then \
		printf 'error: MAGENTO_ROOT "%s" does not exist.\n' '$(MAGENTO_ROOT)'; \
		printf 'Point MAGENTO_ROOT at a Magento installation that contains the module at\n'; \
		printf 'MODULE_PATH (default app/code/FlizPay/Payment), for example:\n'; \
		printf '  make test-unit MAGENTO_ROOT=../magento-store\n'; \
		exit 1; \
	fi
