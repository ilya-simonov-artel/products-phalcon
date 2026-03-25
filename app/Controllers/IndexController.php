<?php

declare(strict_types=1);

namespace App\Controllers;

final class IndexController extends ControllerBase
{
    public function indexAction(): void
    {
        $this->view->setVar('appName', $this->container->get('config')->path('app.name'));
        $viteAssets = $this->getViteAssets();
        $this->view->setVar('mainJs', $viteAssets['js']);
        $this->view->setVar('mainCss', $viteAssets['css']);
    }
}
