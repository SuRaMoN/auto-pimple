
.PHONY: run-tests install-vendor update-vendor init-composer clean

install-vendor: bin/composer.phar
	bin/composer.phar install

vendor/autoload.php: bin/composer.phar
	bin/composer.phar install

run-tests: vendor/autoload.php
	vendor/bin/phpunit

update-vendor: bin/composer.phar
	bin/composer.phar update

init-composer: bin/composer.phar
	bin/composer.phar init

clean:
	rm -R bin
	rm -R vendor

bin/composer.phar:
	mkdir -p bin
	wget -O bin/composer.phar.partial http://getcomposer.org/composer.phar
	mv bin/composer.phar.partial bin/composer.phar
	chmod a+x bin/composer.phar


