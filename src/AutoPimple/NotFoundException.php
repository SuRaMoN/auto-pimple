<?php

namespace AutoPimple;

use Psr\Container\NotFoundExceptionInterface;

final class NotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface
{
}
