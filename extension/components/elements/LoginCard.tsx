import React, { useState } from 'react';
import { faKey } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Input from '@/components/elements/Input';
import { Button } from '@/components/elements/button/index';
import { login } from '../api/sfmanager';
import { t } from '../lang';
import { Field, useNotify } from './ui';

export default ({ uuid, onDone }: { uuid: string; onDone: () => void }) => {
  const notify = useNotify();
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    notify.clear();
    login(uuid, password)
      .then(() => {
        setPassword('');
        onDone();
      })
      .catch(notify.error)
      .then(() => setBusy(false));
  };

  return (
    <TitledGreyBox title={t('login.title')} icon={faKey}>
      <form onSubmit={submit}>
        <p className={'text-sm text-neutral-300 mb-4'}>{t('login.intro')}</p>
        <Field label={t('login.password')}>
          <Input
            type={'password'}
            autoComplete={'current-password'}
            value={password}
            onChange={(e) => setPassword(e.currentTarget.value)}
          />
        </Field>
        <div className={'flex justify-end'}>
          <Button type={'submit'} disabled={busy || !password}>
            {t('login.submit')}
          </Button>
        </div>
      </form>
    </TitledGreyBox>
  );
};
