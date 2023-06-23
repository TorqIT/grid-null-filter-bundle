<?php

namespace TorqIT\GridNullFilterBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;

class TorqITGridNullFilterBundle extends AbstractPimcoreBundle
{
    public function getJsPaths(): array
    {
        return [
            '/bundles/torqitgridnullfilter/js/pimcore/startup.js'
        ];
    }
}