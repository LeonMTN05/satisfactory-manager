import React from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import { ServerContext } from '@/state/server';
import { useStatus } from '../api/sfmanager';
import { t } from '../lang';
import { FLASH_KEY } from '../elements/ui';
import StatusCard from '../elements/StatusCard';
import ClaimWizard from '../elements/ClaimWizard';
import LoginCard from '../elements/LoginCard';
import ConnectionCard from '../elements/ConnectionCard';
import NewGameCard from '../elements/NewGameCard';

const SatisfactorySection = () => {
  const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
  const serverName = ServerContext.useStoreState((state) => state.server.data!.name);
  const { data: status, revalidate } = useStatus(uuid);

  const refresh = () => {
    revalidate();
  };

  return (
    <ServerContentBlock title={t('title')}>
      <FlashMessageRender byKey={FLASH_KEY} className={'mb-4'} />
      {!status ? (
        <Spinner centered size={'large'} />
      ) : (
        <div className={'grid lg:grid-cols-3 gap-4'}>
          <div className={'lg:col-span-2 space-y-4'}>
            <StatusCard uuid={uuid} status={status} onChange={refresh} />
            {status.reachable && status.access === 'initial' && (
              <ClaimWizard uuid={uuid} defaultName={serverName} onDone={refresh} />
            )}
            {status.reachable && (status.access === 'none' || status.access === 'client') && (
              <LoginCard uuid={uuid} onDone={refresh} />
            )}
            {status.reachable && status.access === 'admin' && (
              <NewGameCard uuid={uuid} defaultName={serverName} onDone={refresh} />
            )}
          </div>
          <div>
            <ConnectionCard uuid={uuid} />
          </div>
        </div>
      )}
    </ServerContentBlock>
  );
};

export default SatisfactorySection;
