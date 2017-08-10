<?php

namespace DonkeyCode\RestBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use DonkeyCode\RestBundle\DependencyInjection\Compiler\AddPropelTagPass;
use DonkeyCode\RestBundle\DependencyInjection\Security\Factory\VarnishFactory;

class DonkeyCodeRestBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AddPropelTagPass());
    }
}