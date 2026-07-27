MAGENTO_STORE_ROOT ?= ../magento-store
MAGENTO_PACKAGE_PATH := vendor/flizpay/magento2

.DEFAULT_GOAL := help

.PHONY: help validate lint analyse test-unit test-integration phpcs compile

help:
	@printf '%s\n' \
		'FlizPay Magento development commands:' \
		'  make validate          Validate the Composer package' \
		'  make lint              Check PHP syntax' \
		'  make analyse           Run PHPStan' \
		'  make test-unit         Run unit tests' \
		'  make test-integration  Run Magento integration tests' \
		'  make phpcs             Run Magento coding standards' \
		'  make compile           Compile Magento dependency injection'

validate:
	composer validate --no-check-publish

lint:
	@git ls-files -co --exclude-standard '*.php' | xargs -n1 php -l

analyse:
	cd $(MAGENTO_STORE_ROOT) && ./bin/clinotty vendor/bin/phpstan analyse \
		/var/www/flizpay-magento/registration.php \
		/var/www/flizpay-magento/Test/Unit \
		--level=6 --no-progress

test-unit:
	cd $(MAGENTO_STORE_ROOT) && ./bin/clinotty vendor/bin/phpunit \
		-c $(MAGENTO_PACKAGE_PATH)/phpunit.xml.dist

test-integration:
	cd $(MAGENTO_STORE_ROOT) && ./bin/clinotty bash -lc \
		'cd dev/tests/integration && ../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../$(MAGENTO_PACKAGE_PATH)/Test/Integration'

phpcs:
	cd $(MAGENTO_STORE_ROOT) && ./bin/phpcs $(MAGENTO_PACKAGE_PATH)

compile:
	cd $(MAGENTO_STORE_ROOT) && ./bin/magento setup:di:compile
