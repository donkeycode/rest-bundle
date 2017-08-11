<?php

namespace DonkeyCode\RestBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Add tagged provider to the hash generator for user context.
 */
class AddPropelTagPass implements CompilerPassInterface
{
    const TAGGED_SERVICE = 'rest.propel.container_aware';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(self::TAGGED_SERVICE)) {
            return;
        }

        $definition = $container->getDefinition(self::TAGGED_SERVICE);

        foreach ($this->getPropelModels($container) as $model) {
            $definition->addTag("propel.event_listener", [
                'event' => "propel.construct",
                'class' => $model,
                'method' => "onPropelConstruct"
            ]);
        }
    }

    protected function getPropelModels(ContainerBuilder $container)
    {
        $classes = [];

        $files = Finder::create()
            ->files()
            ->name('*.php')
            ->notName('*Query.php')
            ->depth(0)
            ->in($container->getParameter('kernel.root_dir').'/../src/*/*Bundle/Propel')
            ;

        foreach ($files as $file) {
            list ($fold, $path) = explode('src/', $file->getPathname());
            $path = preg_replace('/(.+)\.php/', '$1', $path);
            $path = str_replace('/', '\\', $path);

            $implements = class_implements($path);
            if (array_key_exists(ContainerAwareInterface::class, $implements)) {
                $classes[] = $path;
            }
        }

        return $classes;
    }
}
