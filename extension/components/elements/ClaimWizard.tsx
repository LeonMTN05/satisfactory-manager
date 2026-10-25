import React, { useState } from 'react';
import { faMagic } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { Button } from '@/components/elements/button/index';
import { STARTING_LOCATIONS, claimServer } from '../api/sfmanager';
import { t } from '../lang';
import { Field, useNotify } from './ui';

export default ({ uuid, defaultName, onDone }: { uuid: string; defaultName: string; onDone: () => void }) => {
  const notify = useNotify();
  const [busy, setBusy] = useState(false);
  const [serverName, setServerName] = useState(defaultName);
  const [adminPassword, setAdminPassword] = useState('');
  const [adminRepeat, setAdminRepeat] = useState('');
  const [clientPassword, setClientPassword] = useState('');
  const [newGame, setNewGame] = useState(true);
  const [sessionName, setSessionName] = useState(defaultName);
  const [location, setLocation] = useState(STARTING_LOCATIONS[0]);
  const [skipOnboarding, setSkipOnboarding] = useState(false);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    notify.clear();
    if (adminPassword.length < 4) return notify.errorText(t('claim.tooShort'));
    if (adminPassword !== adminRepeat) return notify.errorText(t('claim.mismatch'));

    setBusy(true);
    claimServer(uuid, {
      server_name: serverName,
      admin_password: adminPassword,
      client_password: clientPassword || undefined,
      new_game: newGame
        ? { session_name: sessionName, starting_location: location, skip_onboarding: skipOnboarding }
        : undefined,
    })
      .then(() => {
        notify.success(t('claim.done'));
        onDone();
      })
      .catch(notify.error)
      .then(() => setBusy(false));
  };

  return (
    <TitledGreyBox title={t('claim.title')} icon={faMagic}>
      <form onSubmit={submit}>
        <p className={'text-sm text-neutral-300 mb-4'}>{t('claim.intro')}</p>
        <div className={'grid md:grid-cols-2 gap-x-4'}>
          <Field label={t('claim.serverName')}>
            <Input value={serverName} maxLength={64} onChange={(e) => setServerName(e.currentTarget.value)} />
          </Field>
          <div />
          <Field label={t('claim.adminPassword')}>
            <Input
              type={'password'}
              autoComplete={'new-password'}
              value={adminPassword}
              onChange={(e) => setAdminPassword(e.currentTarget.value)}
            />
          </Field>
          <Field label={t('claim.adminPasswordRepeat')}>
            <Input
              type={'password'}
              autoComplete={'new-password'}
              value={adminRepeat}
              onChange={(e) => setAdminRepeat(e.currentTarget.value)}
            />
          </Field>
          <Field label={t('claim.clientPassword')} hint={t('claim.clientPasswordHint')}>
            <Input
              type={'password'}
              autoComplete={'new-password'}
              value={clientPassword}
              onChange={(e) => setClientPassword(e.currentTarget.value)}
            />
          </Field>
        </div>

        <label className={'flex items-center text-sm mb-4 cursor-pointer'}>
          <input type={'checkbox'} className={'mr-2'} checked={newGame} onChange={(e) => setNewGame(e.currentTarget.checked)} />
          {t('claim.newGame')}
        </label>

        {newGame && (
          <div className={'grid md:grid-cols-2 gap-x-4 pl-6 border-l-2 border-neutral-600 mb-4'}>
            <Field label={t('claim.sessionName')}>
              <Input value={sessionName} maxLength={64} onChange={(e) => setSessionName(e.currentTarget.value)} />
            </Field>
            <Field label={t('claim.startingLocation')}>
              <Select value={location} onChange={(e) => setLocation(e.currentTarget.value)}>
                {STARTING_LOCATIONS.map((l) => (
                  <option key={l} value={l}>
                    {l}
                  </option>
                ))}
              </Select>
            </Field>
            <label className={'flex items-center text-sm cursor-pointer'}>
              <input
                type={'checkbox'}
                className={'mr-2'}
                checked={skipOnboarding}
                onChange={(e) => setSkipOnboarding(e.currentTarget.checked)}
              />
              {t('claim.skipOnboarding')}
            </label>
          </div>
        )}

        <div className={'flex justify-end'}>
          <Button type={'submit'} disabled={busy || !serverName || !adminPassword || (newGame && !sessionName)}>
            {t('claim.submit')}
          </Button>
        </div>
      </form>
    </TitledGreyBox>
  );
};
