import React, { useEffect, useState } from 'react';
import { faArchive, faClock, faHeartbeat, faSyncAlt } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import getFileDownloadUrl from '@/api/server/files/getFileDownloadUrl';
import { bytesToString } from '@/lib/formatters';
import {
  Automation,
  AutomationSettings,
  Backup,
  createBackup,
  deleteBackup,
  getAutomation,
  getBackups,
  restoreBackup,
  runUpdate,
  saveAutomation,
  setBranch,
} from '../api/sfmanager';
import { t } from '../lang';
import { Badge, Field, useNotify } from './ui';

const INTERVALS = [0, 1, 2, 3, 6, 12, 24];

const Check = ({ checked, onChange, label }: { checked: boolean; onChange: (v: boolean) => void; label: string }) => (
  <label className={'flex items-center text-sm cursor-pointer mb-2'}>
    <input type={'checkbox'} className={'mr-2'} checked={checked} onChange={(e) => onChange(e.currentTarget.checked)} />
    {label}
  </label>
);

const Disabled = ({ text }: { text: string }) => <p className={'text-sm text-neutral-400'}>{text}</p>;

type Pending = { kind: 'restore' | 'delete'; backup: Backup } | { kind: 'update' } | null;

export default ({ uuid, onChange }: { uuid: string; onChange: () => void }) => {
  const notify = useNotify();
  const [data, setData] = useState<Automation | null>(null);
  const [settings, setSettings] = useState<AutomationSettings | null>(null);
  const [backups, setBackups] = useState<Backup[] | null>(null);
  const [newTime, setNewTime] = useState('04:00');
  const [busy, setBusy] = useState(false);
  const [pending, setPending] = useState<Pending>(null);

  const loadAll = () => {
    getAutomation(uuid)
      .then((a) => {
        setData(a);
        setSettings(a.settings);
      })
      .catch(notify.error);
    getBackups(uuid)
      .then((b) => setBackups(b.backups))
      .catch(() => setBackups([]));
  };

  useEffect(loadAll, [uuid]);

  const run = (action: () => Promise<any>, success: string) => {
    setBusy(true);
    notify.clear();
    return action()
      .then(() => {
        notify.success(success);
        onChange();
      })
      .catch(notify.error)
      .then(() => {
        setBusy(false);
        setPending(null);
        loadAll();
      });
  };

  if (!data || !settings) return <Spinner centered size={'large'} />;

  const f = data.features;
  const u = data.update;
  const dirty = JSON.stringify(settings) !== JSON.stringify(data.settings);
  const set = <G extends keyof AutomationSettings>(group: G, values: Partial<AutomationSettings[G]>) =>
    setSettings({ ...settings, [group]: { ...settings[group], ...values } });
  const date = (b: Backup) => new Date(b.created_at).toLocaleString();

  const download = (b: Backup) =>
    getFileDownloadUrl(uuid, `/${b.file}`)
      .then((url) => {
        window.location.href = url;
      })
      .catch(notify.error);

  const confirmPending = () => {
    if (!pending) return;
    if (pending.kind === 'restore') run(() => restoreBackup(uuid, pending.backup.id), t('backup.restored'));
    else if (pending.kind === 'delete') run(() => deleteBackup(uuid, pending.backup.id), t('backup.deleted'));
    else run(() => runUpdate(uuid), t('update.started'));
  };

  const saveBar = dirty && (
    <div className={'fixed bottom-4 right-4 z-50 flex gap-2 bg-neutral-800 border border-neutral-600 rounded p-3 shadow-lg'}>
      <Button.Text onClick={() => setSettings(data.settings)} disabled={busy}>
        {t('common.cancel')}
      </Button.Text>
      <Button
        disabled={busy}
        onClick={() => run(() => saveAutomation(uuid, settings), t('auto.saved'))}
      >
        {t('common.save')}
      </Button>
    </div>
  );

  return (
    <div className={'grid lg:grid-cols-2 gap-4'}>
      {/* Backups */}
      <TitledGreyBox title={t('backup.title')} icon={faArchive} className={'lg:row-span-2'}>
        {!f.backups ? (
          <Disabled text={t('backup.disabled')} />
        ) : (
          <>
            <div className={'flex items-start justify-between gap-4 mb-3'}>
              <p className={'text-sm text-neutral-300'}>{t('backup.intro')}</p>
              <Button disabled={busy} onClick={() => run(() => createBackup(uuid), t('backup.created'))}>
                {busy ? '…' : t('backup.create')}
              </Button>
            </div>
            {!backups ? (
              <Spinner centered size={'small'} />
            ) : backups.length === 0 ? (
              <p className={'text-sm text-neutral-400 text-center py-4'}>{t('backup.empty')}</p>
            ) : (
              <div className={'divide-y divide-neutral-600'}>
                {backups.map((b) => (
                  <div key={b.id} className={'flex flex-wrap items-center justify-between gap-2 py-2'}>
                    <div>
                      <p className={'text-sm'}>
                        {date(b)}
                        <Badge>{t(`backup.reason.${b.reason}` as any)}</Badge>
                      </p>
                      <p className={'text-xs text-neutral-400'}>
                        {bytesToString(b.size)}
                        {b.session && <> · {b.session}</>}
                      </p>
                    </div>
                    <div className={'flex gap-2'}>
                      <Button.Text size={Button.Sizes.Small} onClick={() => download(b)}>
                        {t('saves.download')}
                      </Button.Text>
                      <Button
                        size={Button.Sizes.Small}
                        disabled={busy}
                        onClick={() => setPending({ kind: 'restore', backup: b })}
                      >
                        {t('backup.restore')}
                      </Button>
                      <Button.Danger
                        size={Button.Sizes.Small}
                        disabled={busy}
                        onClick={() => setPending({ kind: 'delete', backup: b })}
                      >
                        {t('common.delete')}
                      </Button.Danger>
                    </div>
                  </div>
                ))}
              </div>
            )}

            <p className={'text-xs uppercase text-neutral-400 mt-6 mb-2'}>{t('auto.backupSchedule')}</p>
            <div className={'grid md:grid-cols-2 gap-x-4'}>
              <Field label={t('auto.interval')}>
                <Select
                  value={settings.backup.interval_hours}
                  onChange={(e) => set('backup', { interval_hours: Number(e.currentTarget.value) })}
                >
                  {INTERVALS.map((h) => (
                    <option key={h} value={h}>
                      {h === 0 ? t('auto.off') : t('auto.everyHours', h)}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label={t('auto.keep')} hint={t('auto.keepHint')}>
                <Input
                  type={'number'}
                  min={1}
                  max={100}
                  value={settings.backup.keep}
                  onChange={(e) => set('backup', { keep: Math.max(1, Number(e.currentTarget.value) || 1) })}
                />
              </Field>
            </div>
            <Check
              label={t('auto.beforeRestart')}
              checked={settings.backup.before_restart}
              onChange={(v) => set('backup', { before_restart: v })}
            />
            <Check
              label={t('auto.beforeUpdate')}
              checked={settings.backup.before_update}
              onChange={(v) => set('backup', { before_update: v })}
            />
            <Check
              label={t('auto.beforeLoad')}
              checked={settings.backup.before_load}
              onChange={(v) => set('backup', { before_load: v })}
            />
          </>
        )}
      </TitledGreyBox>

      {/* Updates */}
      <TitledGreyBox title={t('update.title')} icon={faSyncAlt}>
        <div className={'grid grid-cols-2 gap-3 mb-3'}>
          <div>
            <p className={'text-xs uppercase text-neutral-400'}>{t('update.installed')}</p>
            <p className={'font-mono'}>{u.installed ?? t('update.unknown')}</p>
          </div>
          <div>
            <p className={'text-xs uppercase text-neutral-400'}>{t('update.latest', u.branch)}</p>
            <p className={'font-mono'}>
              {u.latest[u.branch]?.build ?? t('update.unknown')}
              {u.update_available === true && <Badge className={'bg-yellow-600'}>{t('update.available')}</Badge>}
              {u.update_available === false && <Badge className={'bg-green-600'}>{t('update.upToDate')}</Badge>}
            </p>
          </div>
        </div>
        {!u.check_enabled && <p className={'text-xs text-neutral-400 mb-3'}>{t('update.checkOff')}</p>}
        {!u.auto_update && <p className={'text-sm text-yellow-400 mb-3'}>{t('update.autoOff')}</p>}
        {u.branch_editable && (
          <Field label={t('update.branch')} hint={t('update.branchHint')}>
            <Select
              value={u.branch}
              disabled={busy}
              onChange={(e) => {
                const branch = e.currentTarget.value;
                run(() => setBranch(uuid, branch), t('update.branchSet'));
              }}
            >
              <option value={'public'}>public</option>
              <option value={'experimental'}>experimental</option>
            </Select>
          </Field>
        )}
        <div className={'flex justify-end'}>
          <Button disabled={busy || !u.auto_update} onClick={() => setPending({ kind: 'update' })}>
            {f.backups && settings.backup.before_update ? t('update.run') : t('update.runNoBackup')}
          </Button>
        </div>
      </TitledGreyBox>

      <div className={'space-y-4'}>
        {/* Scheduled restarts */}
        <TitledGreyBox title={t('auto.restartTitle')} icon={faClock}>
          {!f.restarts ? (
            <Disabled text={t('auto.restartDisabled')} />
          ) : (
            <>
              <p className={'text-sm text-neutral-300 mb-3'}>{t('auto.restartIntro', data.timezone)}</p>
              <div className={'flex flex-wrap gap-2 mb-3'}>
                {settings.restart.times.length === 0 && (
                  <span className={'text-sm text-neutral-400'}>{t('auto.noTimes')}</span>
                )}
                {settings.restart.times.map((time) => (
                  <span key={time} className={'inline-flex items-center bg-neutral-600 rounded px-2 py-1 text-sm font-mono'}>
                    {time}
                    <button
                      className={'ml-2 text-neutral-300 hover:text-red-400'}
                      onClick={() => set('restart', { times: settings.restart.times.filter((x) => x !== time) })}
                      aria-label={t('common.delete')}
                    >
                      ×
                    </button>
                  </span>
                ))}
              </div>
              <div className={'flex gap-2 mb-3'}>
                <div className={'w-36'}>
                  <Input type={'time'} value={newTime} onChange={(e) => setNewTime(e.currentTarget.value)} />
                </div>
                <Button.Text
                  disabled={!newTime || settings.restart.times.includes(newTime) || settings.restart.times.length >= 6}
                  onClick={() => set('restart', { times: [...settings.restart.times, newTime].sort() })}
                >
                  {t('auto.addTime')}
                </Button.Text>
              </div>
              <Check
                label={t('auto.onlyEmpty')}
                checked={settings.restart.only_empty}
                onChange={(v) => set('restart', { only_empty: v })}
              />
            </>
          )}
        </TitledGreyBox>

        {/* Watchdog */}
        <TitledGreyBox title={t('auto.watchdogTitle')} icon={faHeartbeat}>
          {!f.watchdog ? (
            <Disabled text={t('auto.watchdogDisabled')} />
          ) : (
            <>
              <p className={'text-sm text-neutral-300 mb-3'}>{t('auto.watchdogIntro')}</p>
              <Check
                label={t('auto.watchdogEnable')}
                checked={settings.watchdog.enabled}
                onChange={(v) => set('watchdog', { enabled: v })}
              />
              {settings.watchdog.enabled && (
                <Field label={t('auto.watchdogMinutes')}>
                  <div className={'w-36'}>
                    <Input
                      type={'number'}
                      min={1}
                      max={30}
                      value={settings.watchdog.minutes}
                      onChange={(e) =>
                        set('watchdog', { minutes: Math.min(30, Math.max(1, Number(e.currentTarget.value) || 1)) })
                      }
                    />
                  </div>
                </Field>
              )}
            </>
          )}
        </TitledGreyBox>
      </div>

      {saveBar}

      <Dialog.Confirm
        open={!!pending}
        onClose={() => setPending(null)}
        title={
          pending?.kind === 'restore'
            ? t('backup.restore')
            : pending?.kind === 'delete'
            ? t('common.delete')
            : t('update.title')
        }
        confirm={t('common.confirm')}
        onConfirmed={confirmPending}
      >
        {pending?.kind === 'restore' && t('backup.confirmRestore', date(pending.backup))}
        {pending?.kind === 'delete' && t('backup.confirmDelete', date(pending.backup))}
        {pending?.kind === 'update' && t('update.confirm')}
      </Dialog.Confirm>
    </div>
  );
};
