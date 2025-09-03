<?php

declare(strict_types=1);

/* 
 * @package   [accessibility]
 * @author    V&T Innovations Core Team
 * @license   SLA/TLA
 * @copyright V&T Innovations 2025 - 2030
 */

/**
 * Namespace
 */
namespace VTInnovations\Accessplus\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AccessplusExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}