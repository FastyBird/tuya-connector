# FastyBird IoT Tuya connector

[![Build Status](https://badgen.net/github/checks/FastyBird/tuya-connector/master?cache=300&style=flast-square)](https://github.com/FastyBird/tuya-connector/actions)
[![Licence](https://badgen.net/github/license/FastyBird/tuya-connector?cache=300&style=flast-square)](https://github.com/FastyBird/tuya-connector/blob/master/LICENSE.md)
[![Code coverage](https://badgen.net/coveralls/c/github/FastyBird/tuya-connector?cache=300&style=flast-square)](https://coveralls.io/r/FastyBird/tuya-connector)

![PHP](https://badgen.net/packagist/php/FastyBird/tuya-connector?cache=300&style=flast-square)
[![Latest stable](https://badgen.net/packagist/v/FastyBird/tuya-connector/latest?cache=300&style=flast-square)](https://packagist.org/packages/FastyBird/tuya-connector)
[![Downloads total](https://badgen.net/packagist/dt/FastyBird/tuya-connector?cache=300&style=flast-square)](https://packagist.org/packages/FastyBird/tuya-connector)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat-square)](https://github.com/phpstan/phpstan)

![Python](https://badgen.net/pypi/python/fastybird-tuya-connector?cache=300&style=flat-square)
[![Python latest stable](https://badgen.net/pypi/v/fastybird-tuya-connector?cache=300&style=flat-square)](https://pypi.org/project/fastybird-tuya-connector/)
[![Python downloads month](https://img.shields.io/pypi/dm/fastybird-tuya-connector?cache=300&style=flat-square)](https://pypi.org/project/fastybird-tuya-connector/)
[![Black](https://img.shields.io/badge/black-enabled-brightgreen.svg?style=flat-square)](https://github.com/psf/black)
[![MyPy](https://img.shields.io/badge/mypy-enabled-brightgreen.svg?style=flat-square)](http://mypy-lang.org)

## What is FastyBird IoT Tuya connector?

Tuya connector is a combined [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) extension which is integrating [Tuya](https://www.tuya.com) protocol for connected devices

[FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) Tuya connector is
an [Apache2 licensed](http://www.apache.org/licenses/LICENSE-2.0) distributed extension, developed
in [PHP](https://www.php.net) with [Nette framework](https://nette.org) and in [Python](https://python.org).

### Features:

- Tuya local network communication
- Tuya cloud network communication
- Tuya connector management for [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) [devices module](https://github.com/FastyBird/devices-module)
- Tuya device management for [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) [devices module](https://github.com/FastyBird/devices-module)
- [{JSON:API}](https://jsonapi.org/) schemas for full api access
- Integrated connector Python worker

## Requirements

PHP part of [FastyBird](https://www.fastybird.com) Tuya connector is tested against PHP 7.4
and [ReactPHP http](https://github.com/reactphp/http) 0.8 event-driven, streaming plaintext HTTP server
and [Nette framework](https://nette.org/en/) 3.0 PHP framework for real programmers

Python part of [FastyBird](https://www.fastybird.com) Tuya connector is tested against [Python 3.7](http://python.org)

## Installation

### Manual installation

#### Application backend in PHP

The best way to install **fastybird/tuya-connector** is using [Composer](http://getcomposer.org/):

```sh
composer require fastybird/tuya-connector
```

#### Application workers in Python

The best way to install **fastybird-tuya-connector** is using [Pip](https://pip.pypa.io/en/stable/):

```sh
pip install fastybird-tuya-connector
```

### Marketplace installation

You could install this connector in your [FastyBird](https://www.fastybird.com) [IoT](https://en.wikipedia.org/wiki/Internet_of_things) application under marketplace section

## Documentation

Learn how to consume & publish messages in [documentation](https://github.com/FastyBird/tuya-connector/blob/master/.docs/en/index.md).

***
Homepage [https://www.fastybird.com](https://www.fastybird.com) and repository [https://github.com/FastyBird/tuya-connector](https://github.com/FastyBird/tuya-connector).
