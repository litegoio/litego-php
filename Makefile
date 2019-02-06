install: composer.phar
	./composer.phar install

update:
	./composer.phar update

composer.phar:
	curl -s http://getcomposer.org/installer | php

build:
	mkdir build

clean:
	rm composer.phar
	rm -r vendor
	rm -r build
