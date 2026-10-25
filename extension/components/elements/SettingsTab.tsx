import React, { useEffect, useMemo, useState } from 'react';
import { faCogs, faFlask, faIdCard } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { Button } from '@/components/elements/button/index';
import {
  Settings,
  getSettings,
  renameServer,
  setAdminPassword,
  setClientPassword,
  updateAdvanced,
  updateOptions,
} from '../api/sfmanager';
import { has, t } from '../lang';
import { Field, useNotify } from './ui';

type Values = Record<string, string>;

const isBool = (v: string) => v === 'True' || v === 'False';

// Known server options in a sensible order; unknown ones go to "more options".
const KNOWN_OPTIONS = [
  'FG.DSAutoPause',
  'FG.DSAutoSaveOnDisconnect',
  'FG.AutosaveInterval',
  'FG.NetworkQuality',
  'FG.EnableSeasonalEvents',
  'FG.SendGameplayData',
  'FG.ServerRestartTimeSlot',
];

/** One option row: switch for True/False, select for network quality, text field otherwise. */
const OptionInput = ({ id, value, onChange }: { id: string; value: string; onChange: (v: string) => void }) => {
  if (isBool(value)) {
    return (
      <input
        type={'checkbox'}
        className={'w-4 h-4'}
        checked={value === 'True'}
        onChange={(e) => onChange(e.currentTarget.checked ? 'True' : 'False')}
      />
    );
  }
  if (id === 'FG.NetworkQuality') {
    return (
      <Select value={value} onChange={(e) => onChange(e.currentTarget.value)}>
        {['0', '1', '2', '3'].map((q) => (
          <option key={q} value={q}>
            {t(`net.${q}` as any)}
          </option>
        ))}
      </Select>
    );
  }
  return <Input value={value} maxLength={128} onChange={(e) => onChange(e.currentTarget.value)} />;
};

const label = (prefix: 'opt' | 'adv', id: string) => {
  const key = `${prefix}.${id}`;
  return has(key) ? t(key) : id;
};

/** Grid of options; returns only changed values on save. */
const OptionsForm = ({
  prefix,
  initial,
  order,
  onSave,
  busy,
}: {
  prefix: 'opt' | 'adv';
  initial: Values;
  order: string[];
  onSave: (changes: Values) => void;
  busy: boolean;
}) => {
  const [values, setValues] = useState<Values>(initial);
  useEffect(() => setValues(initial), [initial]);

  const known = order.filter((k) => k in values);
  const others = Object.keys(values).filter((k) => !order.includes(k)).sort();
  const changes = Object.fromEntries(Object.entries(values).filter(([k, v]) => initial[k] !== v));

  const row = (id: string) => (
    <div key={id} className={'flex items-center justify-between gap-4 py-2'}>
      <span className={'text-sm'} title={id}>
        {label(prefix, id)}
      </span>
      <div className={isBool(values[id]) ? '' : 'w-40'}>
        <OptionInput id={id} value={values[id]} onChange={(v) => setValues({ ...values, [id]: v })} />
      </div>
    </div>
  );

  return (
    <>
      <div className={'divide-y divide-neutral-600'}>{known.map(row)}</div>
      {others.length > 0 && (
        <>
          {prefix === 'opt' && <p className={'text-xs uppercase text-neutral-400 mt-4'}>{t('settings.more')}</p>}
          <div className={'divide-y divide-neutral-600'}>{others.map(row)}</div>
        </>
      )}
      <div className={'flex justify-end mt-4'}>
        <Button disabled={busy || Object.keys(changes).length === 0} onClick={() => onSave(changes)}>
          {t('common.save')}
        </Button>
      </div>
    </>
  );
};

