<?php

namespace TorqIT\GridNullFilterBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;

class TorqITGridNullFilterBundle extends AbstractPimcoreBundle implements PimcoreBundleAdminClassicInterface
{
    public function getJsPaths(): array
    {
        return [
            '/bundles/torqitgridnullfilter/js/pimcore/startup.js'
        ];
    }

    public function getCssPaths(): array
    {
        return [];
    }

    public function getEditmodeJsPaths(): array
    {
        return [];
    }

    public function getEditmodeCssPaths(): array
    {
        return [];
    }
}
