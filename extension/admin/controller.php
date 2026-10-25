<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\{identifier};

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\Factory as ViewFactory;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfBackup;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Models\SfMetric;
use Pterodactyl\BlueprintFramework\Extensions\sfmanager\Services\GlobalSettings;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;

class {identifier}ExtensionController extends Controller
{
  public function __construct(
    private ViewFactory $view,
    private BlueprintExtensionLibrary $blueprint,
    private GlobalSettings $global,
    private AlertsMessageBag $alert,
  ) {}

  public function index(): View
  {
    $servers = $this->global->servers()->with(['user', 'egg'])->orderBy('name')->get()->map(function ($server) {
      $metric = SfMetric::query()->where('server_id', $server->id)->latest('id')->first();
      $backup = SfBackup::query()->where('server_id', $server->id)->latest('created_at')->first();

      return [
        'server' => $server,
        'metric' => $metric && $metric->created_at->gt(now()->subMinutes(3)) ? $metric : null,
        'last_backup' => $backup?->created_at,
        'backups' => SfBackup::query()->where('server_id', $server->id)->count(),
      ];
    });

    return $this->view->make('admin.extensions.{identifier}.index', [
      'root' => '/admin/extensions/{identifier}',
      'blueprint' => $this->blueprint,
      'settings' => $this->global->all(),
      'eggs' => Egg::query()->orderBy('name')->get(['id', 'name']),
      'eggIds' => $this->global->eggIds(),
      'detected' => $this->global->detectEggIds(),
      'servers' => $servers,
      'timezone' => config('app.timezone'),
    ]);
  }

  public function update({identifier}SettingsFormRequest $request): RedirectResponse
  {
    $data = $request->validated();
    $eggs = array_map('intval', $data['eggs'] ?? []);
    // Same as auto-detection → keep auto mode, so new Satisfactory eggs are picked up automatically.
    $detected = $this->global->detectEggIds();
    sort($eggs);
    sort($detected);
    $this->global->set('eggs', $eggs === $detected ? null : $eggs);

    foreach (['metrics', 'backups', 'restarts', 'watchdog', 'update_check'] as $flag) {
      $this->global->set($flag, (bool) ($data[$flag] ?? false));
    }
    $this->global->set('retention_days', (int) $data['retention_days']);

    $this->alert->success('Satisfactory Manager settings saved.')->flash();

    return redirect()->route('admin.extensions.{identifier}.index');
  }
}

class {identifier}SettingsFormRequest extends AdminFormRequest
{
  public function rules(): array
  {
    return [
      'eggs' => 'nullable|array',
      'eggs.*' => 'integer|exists:eggs,id',
      'metrics' => 'nullable|boolean',
      'backups' => 'nullable|boolean',
      'restarts' => 'nullable|boolean',
      'watchdog' => 'nullable|boolean',
      'update_check' => 'nullable|boolean',
      'retention_days' => 'required|integer|min:1|max:365',
    ];
  }
}
