import React, { useEffect, useState } from 'react';
import { faNetworkWired } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import CopyOnClick from '@/components/elements/CopyOnClick';
import Spinner from '@/components/elements/Spinner';
import { Button } from '@/components/elements/button/index';
import { Connection, getConnection } from '../api/sfmanager';
import { t } from '../lang';
import { Dot, useNotify } from './ui';

export default ({ uuid }: { uuid: string }) => {
  const notify = useNotify();
  const [conn, setConn] = useState<Connection | null>(null);
  const [busy, setBusy] = useState(false);

  const load = () => {
    setBusy(true);
    getConnection(uuid)
      .then(setConn)
      .catch(notify.error)
      .then(() => setBusy(false));
  };

  useEffect(load, [uuid]);

  const full = conn ? `${conn.address}:${conn.port}` : '';

  return (
    <TitledGreyBox title={t('conn.title')} icon={faNetworkWired}>
      {!conn ? (
        <Spinner centered size={'small'} />
      ) : (
        <>
          <p className={'text-xs uppercase text-neutral-400'}>{t('conn.address')}</p>
          <CopyOnClick text={full}>
            <p className={'font-mono text-lg mb-3 cursor-pointer'}>{full}</p>
          </CopyOnClick>
          <p className={'text-xs uppercase text-neutral-400'}>{t('conn.reliable')}</p>
          <p className={'font-mono mb-3'}>{conn.reliable_port}</p>

          {!conn.reliable_port_allocated && (
            <p className={'text-sm text-yellow-400 mb-3'}>{t('conn.reliableMissing', conn.reliable_port)}</p>
          )}

          <ul className={'text-sm space-y-1 mb-3'}>
            <li className={'flex items-center'}>
              <Dot ok={conn.checks.game_tcp} />
              {t('conn.gameTcp')} ({conn.port})
            </li>
            <li className={'flex items-center'}>
              <Dot ok={conn.checks.reliable_tcp} />
              {t('conn.reliableTcp')} ({conn.reliable_port})
            </li>
          </ul>
          <p className={'text-xs text-neutral-400 mb-3'}>{t('conn.hint', conn.port)}</p>
          <div className={'flex justify-end'}>
            <Button.Text size={Button.Sizes.Small} onClick={load} disabled={busy}>
              {t('conn.recheck')}
            </Button.Text>
          </div>
        </>
      )}
    </TitledGreyBox>
  );
};