export default ({ uuid, onChange }: { uuid: string; onChange: () => void }) => {
  const notify = useNotify();
  const [settings, setSettings] = useState<Settings | null>(null);
  const [busy, setBusy] = useState(false);
  const [serverName, setServerName] = useState('');
  const [clientPassword, setClientPw] = useState('');
  const [adminPassword, setAdminPw] = useState('');
  const [adminRepeat, setAdminRepeat] = useState('');

  const refresh = () => getSettings(uuid).then(setSettings).catch(notify.error);

  // Stable objects, so the forms only reset when fresh data arrives.
  const serverOptions = useMemo(() => (settings ? { ...settings.options, ...settings.pending_options } : {}), [settings]);
  const advancedOrder = useMemo(
    () => (settings ? Object.keys(settings.advanced).filter((k) => has(`adv.${k}`)) : []),
    [settings]
  );

  useEffect(() => {
    refresh();
  }, [uuid]);

  const run = (action: () => Promise<any>, success: string, after?: () => void) => {
    setBusy(true);
    notify.clear();
    action()
      .then(() => {
        notify.success(success);
        after?.();
        onChange();
      })
      .catch(notify.error)
      .then(() => {
        setBusy(false);
        refresh();
      });
  };

  if (!settings) return <Spinner centered size={'large'} />;

  const pending = Object.keys(settings.pending_options).length > 0;

  return (
    <div className={'grid lg:grid-cols-2 gap-4'}>
      <TitledGreyBox title={t('settings.server')} icon={faCogs}>
        {pending && <p className={'text-sm text-yellow-400 mb-2'}>{t('settings.pending')}</p>}
        <OptionsForm
          prefix={'opt'}
          initial={serverOptions}
          order={KNOWN_OPTIONS}
          busy={busy}
          onSave={(changes) => run(() => updateOptions(uuid, changes), t('settings.saved'))}
        />
      </TitledGreyBox>

      <div className={'space-y-4'}>
        <TitledGreyBox title={t('settings.identity')} icon={faIdCard}>
          <Field label={t('settings.serverName')}>
            <div className={'flex gap-2'}>
              <Input value={serverName} maxLength={64} onChange={(e) => setServerName(e.currentTarget.value)} />
              <Button
                disabled={busy || !serverName}
                onClick={() => run(() => renameServer(uuid, serverName), t('settings.renamed'), () => setServerName(''))}
              >
                {t('settings.rename')}
              </Button>
            </div>
          </Field>
          <Field label={t('settings.clientPassword')} hint={t('settings.clientPasswordHint')}>
            <div className={'flex gap-2'}>
              <Input
                type={'password'}
                autoComplete={'new-password'}
                value={clientPassword}
                onChange={(e) => setClientPw(e.currentTarget.value)}
              />
              <Button
                disabled={busy}
                onClick={() =>
                  run(() => setClientPassword(uuid, clientPassword), t('settings.clientPasswordSet'), () =>
                    setClientPw('')
                  )
                }
              >
                {t('common.save')}
              </Button>
            </div>
          </Field>
          <div className={'grid md:grid-cols-2 gap-x-4'}>
            <Field label={t('settings.adminPassword')}>
              <Input
                type={'password'}
                autoComplete={'new-password'}
                value={adminPassword}
                onChange={(e) => setAdminPw(e.currentTarget.value)}
              />
            </Field>
            <Field label={t('settings.adminPasswordRepeat')}>
              <Input
                type={'password'}
                autoComplete={'new-password'}
                value={adminRepeat}
                onChange={(e) => setAdminRepeat(e.currentTarget.value)}
              />
            </Field>
          </div>
          <div className={'flex justify-end'}>
            <Button
              disabled={busy || adminPassword.length < 4}
              onClick={() => {
                if (adminPassword !== adminRepeat) return notify.errorText(t('claim.mismatch'));
                run(() => setAdminPassword(uuid, adminPassword, adminRepeat), t('settings.adminPasswordSet'), () => {
                  setAdminPw('');
                  setAdminRepeat('');
                });
              }}
            >
              {t('settings.change')}
            </Button>
          </div>
        </TitledGreyBox>

        <TitledGreyBox title={t('settings.advanced')} icon={faFlask}>
          <p className={'text-sm mb-2 ' + (settings.creative_mode ? 'text-neutral-300' : 'text-yellow-400')}>
            {settings.creative_mode ? t('settings.advancedActive') : t('settings.advancedWarn')}
          </p>
          <OptionsForm
            prefix={'adv'}
            initial={settings.advanced}
            order={advancedOrder}
            busy={busy}
            onSave={(changes) => run(() => updateAdvanced(uuid, changes), t('settings.saved'))}
          />
        </TitledGreyBox>
      </div>
    </div>
  );
};
