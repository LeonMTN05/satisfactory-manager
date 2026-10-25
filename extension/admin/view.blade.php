<div class="row">
  <div class="col-xs-12">
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title">Satisfactory servers</h3>
      </div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <tr>
            <th>Server</th>
            <th>Owner</th>
            <th>Status</th>
            <th>Players</th>
            <th>Tier</th>
            <th>Last backup</th>
            <th>Backups</th>
          </tr>
          @forelse($servers as $row)
            <tr>
              <td>
                <a href="/server/{{ $row['server']->uuidShort }}">{{ $row['server']->name }}</a>
                <a href="/admin/servers/view/{{ $row['server']->id }}" class="text-muted small" style="margin-left:6px">admin</a>
              </td>
              <td>{{ $row['server']->user->username ?? '–' }}</td>
              <td>
                @if(!$row['metric'])
                  <span class="label label-default">no data</span>
                @elseif($row['metric']->online)
                  <span class="label label-success">online</span>
                @else
                  <span class="label label-danger">offline</span>
                @endif
              </td>
              <td>{{ $row['metric'] && $row['metric']->online ? $row['metric']->players . ' / ' . $row['metric']->player_limit : '–' }}</td>
              <td>{{ $row['metric']->tech_tier ?? '–' }}</td>
              <td>{{ $row['last_backup'] ? $row['last_backup']->diffForHumans() : '–' }}</td>
              <td>{{ $row['backups'] }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted">No Satisfactory servers found. Check the egg selection below.</td></tr>
          @endforelse
        </table>
      </div>
    </div>
  </div>
</div>

<form action="{{ $root }}" method="POST">
  {{ csrf_field() }}
  <input type="hidden" name="_method" value="PATCH" />
  <div class="row">
    <div class="col-xs-12 col-md-6">
      <div class="box box-info">
        <div class="box-header with-border"><h3 class="box-title">Eggs</h3></div>
        <div class="box-body">
          <p class="text-muted small">Servers with these eggs are managed by the extension. Detected automatically: eggs that start <code>FactoryGame</code> or install Steam app 1690800.</p>
          @foreach($eggs as $egg)
            <div class="checkbox">
              <label>
                <input type="checkbox" name="eggs[]" value="{{ $egg->id }}" @if(in_array($egg->id, $eggIds)) checked @endif />
                {{ $egg->name }}
                @if(in_array($egg->id, $detected))<span class="label label-info">detected</span>@endif
              </label>
            </div>
          @endforeach
          <p class="text-muted small">Tip: to hide the Satisfactory tab on other servers, restrict this extension to the same eggs in <a href="/admin/extensions">Blueprint's extension settings</a>.</p>
        </div>
      </div>
    </div>

    <div class="col-xs-12 col-md-6">
      <div class="box box-warning">
        <div class="box-header with-border"><h3 class="box-title">Features</h3></div>
        <div class="box-body">
          @php
            $features = [
            'metrics' => ['History & server list badges', 'Stores one data point per server and minute.'],
            'backups' => ['Save backups', 'Allows manual and scheduled backups (stored inside each server, counts towards its disk space).'],
            'restarts' => ['Scheduled restarts', 'Times use the panel time zone (' . $timezone . ').'],
            'watchdog' => ['Crash watchdog', 'Restarts servers whose process runs but no longer answers.'],
            'update_check' => ['Update check', 'Asks api.steamcmd.net (third party) for the newest Satisfactory build, at most once per hour.'],
            ];
          @endphp
          @foreach($features as $key => [$label, $hint])
            <div class="checkbox">
              <label>
                <input type="hidden" name="{{ $key }}" value="0" />
                <input type="checkbox" name="{{ $key }}" value="1" @if($settings[$key]) checked @endif />
                <strong>{{ $label }}</strong>
              </label>
              <p class="text-muted small" style="margin-left:20px">{{ $hint }}</p>
            </div>
          @endforeach
          <div class="form-group">
            <label class="control-label">Keep history for (days)</label>
            <input type="number" min="1" max="365" name="retention_days" value="{{ $settings['retention_days'] }}" class="form-control" style="max-width:140px" />
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary btn-sm pull-right">Save</button>
        </div>
      </div>
    </div>
  </div>
</form>

<div class="row">
  <div class="col-xs-12">
    <p class="text-muted small">
      Satisfactory Manager {version} · Unofficial community project, not affiliated with Coffee Stain Studios.
      Satisfactory is a trademark of Coffee Stain Studios AB. Pterodactyl® and Blueprint belong to their respective owners.
    </p>
  </div>
</div>
