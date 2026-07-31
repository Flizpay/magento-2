MAGENTO_STORE_ROOT ?= ../magento-store
MAGENTO_PACKAGE_PATH := vendor/flizpay/magento2

.DEFAULT_GOAL := help

.PHONY: help validate lint analyse test-unit test-integration phpcs format compile

help:
	@printf '%s\n' \
		'FlizPay Magento development commands:' \
		'  make validate          Validate the Composer package' \
		'  make lint              Check PHP syntax' \
		'  make analyse           Run PHPStan' \
		'  make test-unit         Run unit tests' \
		'  make test-integration  Run Magento integration tests' \
		'  make phpcs             Run Magento coding standards' \
		'  make format            Format PHP with Magento PHPCBF' \
		'  make compile           Compile Magento dependency injection'

validate:
	composer validate --no-check-publish

lint:
	@git ls-files -co --exclude-standard '*.php' | xargs -n1 php -l

analyse:
	cd $(MAGENTO_STORE_ROOT) && ./bin/clinotty vendor/bin/phpstan analyse \
		/var/www/flizpay-magento/Api \
		/var/www/flizpay-magento/Block \
		/var/www/flizpay-magento/Controller \
		/var/www/flizpay-magento/Gateway \
		/var/www/flizpay-magento/Model \
		/var/www/flizpay-magento/Service \
		/var/www/flizpay-magento/Test/Unit \
		/var/www/flizpay-magento/registration.php \
		--level=6 --no-progress

test-unit:
	cd $(MAGENTO_STORE_ROOT) && ./bin/clinotty vendor/bin/phpunit \
		-c $(MAGENTO_PACKAGE_PATH)/phpunit.xml.dist

test-integration:
	cd $(MAGENTO_STORE_ROOT) && ./bin/clinotty bash -lc \
		'cd dev/tests/integration && ../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../$(MAGENTO_PACKAGE_PATH)/Test/Integration'

phpcs:
	cd $(MAGENTO_STORE_ROOT) && ./bin/phpcs \
		--runtime-set ignore_warnings_on_exit 1 $(MAGENTO_PACKAGE_PATH)

format:
	cd $(MAGENTO_STORE_ROOT) && ./bin/phpcbf $(MAGENTO_PACKAGE_PATH)

compile:
	cd $(MAGENTO_STORE_ROOT) && ./bin/magento setup:di:compile
