<?php

namespace AutoPimple;

use Psr\Container\ContainerExceptionInterface;

final class InvalidFactoryException extends \InvalidArgumentException implements ContainerExceptionInterface
{
}
