<?php

declare(strict_types=1);

/*
 * AccessPlus
 *
 * Package: vtinnovations/accessplus
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

namespace VTInnovations\AccessPlus\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;
use VTInnovations\AccessPlus\VTInnovationsAccessPlusBundle;

/**
 * Contao Manager plugin: registers the bundle, orders it after the core, and
 * loads config/routes.yaml (the frontend-scan ingest endpoint, Phase 6+).
 */
class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    /**
     * @return list<BundleConfig>
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(VTInnovationsAccessPlusBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): ?RouteCollection
    {
        $file = \dirname(__DIR__, 2) . '/config/routes.yaml';

        return $resolver->resolve($file, 'yaml')->load($file, 'yaml');
    }
}
