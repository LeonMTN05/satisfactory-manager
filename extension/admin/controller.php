<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\{identifier};

use Illuminate\View\View;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;

class {identifier}ExtensionController extends Controller
{
  public function __construct(
    private ViewFactory $view,
    private BlueprintExtensionLibrary $blueprint,
  ) {}

  public function index(): View
  {
    return $this->view->make('admin.extensions.{identifier}.index', [
      'root' => '/admin/extensions/{identifier}',
      'blueprint' => $this->blueprint,
    ]);
  }
}
