#!/bin/bash
# Run tests using composer docker image

# Install dependencies including dev dependencies
docker run --rm -v $(pwd):/app composer:2 composer install

# Run PHPUnit tests
docker run --rm -v $(pwd):/app -w /app php:8.2-cli ./vendor/bin/phpunit

echo "Tests completed!"
