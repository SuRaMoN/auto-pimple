<?php

namespace AutoPimple;

use Psr\Container\ContainerExceptionInterface;

final class InvalidDefinitionException extends \InvalidArgumentException implements ContainerExceptionInterface
{
}
